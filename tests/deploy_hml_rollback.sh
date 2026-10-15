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
  assert_absent "$RELEASE" 'rollback remove release nova com falha'
  assert_absent "$PACKAGE" 'rollback remove pacote temporario'
}

test_success_and_idempotent_retry() {
  setup_case success
  create_package yes

  run_deploy >/dev/null
  assert_eq "$RELEASE" "$(readlink -f "$BASE/current")" 'sucesso ativa release nova'
  assert_eq "$SHA" "$(cat "$RELEASE/.deploy-ready")" 'sucesso grava marcador da release'
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
  assert_absent "$RELEASE/incomplete" 'retry remove conteudo parcial anterior'
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
  assert_absent "$RELEASE" "SIG$signal remove release nova"
}

if [ ! -f "$DEPLOY_SCRIPT" ]; then
  printf 'not ok - script de deploy ausente: %s\n' "$DEPLOY_SCRIPT" >&2
  exit 1
fi

test_failure_before_activation
test_failure_after_activation
test_success_and_idempotent_retry
test_retry_replaces_partial_release
test_signal_after_activation_rolls_back TERM 143
test_signal_after_activation_rolls_back HUP 129

printf '%s testes passaram; %s falharam.\n' "$PASS" "$FAIL"
test "$FAIL" -eq 0
