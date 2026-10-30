#!/usr/bin/env bash
# Application deployment phase. Always executed as www, never as root.
set -Eeuo pipefail
umask 027

APP_DIR="${APP_DIR:-/www/wwwroot/c2me.ovh}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-production}"
PHP_BIN="${PHP_BIN:-/www/server/php/84/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/bin/composer}"
NODE_BIN_DIR="${NODE_BIN_DIR:-/www/server/nodejs/v22.17.1/bin}"
export PATH="$NODE_BIN_DIR:/www/server/php/84/bin:/usr/local/bin:/usr/bin:/bin"
export HOME="${DEPLOY_HOME:-/var/cache/c2me/deploy-home}"
export COMPOSER_HOME="$HOME/.composer"
export npm_config_cache="$HOME/.npm"

cd "$APP_DIR"

log() { printf '[%s] %s\n' "$(date '+%F %T%z')" "$*"; }
artisan() { "$PHP_BIN" artisan "$@"; }

case "${1:-}" in
    preflight)
        [[ "$(id -un)" == "www" ]] || { echo "Ce script doit être exécuté sous www." >&2; exit 1; }
        [[ -d .git && -f .env ]] || { echo "Dépôt Git ou .env absent." >&2; exit 1; }
        [[ -x "$PHP_BIN" && -x "$NODE_BIN_DIR/node" && -x "$NODE_BIN_DIR/npm" ]] || { echo "Runtime PHP/Node introuvable." >&2; exit 1; }
        [[ "$(git branch --show-current)" == "$DEPLOY_BRANCH" ]] || { echo "La branche checkoutée ne correspond pas à DEPLOY_BRANCH." >&2; exit 1; }
        [[ -z "$(git status --porcelain --untracked-files=all)" ]] || { echo "Modifications locales ou fichiers non suivis détectés dans le dépôt." >&2; exit 1; }
        git fetch --prune origin "$DEPLOY_BRANCH" --tags
        git merge-base --is-ancestor HEAD "origin/$DEPLOY_BRANCH" || { echo "La branche locale diverge de origin/$DEPLOY_BRANCH." >&2; exit 1; }
        log "Précontrôles applicatifs validés."
        ;;
    down)
        artisan down --retry=60
        ;;
    update)
        log "Mise à jour du code depuis origin/$DEPLOY_BRANCH."
        git pull --ff-only origin "$DEPLOY_BRANCH"
        DEPLOY_COMMIT="$(git rev-parse --short=12 HEAD)"
        APP_VERSION="$(git describe --tags --exact-match HEAD 2>/dev/null || printf 'commit-%s' "$DEPLOY_COMMIT")"
        log "Commit $DEPLOY_COMMIT — version $APP_VERSION"

        log "Installation des dépendances PHP."
        "$COMPOSER_BIN" install --no-interaction --prefer-dist --optimize-autoloader --no-dev

        log "Installation et compilation frontend."
        "$NODE_BIN_DIR/npm" ci
        "$NODE_BIN_DIR/npm" run build

        if grep -q '^APP_VERSION=' .env; then
            sed -i "s/^APP_VERSION=.*/APP_VERSION=$APP_VERSION/" .env
        else
            printf '\nAPP_VERSION=%s\n' "$APP_VERSION" >> .env
        fi

        log "Migrations et caches Laravel."
        artisan migrate --force
        artisan filament:asset
        artisan optimize:clear
        artisan config:cache
        artisan route:cache
        artisan view:cache
        artisan event:cache
        artisan queue:restart
        log "Mise à jour applicative terminée."
        ;;
    up)
        artisan up
        ;;
    *)
        echo "Usage: $0 {preflight|down|update|up}" >&2
        exit 2
        ;;
esac
