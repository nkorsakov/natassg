#!/usr/bin/env bash
# Production deploy for SkyDesk (no Docker).
#
# Usage:
#   ./deploy.sh                 # интерактивный выбор шагов
#   ./deploy.sh --all           # полный деплой (как раньше)
#   ./deploy.sh git migrate     # только выбранные шаги
#   BRANCH=main ./deploy.sh git
#
# Steps: git | composer | frontend | migrate | storage | cache | queue
#
# Re-exec under bash if invoked via sh/dash (pipefail is bash-only).
if [ -z "${BASH_VERSION:-}" ]; then
  exec /usr/bin/env bash "$0" "$@"
fi
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

# Safety: never wipe production DB from this script.
# Forbidden here: migrate:fresh, migrate:refresh, db:wipe, schema:wipe
# Note: a one-off finance wipe lived in migration 2026_08_03_000001 (already applied).
# New deploys only run forward `migrate` — no data wipe in this script.

ALL_STEPS=(git composer frontend migrate storage cache queue)

usage() {
  cat <<EOF
SkyDesk deploy

Usage:
  ./deploy.sh                 Interactive menu
  ./deploy.sh --all           Run all steps
  ./deploy.sh --help
  ./deploy.sh <step> [step…]  Run only listed steps

Steps:
  git        git fetch/checkout/pull (ff-only)
  composer   composer install --no-dev
  frontend   npm ci/install + npm run build
  migrate    php artisan migrate --force  (forward only, no wipe)
  storage    storage:link
  cache      optimize:clear + config/route/view/event cache
  queue      queue:restart

Env:
  BRANCH=${BRANCH}  PHP_BIN  COMPOSER_BIN  NPM_BIN
EOF
}

need_maintenance() {
  local s
  for s in "$@"; do
    case "$s" in
      git|composer|frontend|migrate|cache) return 0 ;;
    esac
  done
  return 1
}

run_git() {
  echo "==> Git pull (ff-only, branch: ${BRANCH})"
  git fetch origin "$BRANCH"
  git checkout "$BRANCH"
  git pull --ff-only origin "$BRANCH"
}

run_composer() {
  echo "==> Composer install"
  "$COMPOSER_BIN" install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist
}

run_frontend() {
  echo "==> Frontend build"
  if [[ -f package-lock.json ]]; then
    "$NPM_BIN" ci
  else
    "$NPM_BIN" install
  fi
  "$NPM_BIN" run build
}

run_migrate() {
  echo "==> Migrate (forward only — no fresh/wipe)"
  "$PHP_BIN" artisan migrate --force --no-interaction
}

run_storage() {
  echo "==> Storage link"
  "$PHP_BIN" artisan storage:link --force >/dev/null 2>&1 \
    || "$PHP_BIN" artisan storage:link >/dev/null 2>&1 \
    || true
}

run_cache() {
  echo "==> Rebuild caches"
  "$PHP_BIN" artisan optimize:clear
  "$PHP_BIN" artisan config:cache
  "$PHP_BIN" artisan route:cache
  "$PHP_BIN" artisan view:cache
  "$PHP_BIN" artisan event:cache
}

run_queue() {
  echo "==> Restart queue workers"
  "$PHP_BIN" artisan queue:restart
}

run_step() {
  case "$1" in
    git) run_git ;;
    composer) run_composer ;;
    frontend) run_frontend ;;
    migrate) run_migrate ;;
    storage) run_storage ;;
    cache) run_cache ;;
    queue) run_queue ;;
    *)
      echo "ERROR: unknown step: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
}

pick_interactive() {
  echo "Что деплоить? Введите номера через пробел (или a = всё)."
  echo
  local i=1
  local s
  for s in "${ALL_STEPS[@]}"; do
    printf "  %d) %s\n" "$i" "$s"
    i=$((i + 1))
  done
  echo "  a) all"
  echo
  local answer
  read -r -p "Выбор: " answer
  if [[ -z "${answer// }" ]]; then
    echo "Ничего не выбрано — выход."
    exit 0
  fi
  if [[ "$answer" == "a" || "$answer" == "A" || "$answer" == "all" ]]; then
    SELECTED=("${ALL_STEPS[@]}")
    return
  fi

  SELECTED=()
  local token n
  for token in $answer; do
    if [[ ! "$token" =~ ^[0-9]+$ ]]; then
      echo "ERROR: ожидались номера шагов, получено: $token" >&2
      exit 1
    fi
    n=$token
    if (( n < 1 || n > ${#ALL_STEPS[@]} )); then
      echo "ERROR: номер вне диапазона: $n" >&2
      exit 1
    fi
    SELECTED+=("${ALL_STEPS[$((n - 1))]}")
  done
}

# --- args ---
SELECTED=()
if [[ $# -eq 0 ]]; then
  pick_interactive
elif [[ "$1" == "--help" || "$1" == "-h" ]]; then
  usage
  exit 0
elif [[ "$1" == "--all" || "$1" == "all" ]]; then
  SELECTED=("${ALL_STEPS[@]}")
else
  SELECTED=("$@")
fi

# validate steps early
for s in "${SELECTED[@]}"; do
  case "$s" in
    git|composer|frontend|migrate|storage|cache|queue) ;;
    *)
      echo "ERROR: unknown step: $s" >&2
      usage >&2
      exit 1
      ;;
  esac
done

echo "==> Deploy from $(pwd)"
echo "==> Steps: ${SELECTED[*]}"

if [[ ! -f .env ]]; then
  echo "ERROR: .env not found. Create it before deploying." >&2
  exit 1
fi

if [[ ! -f artisan ]]; then
  echo "ERROR: artisan not found — wrong directory?" >&2
  exit 1
fi

MAINT=0
bring_up() {
  if [[ "$MAINT" -eq 1 ]]; then
    "$PHP_BIN" artisan up >/dev/null 2>&1 || true
  fi
}
trap bring_up EXIT

if need_maintenance "${SELECTED[@]}"; then
  echo "==> Maintenance mode"
  "$PHP_BIN" artisan down --retry=60 --secret="deploying" || true
  MAINT=1
fi

for s in "${SELECTED[@]}"; do
  run_step "$s"
done

if [[ "$MAINT" -eq 1 ]]; then
  echo "==> Bring application up"
  "$PHP_BIN" artisan up
  MAINT=0
fi
trap - EXIT

echo "==> Done."
echo "Reminder: cron should run every minute:"
echo "  * * * * * ${PHP_BIN} ${ROOT}/artisan schedule:run >> /dev/null 2>&1"
