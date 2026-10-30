#!/usr/bin/env bash
#
# Déploiement de production C2ME — exécuté sur le serveur aaPanel.
# Configuration facultative : /etc/c2me/deploy.conf (shell, root/admin contrôlé).
#
set -Eeuo pipefail
umask 027

APP_DIR="${APP_DIR:-/www/wwwroot/c2me.ovh}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-master}"
HEALTH_URL="${HEALTH_URL:-https://c2me.fr/up}"
BACKUP_DIR="${BACKUP_DIR:-/www/backup/c2me/database}"
SUPERVISOR_PROGRAM="${SUPERVISOR_PROGRAM:-}"
RELEASE_LOG_DIR="${RELEASE_LOG_DIR:-$APP_DIR/storage/logs/deploy}"

if [[ -f /etc/c2me/deploy.conf ]]; then
    # Ce fichier doit être administré par root et non modifiable par le compte web.
    # shellcheck disable=SC1091
    source /etc/c2me/deploy.conf
fi

mkdir -p "$BACKUP_DIR" "$RELEASE_LOG_DIR"
chmod 750 "$BACKUP_DIR" "$RELEASE_LOG_DIR"
LOG_FILE="$RELEASE_LOG_DIR/deploy-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG_FILE") 2>&1

MAINTENANCE_ENABLED=0
DEPLOY_OK=0

log() { printf '[%s] %s\n' "$(date '+%F %T%z')" "$*"; }
die() { log "ERREUR: $*"; exit 1; }

finish() {
    local status=$?
    if (( status != 0 )); then
        log "Déploiement interrompu (code $status)."
        if (( MAINTENANCE_ENABLED == 1 )); then
            log "Tentative de sortie du mode maintenance."
            (cd "$APP_DIR" && php artisan up) || log "ATTENTION: impossible de désactiver le mode maintenance."
        fi
    fi
    log "Fin du script (code $status)."
    exit "$status"
}
trap finish EXIT

require_command() { command -v "$1" >/dev/null 2>&1 || die "Commande manquante : $1"; }

for command in git php composer npm curl gzip mysqldump supervisorctl; do
    require_command "$command"
done

[[ -d "$APP_DIR/.git" ]] || die "Dépôt Git introuvable dans $APP_DIR"
[[ -f "$APP_DIR/.env" ]] || die "Fichier .env absent : déploiement refusé"
[[ -n "$SUPERVISOR_PROGRAM" ]] || die "SUPERVISOR_PROGRAM doit être défini dans /etc/c2me/deploy.conf"

cd "$APP_DIR"
log "Déploiement C2ME depuis origin/$DEPLOY_BRANCH"
log "Répertoire : $APP_DIR"

if ! git diff --quiet || ! git diff --cached --quiet; then
    die "Le dépôt contient des modifications Git locales. Nettoyer avant de déployer."
fi

git fetch --prune origin "$DEPLOY_BRANCH" --tags
git merge-base --is-ancestor HEAD "origin/$DEPLOY_BRANCH" || die "La branche locale diverge de origin/$DEPLOY_BRANCH"


# Lecture sûre des paramètres DB depuis .env avec le parseur PHP Dotenv.
mapfile -t DB_VALUES < <(php -r '
require "vendor/autoload.php";
$env = Dotenv\Dotenv::parse(file_get_contents(".env"));
foreach (["DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"] as $key) {
    echo base64_encode((string) ($env[$key] ?? "")), PHP_EOL;
}
')
(( ${#DB_VALUES[@]} == 5 )) || die "Impossible de lire la configuration SQL."
DB_HOST="$(printf '%s' "${DB_VALUES[0]}" | base64 -d)"
DB_PORT="$(printf '%s' "${DB_VALUES[1]}" | base64 -d)"
DB_DATABASE="$(printf '%s' "${DB_VALUES[2]}" | base64 -d)"
DB_USERNAME="$(printf '%s' "${DB_VALUES[3]}" | base64 -d)"
DB_PASSWORD="$(printf '%s' "${DB_VALUES[4]}" | base64 -d)"
[[ -n "$DB_DATABASE" && -n "$DB_USERNAME" ]] || die "Configuration SQL incomplète."
unset DB_VALUES

# Sauvegarde avant toute migration. Le dump est vérifié avant de poursuivre.
BACKUP_FILE="$BACKUP_DIR/${DB_DATABASE}_$(date +%Y%m%d-%H%M%S)_${DEPLOY_COMMIT}.sql.gz"
log "Sauvegarde SQL : $BACKUP_FILE"
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
    --single-transaction --quick --routines --triggers --events \
    --default-character-set=utf8mb4 "$DB_DATABASE" | gzip -9 > "$BACKUP_FILE"
unset MYSQL_PWD DB_PASSWORD
gzip -t "$BACKUP_FILE" || die "La sauvegarde SQL est invalide."
chmod 640 "$BACKUP_FILE"
log "Sauvegarde SQL vérifiée."

# Le répertoire est servi directement par aaPanel : toute modification du code
# doit donc être faite sous maintenance pour éviter un mélange de fichiers.
log "Activation du mode maintenance."
php artisan down --retry=60
MAINTENANCE_ENABLED=1

log "Mise à jour du code depuis origin/$DEPLOY_BRANCH."
git pull --ff-only origin "$DEPLOY_BRANCH"
DEPLOY_COMMIT="$(git rev-parse --short=12 HEAD)"
APP_VERSION="$(git describe --tags --exact-match HEAD 2>/dev/null || git describe --tags --abbrev=0 2>/dev/null || printf 'commit-%s' "$DEPLOY_COMMIT")"
log "Commit : $DEPLOY_COMMIT — version : $APP_VERSION"

log "Installation des dépendances PHP."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

log "Installation stricte des dépendances JavaScript."
npm ci
log "Compilation des assets."
npm run build

# Mise à jour contrôlée de APP_VERSION sans écraser les autres variables.
if grep -q '^APP_VERSION=' .env; then
    sed -i "s/^APP_VERSION=.*/APP_VERSION=$APP_VERSION/" .env
else
    printf '\nAPP_VERSION=%s\n' "$APP_VERSION" >> .env
fi

log "Exécution des migrations."
php artisan migrate --force

log "Publication des assets Filament et reconstruction des caches."
php artisan filament:asset
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

log "Demande de redémarrage des workers Laravel."
php artisan queue:restart

log "Vérification des workers Supervisor : $SUPERVISOR_PROGRAM"
WORKER_STATUS="$(supervisorctl status "$SUPERVISOR_PROGRAM")"
printf '%s\n' "$WORKER_STATUS"
grep -q 'RUNNING' <<< "$WORKER_STATUS" || die "Aucun worker Supervisor RUNNING pour $SUPERVISOR_PROGRAM."

log "Sortie du mode maintenance."
php artisan up
MAINTENANCE_ENABLED=0

log "Health check HTTP : $HEALTH_URL"
HEALTH_OK=0
for attempt in {1..10}; do
    if curl --fail --silent --show-error --max-time 10 "$HEALTH_URL" >/dev/null; then
        HEALTH_OK=1
        break
    fi
    log "Health check en échec ($attempt/10), nouvelle tentative dans 3 secondes."
    sleep 3
done
(( HEALTH_OK == 1 )) || die "Health check HTTP en échec après 10 tentatives."

# Conservation des sauvegardes SQL pendant 14 jours (adapter selon la politique de sauvegarde).
find "$BACKUP_DIR" -type f -name '*.sql.gz' -mtime +14 -delete

DEPLOY_OK=1
log "SUCCÈS : version $APP_VERSION ($DEPLOY_COMMIT) déployée."
