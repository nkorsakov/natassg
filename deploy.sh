#!/usr/bin/env bash
# Production deploy for SkyDesk (no Docker).
# Usage: ./deploy.sh   (or: bash deploy.sh — not sh deploy.sh)
# Optional: BRANCH=main ./deploy.sh
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

echo "==> Deploy from $(pwd) (branch: ${BRANCH})"

if [[ ! -f .env ]]; then
  echo "ERROR: .env not found. Create it before deploying." >&2
  exit 1
fi

if [[ ! -f artisan ]]; then
  echo "ERROR: artisan not found — wrong directory?" >&2
  exit 1
fi

# Safety: never wipe production DB from this script.
# Forbidden: migrate:fresh, migrate:refresh, db:wipe, schema:wipe

bring_up() {
  "$PHP_BIN" artisan up >/dev/null 2>&1 || true
}
trap bring_up EXIT

echo "==> Maintenance mode"
"$PHP_BIN" artisan down --retry=60 --secret="deploying" || true

echo "==> Git pull (ff-only)"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> Composer install"
"$COMPOSER_BIN" install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist

echo "==> Frontend build"
if [[ -f package-lock.json ]]; then
  "$NPM_BIN" ci
else
  "$NPM_BIN" install
fi
"$NPM_BIN" run build

echo "==> Migrate (forward only)"
"$PHP_BIN" artisan migrate --force --no-interaction

echo "==> Storage link"
"$PHP_BIN" artisan storage:link --force >/dev/null 2>&1 \
  || "$PHP_BIN" artisan storage:link >/dev/null 2>&1 \
  || true

echo "==> Rebuild caches"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

echo "==> Restart queue workers"
"$PHP_BIN" artisan queue:restart

echo "==> Bring application up"
"$PHP_BIN" artisan up
trap - EXIT

echo "==> Done."
echo "Reminder: cron should run every minute:"
echo "  * * * * * ${PHP_BIN} ${ROOT}/artisan schedule:run >> /dev/null 2>&1"
