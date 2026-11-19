#!/usr/bin/env bash
# Deploy the CRM to crm.mensalc.uz and the landing page to mensalc.uz (FastPanel server).
# Builds assets and production vendor locally (the server has no node/composer),
# uploads, runs migrations and refreshes caches.
set -euo pipefail

SERVER="${DEPLOY_SERVER:-admin@93.92.112.90}"
SITE_USER="crm_mensalc__usr"
HOME_DIR="/var/www/${SITE_USER}/data"
APP_DIR="${HOME_DIR}/mensalc-app"
DOC_ROOT="${HOME_DIR}/www/crm.mensalc.uz"
LANDING_USER="mensalc_uz_usr"
LANDING_ROOT="/var/www/${LANDING_USER}/data/www/mensalc.uz"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "→ Building frontend"
(cd "$ROOT" && npm run build >/dev/null 2>&1)

echo "→ Staging committed code with production dependencies"
(cd "$ROOT" && git archive HEAD | tar -x -C "$STAGE")
cp -R "$ROOT/public/build" "$STAGE/public/"
# The APK is not in git; ship the one built by scripts/release-android.sh.
if ls "$ROOT"/public/downloads/*.apk >/dev/null 2>&1; then mkdir -p "$STAGE/public/downloads" && cp "$ROOT"/public/downloads/*.apk "$STAGE/public/downloads/"; fi
(cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet)
# The docroot is a copy of public/ whose index.php points at the app directory.
sed -e "s#__DIR__.'/../#'${APP_DIR}/#g" -e "s#^\$app->handleRequest#\$app->usePublicPath(__DIR__);\n\n\$app->handleRequest#" \
    "$STAGE/public/index.php" > "$STAGE/docroot-index.php"

echo "→ Uploading"
ssh "$SERVER" "mkdir -p /tmp/mensalc-upload"
rsync -az --delete --exclude '.env' --exclude 'storage/' "$STAGE/" "$SERVER:/tmp/mensalc-upload/app/"
rsync -az --delete "$ROOT/landing/" "$SERVER:/tmp/mensalc-upload/landing/"

echo "→ Installing on the server"
ssh "$SERVER" bash -s <<REMOTE
set -euo pipefail
artisan() { sudo -u ${SITE_USER} -H bash -c "cd ${APP_DIR} && php artisan \$*"; }
# Maintenance page while files change; files land with the site owner right away, so PHP never sees unreadable files.
artisan down --retry=15 || true
sudo rsync -a --delete --chown=${SITE_USER}:${SITE_USER} --exclude '.env' --exclude 'storage/' --exclude 'docroot-index.php' /tmp/mensalc-upload/app/ ${APP_DIR}/
sudo mkdir -p ${APP_DIR}/storage/{app/public,framework/{cache/data,sessions,views},logs} ${APP_DIR}/bootstrap/cache
sudo rsync -a --chown=${SITE_USER}:${SITE_USER} --exclude 'index.php' --exclude 'storage' ${APP_DIR}/public/ ${DOC_ROOT}/
sudo install -m 644 /tmp/mensalc-upload/app/docroot-index.php ${DOC_ROOT}/index.php
sudo ln -sfn ${APP_DIR}/storage/app/public ${DOC_ROOT}/storage
sudo chown -h ${SITE_USER}:${SITE_USER} ${DOC_ROOT}/storage
sudo chown ${SITE_USER}:${SITE_USER} ${DOC_ROOT}/index.php
sudo chown -R ${SITE_USER}:${SITE_USER} ${APP_DIR}/storage ${APP_DIR}/bootstrap/cache
artisan migrate --force
artisan db:seed --force
artisan optimize:clear >/dev/null
artisan optimize
# Back online as soon as code, database and caches are ready (users see "updating" for these seconds only).
artisan up
sudo rsync -a --delete /tmp/mensalc-upload/landing/ ${LANDING_ROOT}/
sudo chown -R ${LANDING_USER}:${LANDING_USER} ${LANDING_ROOT}
sudo rm -rf /tmp/mensalc-upload
artisan lessons:generate
REMOTE

echo "✓ Deployed: https://crm.mensalc.uz and https://mensalc.uz"
