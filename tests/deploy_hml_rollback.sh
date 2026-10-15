#!/usr/bin/env bash

set -Eeuo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
DEPLOY_SCRIPT="$ROOT/scripts/deploy-hml-release.sh"
TEST_ROOT=$(mktemp -d "${TMPDIR:-/tmp}/kickoff-deploy-test.XXXXXX")
PASS=0
FAIL=0

cleanup_suite() {
  rm -rf -- "$TEST_ROOT"
}
trap cleanup_suite EXIT

pass() {
  PASS=$((PASS + 1))
  printf 'ok - %s\n' "$1"
}

fail() {
  FAIL=$((FAIL + 1))
  printf 'not ok - %s\n' "$1" >&2
}

assert_eq() {
  expected=$1
  actual=$2
  description=$3
  if [ "$expected" = "$actual" ]; then
    pass "$description"
  else
    fail "$description (esperado: $expected; obtido: $actual)"
  fi
}

assert_absent() {
  path=$1
  description=$2
  if [ ! -e "$path" ] && [ ! -L "$path" ]; then
    pass "$description"
  else
    fail "$description (ainda existe: $path)"
  fi
}

assert_exists() {
  path=$1
  description=$2
  if [ -e "$path" ] || [ -L "$path" ]; then
    pass "$description"
  else
    fail "$description (ausente: $path)"
  fi
}

assert_link_target() {
  link_path=$1
  expected_target=$2
  description=$3
  if [ -L "$link_path" ] && [ "$(readlink "$link_path")" = "$expected_target" ]; then
    pass "$description"
  else
    fail "$description"
  fi
}

find_quarantine() {
  find "$BASE/releases" -mindepth 1 -maxdepth 1 \
    -name ".quarantine-$SHA-*" -print -quit
}

setup_case() {
  case_name=$1
  CASE_DIR="$TEST_ROOT/$case_name"
  BASE="$CASE_DIR/base"
  BIN="$CASE_DIR/bin"
  OLD_RELEASE="$BASE/releases/0000000000000000000000000000000000000000"
  SHA=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
  RELEASE="$BASE/releases/$SHA"
  PACKAGE="$CASE_DIR/kickoff-$SHA.tar.gz"

  mkdir -p "$OLD_RELEASE/public" "$BASE/shared/config" "$BASE/shared/storage" "$BIN"
  printf '<?php echo "old";\n' > "$OLD_RELEASE/public/index.php"
  printf '%s\n' 0000000000000000000000000000000000000000 > "$OLD_RELEASE/.deploy-ready"
  : > "$BASE/shared/config/local.php"
  : > "$BASE/shared/config/crypto.local.php"
  : > "$BASE/shared/config/microsoft.local.php"
  ln -s "$OLD_RELEASE" "$BASE/current"

  cat > "$BIN/curl" <<'STUB'
#!/usr/bin/env bash
set -eu
output=/dev/null
while [ "$#" -gt 0 ]; do
  case "$1" in
    -o) output=$2; shift 2 ;;
    *) shift ;;
  esac
done
printf '%s' "${HEALTH_BODY:-{\"ok\":true}}" > "$output"
printf '%s' "${HEALTH_STATUS:-200}"
STUB
  chmod +x "$BIN/curl"
}

create_package() {
  valid=${1:-yes}
  payload="$CASE_DIR/payload"
  rm -rf -- "$payload"
  mkdir -p "$payload"
  if [ "$valid" = yes ]; then
    mkdir -p "$payload/public"
    printf '<?php echo "new";\n' > "$payload/public/index.php"
  else
    printf 'pacote incompleto\n' > "$payload/README.invalid"
  fi
  tar -czf "$PACKAGE" -C "$payload" .
}

create_link_or_special_package() {
  entry_type=$1
  payload="$CASE_DIR/payload"
  rm -rf -- "$payload"
  mkdir -p "$payload/public"
  printf '<?php echo "new";\n' > "$payload/public/index.php"

  case "$entry_type" in
    symlink) ln -s ../../outside "$payload/public/dangerous-link" ;;
    hardlink) ln "$payload/public/index.php" "$payload/public/dangerous-hardlink" ;;
    fifo) mkfifo "$payload/public/dangerous-fifo" ;;
    *) printf 'tipo de pacote desconhecido: %s\n' "$entry_type" >&2; return 1 ;;
  esac

  tar -czf "$PACKAGE" -C "$payload" .
}

create_path_package() {
  entry_type=$1
  python3 - "$PACKAGE" "$entry_type" <<'PY'
import io
import sys
import tarfile

package, entry_type = sys.argv[1:]
with tarfile.open(package, "w:gz") as archive:
    index = b'<?php echo "new";\n'
    index_info = tarfile.TarInfo("public/index.php")
    index_info.size = len(index)
    archive.addfile(index_info, io.BytesIO(index))

    names = {
        "traversal": "../outside",
        "absolute": "/tmp/kickoff-outside",
        "control": ".deploy-ready",
    }
    payload = b"dangerous\n"
    info = tarfile.TarInfo(names[entry_type])
    info.size = len(payload)
    archive.addfile(info, io.BytesIO(payload))
PY
}

reset_current_to_old() {
  rm -f -- "$BASE/current"
  ln -s "$OLD_RELEASE" "$BASE/current"
}

run_deploy() {
  PATH="$BIN:$PATH" \
  HEALTH_STATUS="${HEALTH_STATUS:-200}" \
  HEALTH_BODY="${HEALTH_BODY:-{\"ok\":true}}" \
  DEPLOY_BASE="$BASE" \
  DEPLOY_PACKAGE="$PACKAGE" \
  DEPLOY_HEALTH_URL=http://health.invalid/api/v1/health \
  DEPLOY_HEALTH_ATTEMPTS=1 \
  DEPLOY_HEALTH_DELAY=0 \
  DEPLOY_HEALTH_TIMEOUT=1 \
  bash "$DEPLOY_SCRIPT" "$SHA"
}

test_failure_before_activation() {
  setup_case before_activation
  create_package no

  if run_deploy >/dev/null 2>&1; then
    fail 'falha antes da ativacao retorna erro'
  else
    pass 'falha antes da ativacao retorna erro'
  fi
  assert_eq "$OLD_RELEASE" "$(readlink -f "$BASE/current")" 'falha previa preserva current'
  assert_absent "$RELEASE" 'falha previa remove release incompleta'
  assert_absent "$PACKAGE" 'falha previa remove pacote temporario'
}

test_failure_after_activation() {
  setup_case after_activation
  create_package yes

  HEALTH_STATUS=500 HEALTH_BODY='{"ok":false}' run_deploy >/dev/null 2>&1 && status=0 || status=$?
  if [ "$status" -ne 0 ]; then
    pass 'falha de health check retorna erro'
  else
    fail 'falha de health check retorna erro'
  fi
  assert_eq "$OLD_RELEASE" "$(readlink -f "$BASE/current")" 'health check falho restaura release anterior'
  assert_exists "$RELEASE" 'rollback preserva release nova completa para diagnostico/retry'
  assert_exists "$RELEASE/.deploy-manifest.sha256" 'rollback preserva manifesto da release'
  assert_absent "$PACKAGE" 'rollback remove pacote temporario'

  create_package yes
  run_deploy >/dev/null
  assert_eq "$RELEASE" "$(readlink -f "$BASE/current")" 'retry reutiliza release completa preservada'
}

test_success_and_idempotent_retry() {
  setup_case success
  create_package yes

  run_deploy >/dev/null
  assert_eq "$RELEASE" "$(readlink -f "$BASE/current")" 'sucesso ativa release nova'
  assert_eq "$SHA" "$(cat "$RELEASE/.deploy-ready")" 'sucesso grava marcador da release'
  assert_exists "$RELEASE/.deploy-manifest.sha256" 'sucesso grava manifesto de integridade'
  assert_absent "$PACKAGE" 'sucesso remove pacote temporario'

  create_package yes
  run_deploy >/dev/null
  assert_eq "$RELEASE" "$(readlink -f "$BASE/current")" 'retry do mesmo SHA e idempotente'
  assert_absent "$PACKAGE" 'retry remove novo pacote temporario'
}

test_retry_replaces_partial_release() {
  setup_case retry_partial
  mkdir -p "$RELEASE"
  printf 'parcial\n' > "$RELEASE/incomplete"
  create_package yes

  run_deploy >/dev/null
  assert_eq "$RELEASE" "$(readlink -f "$BASE/current")" 'retry substitui release parcial e ativa SHA'
  quarantine=$(find_quarantine)
  assert_exists "$quarantine/incomplete" 'retry preserva release parcial em quarentena'
  assert_absent "$RELEASE/incomplete" 'nova release nao contem arquivo parcial'
}

test_active_invalid_release_is_never_removed() {
  setup_case active_invalid
  mkdir -p "$RELEASE/public"
  printf 'nao remover\n' > "$RELEASE/incomplete"
  rm -f -- "$BASE/current"
  ln -s "$RELEASE" "$BASE/current"
  create_package yes

  if run_deploy >/dev/null 2>&1; then
    fail 'release ativa invalida bloqueia deploy'
  else
    pass 'release ativa invalida bloqueia deploy'
  fi
  assert_exists "$RELEASE/incomplete" 'release ativa invalida nao e removida'
  assert_eq "$RELEASE" "$(readlink -f "$BASE/current")" 'current ativo permanece inalterado'
  assert_absent "$PACKAGE" 'falha segura remove somente pacote temporario'
}

test_active_release_alias_is_never_removed() {
  setup_case active_alias
  ln -s "$OLD_RELEASE" "$RELEASE"
  create_package yes

  if run_deploy >/dev/null 2>&1; then
    fail 'alias da release ativa bloqueia deploy'
  else
    pass 'alias da release ativa bloqueia deploy'
  fi
  assert_exists "$RELEASE" 'symlink que referencia release ativa nao e removido'
  assert_eq "$OLD_RELEASE" "$(readlink -f "$BASE/current")" 'alias nao altera current'
}

test_dangerous_archive_is_rejected() {
  entry_type=$1
  setup_case "archive_$entry_type"
  case "$entry_type" in
    symlink|hardlink|fifo) create_link_or_special_package "$entry_type" ;;
    traversal|absolute|control) create_path_package "$entry_type" ;;
  esac

  if run_deploy >/dev/null 2>&1; then
    fail "pacote perigoso $entry_type e rejeitado"
  else
    pass "pacote perigoso $entry_type e rejeitado"
  fi
  assert_eq "$OLD_RELEASE" "$(readlink -f "$BASE/current")" "pacote $entry_type preserva current"
  assert_absent "$RELEASE" "pacote $entry_type nao cria release final"
  assert_absent "$PACKAGE" "pacote $entry_type e removido da area temporaria"
}

test_invalid_shared_link_is_quarantined() {
  link_name=$1
  setup_case "invalid_${link_name//./_}"
  expected_target="$BASE/shared/config/$link_name"
  create_package yes
  run_deploy >/dev/null
  reset_current_to_old

  rm -f -- "$RELEASE/config/$link_name"
  ln -s "$BASE/shared/config/wrong-target" "$RELEASE/config/$link_name"
  create_package yes
  run_deploy >/dev/null

  quarantine=$(find_quarantine)
  assert_exists "$quarantine" "release com link $link_name invalido e preservada em quarentena"
  assert_link_target "$RELEASE/config/$link_name" "$expected_target" "nova release restaura link $link_name correto"
}

test_tampered_reused_release_is_quarantined() {
  setup_case tampered_inactive
  create_package yes
  run_deploy >/dev/null
  reset_current_to_old
  printf 'alterado\n' > "$RELEASE/public/index.php"
  create_package yes

  run_deploy >/dev/null
  quarantine=$(find_quarantine)
  assert_eq 'alterado' "$(tr -d '\r\n' < "$quarantine/public/index.php")" 'release adulterada e preservada em quarentena'
  assert_eq '<?php echo "new";' "$(tr -d '\r\n' < "$RELEASE/public/index.php")" 'release adulterada e reconstruida do pacote'
}

test_tampered_active_release_is_preserved() {
  setup_case tampered_active
  create_package yes
  run_deploy >/dev/null
  printf 'alterado ativo\n' > "$RELEASE/public/index.php"
  create_package yes

  if run_deploy >/dev/null 2>&1; then
    fail 'release ativa adulterada bloqueia retry'
  else
    pass 'release ativa adulterada bloqueia retry'
  fi
  assert_eq "$RELEASE" "$(readlink -f "$BASE/current")" 'release ativa adulterada continua ativa para recuperacao manual'
  assert_eq 'alterado ativo' "$(tr -d '\r\n' < "$RELEASE/public/index.php")" 'conteudo da release ativa nao e excluido'
  if [ -z "$(find_quarantine)" ]; then
    pass 'release ativa adulterada nao e movida para quarentena'
  else
    fail 'release ativa adulterada nao e movida para quarentena'
  fi
}

test_signal_after_activation_rolls_back() {
  signal=$1
  expected_status=$2
  setup_case "signal_$signal"
  create_package yes

  PATH="$BIN:$PATH" \
  HEALTH_STATUS=200 \
  HEALTH_BODY='{"ok":true}' \
  DEPLOY_BASE="$BASE" \
  DEPLOY_PACKAGE="$PACKAGE" \
  DEPLOY_HEALTH_URL=http://health.invalid/api/v1/health \
  DEPLOY_HEALTH_ATTEMPTS=1 \
  DEPLOY_HEALTH_DELAY=0 \
  DEPLOY_HEALTH_TIMEOUT=1 \
  DEPLOY_TEST_MODE=1 \
  DEPLOY_TEST_PAUSE_AFTER_ACTIVATION=30 \
  bash "$DEPLOY_SCRIPT" "$SHA" >/dev/null 2>&1 &
  deploy_pid=$!

  for _ in $(seq 1 100); do
    if [ "$(readlink -f "$BASE/current")" = "$RELEASE" ]; then
      break
    fi
    sleep 0.05
  done

  kill -"$signal" "$deploy_pid"
  wait "$deploy_pid" && status=0 || status=$?
  assert_eq "$expected_status" "$status" "SIG$signal apos ativacao retorna status esperado"
  assert_eq "$OLD_RELEASE" "$(readlink -f "$BASE/current")" "SIG$signal apos ativacao executa rollback"
  assert_exists "$RELEASE" "SIG$signal preserva release nova completa"
}

if [ ! -f "$DEPLOY_SCRIPT" ]; then
  printf 'not ok - script de deploy ausente: %s\n' "$DEPLOY_SCRIPT" >&2
  exit 1
fi

test_failure_before_activation
test_failure_after_activation
test_success_and_idempotent_retry
test_retry_replaces_partial_release
test_active_invalid_release_is_never_removed
test_active_release_alias_is_never_removed
test_dangerous_archive_is_rejected symlink
test_dangerous_archive_is_rejected hardlink
test_dangerous_archive_is_rejected fifo
test_dangerous_archive_is_rejected traversal
test_dangerous_archive_is_rejected absolute
test_dangerous_archive_is_rejected control
test_invalid_shared_link_is_quarantined crypto.local.php
test_invalid_shared_link_is_quarantined microsoft.local.php
test_tampered_reused_release_is_quarantined
test_tampered_active_release_is_preserved
test_signal_after_activation_rolls_back TERM 143
test_signal_after_activation_rolls_back HUP 129

printf '%s testes passaram; %s falharam.\n' "$PASS" "$FAIL"
test "$FAIL" -eq 0
