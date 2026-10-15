#!/usr/bin/env bash

set -Eeuo pipefail

SHA=${1:-}
BASE=${DEPLOY_BASE:-/srv/projetos/kickoff/homologacao}
RELEASES="$BASE/releases"
CURRENT="$BASE/current"
PACKAGE=${DEPLOY_PACKAGE:-/tmp/kickoff-$SHA.tar.gz}
HEALTH_URL=${DEPLOY_HEALTH_URL:-http://127.0.0.1:8080/api/v1/health}
HEALTH_ATTEMPTS=${DEPLOY_HEALTH_ATTEMPTS:-3}
HEALTH_DELAY=${DEPLOY_HEALTH_DELAY:-2}
HEALTH_TIMEOUT=${DEPLOY_HEALTH_TIMEOUT:-15}

RELEASE="$RELEASES/$SHA"
STAGING="$RELEASES/.$SHA.staging.$$"
TEMP_LINK="$BASE/.current-$SHA.$$"
RESTORE_LINK="$BASE/.rollback-$SHA.$$"
LOCK_DIR="$BASE/.deploy-lock"
HEALTH_BODY_FILE=""
PREVIOUS=""
ACTIVATED=0
CREATED_RELEASE=0
LOCK_ACQUIRED=0
ROLLBACK_OK=0
TEST_CHILD_PID=""

log() {
  printf '%s\n' "$*"
}

fail() {
  printf 'ERRO: %s\n' "$*" >&2
  return 1
}

canonical_link() {
  readlink -f -- "$1" 2>/dev/null || true
}

is_current_target() {
  expected=$1
  [ -L "$CURRENT" ] && [ "$(canonical_link "$CURRENT")" = "$expected" ]
}

remove_release_tree() {
  target=$1
  case "$target" in
    "$RELEASES"/*) rm -rf -- "$target" ;;
    *) fail "recusa ao remover caminho fora de releases: $target" ;;
  esac
}

cleanup_artifacts() {
  if [ -n "$TEST_CHILD_PID" ]; then
    kill "$TEST_CHILD_PID" 2>/dev/null || true
    wait "$TEST_CHILD_PID" 2>/dev/null || true
    TEST_CHILD_PID=""
  fi
  rm -f -- "$TEMP_LINK" "$RESTORE_LINK"
  if [ -n "$HEALTH_BODY_FILE" ]; then
    rm -f -- "$HEALTH_BODY_FILE"
  fi
  if [ -e "$STAGING" ]; then
    remove_release_tree "$STAGING" || true
  fi
  if ! rm -f -- "$PACKAGE"; then
    log "AVISO: nao foi possivel remover o pacote temporario: $PACKAGE"
  fi
  if [ "$LOCK_ACQUIRED" -eq 1 ]; then
    rmdir -- "$LOCK_DIR" 2>/dev/null || true
    LOCK_ACQUIRED=0
  fi
}

rollback_current() {
  [ "$ACTIVATED" -eq 1 ] || return 0
  [ -n "$PREVIOUS" ] || return 1

  if is_current_target "$PREVIOUS"; then
    log "Release anterior continua ativa; rollback nao necessario."
    return 0
  fi

  if ! is_current_target "$RELEASE"; then
    log "AVISO: current foi alterado por outro processo; rollback automatico nao sobrescrevera o alvo atual."
    return 1
  fi

  log "Restaurando release anterior: $PREVIOUS"
  rm -f -- "$RESTORE_LINK"
  ln -s -- "$PREVIOUS" "$RESTORE_LINK"
  mv -Tf -- "$RESTORE_LINK" "$CURRENT"

  if ! is_current_target "$PREVIOUS"; then
    fail "rollback nao restaurou o alvo anterior"
    return 1
  fi

  ROLLBACK_OK=1
  log "Rollback executado com sucesso."
}

on_exit() {
  status=$?
  trap - EXIT INT TERM

  if [ "$status" -ne 0 ] && [ "$ACTIVATED" -eq 1 ]; then
    if ! rollback_current; then
      printf 'ERRO CRITICO: falha ao restaurar %s; verifique %s imediatamente.\n' "$PREVIOUS" "$CURRENT" >&2
    fi
  fi

  if [ "$status" -ne 0 ] && [ "$CREATED_RELEASE" -eq 1 ]; then
    if [ "$ACTIVATED" -eq 0 ] || [ "$ROLLBACK_OK" -eq 1 ]; then
      remove_release_tree "$RELEASE" || true
    fi
  fi

  cleanup_artifacts
  exit "$status"
}

on_signal() {
  signal_status=$1
  exit "$signal_status"
}

validate_inputs() {
  [[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || fail "SHA invalido; esperado hash hexadecimal de 40 caracteres"
  [[ "$HEALTH_ATTEMPTS" =~ ^[1-9][0-9]*$ ]] || fail "DEPLOY_HEALTH_ATTEMPTS invalido"
  [[ "$HEALTH_TIMEOUT" =~ ^[1-9][0-9]*$ ]] || fail "DEPLOY_HEALTH_TIMEOUT invalido"
  [[ "$HEALTH_DELAY" =~ ^[0-9]+([.][0-9]+)?$ ]] || fail "DEPLOY_HEALTH_DELAY invalido"
  [ -d "$BASE" ] || fail "diretorio base inexistente: $BASE"
  [ -d "$RELEASES" ] || fail "diretorio de releases inexistente: $RELEASES"
  [ -f "$PACKAGE" ] || fail "pacote inexistente: $PACKAGE"
  [ -L "$CURRENT" ] || fail "current nao e um link simbolico: $CURRENT"

  PREVIOUS=$(canonical_link "$CURRENT")
  [ -n "$PREVIOUS" ] && [ -d "$PREVIOUS" ] || fail "release atual nao pode ser resolvida"
}

acquire_lock() {
  if mkdir -- "$LOCK_DIR" 2>/dev/null; then
    LOCK_ACQUIRED=1
    return 0
  fi
  fail "outro deploy esta em execucao ou existe lock residual: $LOCK_DIR"
}

validate_archive_paths() {
  tar -tzf "$PACKAGE" >/dev/null
  if tar -tzf "$PACKAGE" | grep -Eq '(^|/)\.\.(/|$)|^/'; then
    fail "pacote contem caminho absoluto ou traversal"
  fi
}

release_is_ready() {
  [ -d "$RELEASE" ] \
    && [ ! -L "$RELEASE" ] \
    && [ -f "$RELEASE/.deploy-ready" ] \
    && [ "$(cat "$RELEASE/.deploy-ready")" = "$SHA" ] \
    && [ -f "$RELEASE/public/index.php" ] \
    && [ -L "$RELEASE/storage" ] \
    && [ -L "$RELEASE/config/local.php" ]
}

prepare_release() {
  if [ -e "$RELEASE" ] || [ -L "$RELEASE" ]; then
    if release_is_ready; then
      log "Release $SHA ja preparada; reutilizando de forma idempotente."
      return 0
    fi

    if is_current_target "$RELEASE"; then
      fail "release parcial e o alvo atual; intervencao manual necessaria"
    fi

    log "Removendo release parcial de tentativa anterior: $RELEASE"
    remove_release_tree "$RELEASE"
  fi

  validate_archive_paths
  mkdir -- "$STAGING"
  tar --no-same-owner --no-same-permissions -xzf "$PACKAGE" -C "$STAGING"

  mkdir -p -- "$STAGING/config"
  ln -s -- "$BASE/shared/config/local.php" "$STAGING/config/local.php"
  ln -s -- "$BASE/shared/config/crypto.local.php" "$STAGING/config/crypto.local.php"
  ln -s -- "$BASE/shared/config/microsoft.local.php" "$STAGING/config/microsoft.local.php"
  ln -s -- "$BASE/shared/storage" "$STAGING/storage"

  [ -f "$STAGING/public/index.php" ] || fail "public/index.php ausente no pacote"
  [ -L "$STAGING/storage" ] || fail "link de storage nao foi criado"
  [ -L "$STAGING/config/local.php" ] || fail "link de configuracao nao foi criado"

  printf '%s\n' "$SHA" > "$STAGING/.deploy-ready"
  mv -- "$STAGING" "$RELEASE"
  CREATED_RELEASE=1
  log "Release preparada: $RELEASE"
}

activate_release() {
  if is_current_target "$RELEASE"; then
    log "Release $SHA ja esta ativa."
    return 0
  fi

  ln -s -- "$RELEASE" "$TEMP_LINK"
  # Arma o rollback antes do rename para eliminar a janela entre a troca e o trap.
  ACTIVATED=1
  mv -Tf -- "$TEMP_LINK" "$CURRENT"

  if ! is_current_target "$RELEASE"; then
    fail "troca atomica de current nao ativou a nova release"
  fi
  log "Release ativada atomicamente."
}

health_check() {
  HEALTH_BODY_FILE=$(mktemp "${TMPDIR:-/tmp}/kickoff-health.XXXXXX")
  attempt=1

  while [ "$attempt" -le "$HEALTH_ATTEMPTS" ]; do
    status=000
    if status=$(curl -sS --max-time "$HEALTH_TIMEOUT" -o "$HEALTH_BODY_FILE" -w '%{http_code}' "$HEALTH_URL"); then
      if [ "$status" = 200 ] && grep -Eq '"ok"[[:space:]]*:[[:space:]]*true' "$HEALTH_BODY_FILE"; then
        log "Health check aprovado (HTTP 200, tentativa $attempt)."
        return 0
      fi
    fi

    log "Health check falhou (HTTP $status, tentativa $attempt/$HEALTH_ATTEMPTS)."
    if [ "$attempt" -lt "$HEALTH_ATTEMPTS" ]; then
      sleep "$HEALTH_DELAY"
    fi
    attempt=$((attempt + 1))
  done

  return 1
}

trap on_exit EXIT
trap 'on_signal 130' INT
trap 'on_signal 143' TERM
trap 'on_signal 129' HUP

validate_inputs
acquire_lock

log "Release anterior: $PREVIOUS"
log "Nova release: $RELEASE"

prepare_release
activate_release

if [ "${DEPLOY_TEST_MODE:-0}" = 1 ] && [ -n "${DEPLOY_TEST_PAUSE_AFTER_ACTIVATION:-}" ]; then
  sleep "$DEPLOY_TEST_PAUSE_AFTER_ACTIVATION" &
  TEST_CHILD_PID=$!
  wait "$TEST_CHILD_PID"
  TEST_CHILD_PID=""
fi

health_check || fail "validacao HTTP da nova release falhou"

log "Deploy HML concluido com sucesso."
