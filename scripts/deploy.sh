#!/usr/bin/env bash
# Deploy mensalc.uz to the FastPanel server.
# Builds assets and production vendor locally (the server has no node/composer),
# uploads the app, runs migrations and refreshes caches.
set -euo pipefail

SERVER="${DEPLOY_SERVER:-admin@93.92.112.90}"
SITE_USER="mensalc_uz_usr"
HOME_DIR="/var/www/${SITE_USER}/data"
APP_DIR="${HOME_DIR}/mensalc-app"
DOC_ROOT="${HOME_DIR}/www/mensalc.uz"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "→ Building frontend"
(cd "$ROOT" && npm run build >/dev/null)

echo "→ Staging committed code with production dependencies"
(cd "$ROOT" && git archive HEAD | tar -x -C "$STAGE")
cp -R "$ROOT/public/build" "$STAGE/public/"
(cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet)

echo "→ Uploading"
ssh "$SERVER" "mkdir -p /tmp/mensalc-upload"
rsync -az --delete --exclude '.env' --exclude 'storage/' "$STAGE/" "$SERVER:/tmp/mensalc-upload/app/"

echo "→ Installing on the server"
ssh "$SERVER" bash -s <<REMOTE
set -euo pipefail
sudo rsync -a --delete --exclude '.env' --exclude 'storage/' /tmp/mensalc-upload/app/ ${APP_DIR}/
sudo mkdir -p ${APP_DIR}/storage/{app/public,framework/{cache/data,sessions,views},logs} ${APP_DIR}/bootstrap/cache
sudo rsync -a --exclude 'index.php' --exclude 'storage' ${APP_DIR}/public/ ${DOC_ROOT}/
sudo chown -R ${SITE_USER}:${SITE_USER} ${APP_DIR} ${DOC_ROOT}
sudo rm -rf /tmp/mensalc-upload
cd ${APP_DIR}
sudo -u ${SITE_USER} php artisan down --retry=15 || true
sudo -u ${SITE_USER} php artisan migrate --force
sudo -u ${SITE_USER} php artisan db:seed --force
sudo -u ${SITE_USER} php artisan optimize:clear >/dev/null
sudo -u ${SITE_USER} php artisan optimize
sudo -u ${SITE_USER} php artisan lessons:generate
sudo -u ${SITE_USER} php artisan up
REMOTE

echo "✓ Deployed"
