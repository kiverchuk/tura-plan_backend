#!/usr/bin/env bash
# Called over SSH by .github/workflows/deploy.yml after the checkout is current.
# Can also be run by hand from the Laravel root on the server.
# Does not change cron and does not touch .env.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

PHP_BIN="${PHP_BIN:-php}"
if [[ ! -x "$PHP_BIN" ]]; then
  PHP_BIN="$(command -v "$PHP_BIN")"
fi

if [[ -f /usr/local/bin/composer ]]; then
  COMPOSER_PHAR=/usr/local/bin/composer
elif [[ -f /usr/bin/composer ]]; then
  COMPOSER_PHAR=/usr/bin/composer
elif command -v composer >/dev/null 2>&1; then
  COMPOSER_PHAR="$(command -v composer)"
else
  echo "composer not found" >&2
  exit 1
fi

echo "composer install ($PHP_BIN)"
COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" "$COMPOSER_PHAR" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

echo "npm install"
export PATH="$HOME/.local/share/fnm:/usr/local/bin:$PATH"
if command -v fnm >/dev/null 2>&1; then
  eval "$(fnm env)"
  if [[ -n "${NODE_VERSION:-}" ]]; then
    fnm install "$NODE_VERSION"
    fnm use "$NODE_VERSION"
  fi
fi
if ! command -v npm >/dev/null 2>&1; then
  echo "npm not found. Install fnm once and set \"node\" in DEPLOY_TARGETS." >&2
  exit 1
fi
echo "using node $(node -v) / npm $(npm -v)"
if [[ -f package-lock.json ]]; then
  npm ci --no-audit --no-fund
else
  npm install --no-audit --no-fund
fi
npm run build

echo "migrate"
"$PHP_BIN" artisan migrate --force --no-interaction
if [[ ! -e public/storage ]]; then
  "$PHP_BIN" artisan storage:link
fi
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache

OWNER="${OWNER:-}"
QUEUE_SERVICE="${QUEUE_SERVICE:-turaplan-queue}"
if [[ "$(id -u)" -eq 0 && -z "$OWNER" ]]; then
  echo "Set owner in DEPLOY_TARGETS so the queue worker is not root." >&2
  exit 1
fi
RUN_USER="${OWNER:-$(id -un)}"

if [[ -n "$OWNER" && "$(id -u)" -eq 0 ]]; then
  echo "chown $OWNER"
  chown -R "$OWNER:$OWNER" "$APP_DIR"
  chmod -R ug+rwx "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
fi

UNIT="/etc/systemd/system/${QUEUE_SERVICE}.service"
RENDERED="$(mktemp)"
sed \
  -e "s|__APP_DIR__|$APP_DIR|g" \
  -e "s|__PHP_BIN__|$PHP_BIN|g" \
  -e "s|__USER__|$RUN_USER|g" \
  "$APP_DIR/deploy/turaplan-queue.service" > "$RENDERED"
if [[ ! -f "$UNIT" ]] || ! cmp -s "$RENDERED" "$UNIT"; then
  echo "updating $UNIT for $RUN_USER"
  cp "$RENDERED" "$UNIT"
  systemctl daemon-reload
  systemctl enable "$QUEUE_SERVICE"
fi
rm -f "$RENDERED"

echo "restart $QUEUE_SERVICE"
systemctl restart "$QUEUE_SERVICE"
if [[ -n "$OWNER" && "$(id -u)" -eq 0 ]]; then
  sudo -u "$OWNER" -H "$PHP_BIN" artisan queue:restart
else
  "$PHP_BIN" artisan queue:restart
fi

echo "done $(git rev-parse --short HEAD 2>/dev/null || true)"
