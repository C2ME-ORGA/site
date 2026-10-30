#!/usr/bin/env bash
# Privileged entry point. Install as root:root 0750 in /usr/local/sbin/c2me-deploy.
set -Eeuo pipefail
umask 077

CONFIG="/etc/c2me/deploy.conf"
APP_HELPER="/www/wwwroot/c2me.ovh/scripts/deploy-production-app.sh"
DEFAULT_SUPERVISORCTL="/www/server/panel/pyenv/bin/supervisorctl"
DEFAULT_SUPERVISOR_CONFIG="/etc/supervisor/supervisord.conf"
DEFAULT_PHP_BIN="/www/server/php/84/bin/php"
DEFAULT_NODE_BIN_DIR="/www/server/nodejs/v22.17.1/bin"
DEFAULT_COMPOSER_BIN="/usr/bin/composer"
DEPLOY_HOME="/var/cache/c2me/deploy-home"

[[ $EUID -eq 0 ]] || { echo "Ce script doit être lancé via sudo." >&2; exit 1; }
[[ $# -eq 0 ]] || { echo "Ce script n’accepte aucun argument." >&2; exit 2; }
[[ -f "$CONFIG" && ! -L "$CONFIG" ]] || { echo "Configuration absente ou invalide : $CONFIG" >&2; exit 1; }
[[ "$(stat -c '%U' "$CONFIG")" == "root" ]] || { echo "$CONFIG doit appartenir à root." >&2; exit 1; }
if find "$CONFIG" -maxdepth 0 -perm /022 | grep -q .; then
    echo "$CONFIG ne doit pas être modifiable par group/others." >&2
    exit 1
fi
# shellcheck disable=SC1090
source "$CONFIG"

APP_DIR="${APP_DIR:-/www/wwwroot/c2me.ovh}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-master}"
HEALTH_URL="${HEALTH_URL:-https://c2me.fr/up}"
BACKUP_DIR="${BACKUP_DIR:-/www/backup/c2me/database}"
PHP_BIN="${PHP_BIN:-$DEFAULT_PHP_BIN}"
NODE_BIN_DIR="${NODE_BIN_DIR:-$DEFAULT_NODE_BIN_DIR}"
COMPOSER_BIN="${COMPOSER_BIN:-$DEFAULT_COMPOSER_BIN}"
QUEUE_PROGRAM="${QUEUE_PROGRAM:-c2mequeue}"
REVERB_PROGRAM="${REVERB_PROGRAM:-c2mereverb}"
SUPERVISORCTL="${SUPERVISORCTL:-$DEFAULT_SUPERVISORCTL}"
SUPERVISOR_CONFIG="${SUPERVISOR_CONFIG:-$DEFAULT_SUPERVISOR_CONFIG}"

[[ "$APP_DIR" == "/www/wwwroot/c2me.ovh" ]] || { echo "APP_DIR non autorisé." >&2; exit 1; }
[[ "$QUEUE_PROGRAM" == "c2mequeue" && "$REVERB_PROGRAM" == "c2mereverb" ]] || { echo "Programmes Supervisor non autorisés." >&2; exit 1; }
[[ -x "$SUPERVISORCTL" && -f "$SUPERVISOR_CONFIG" ]] || { echo "Supervisor aaPanel introuvable." >&2; exit 1; }
[[ -x "$PHP_BIN" && -x "$NODE_BIN_DIR/node" && -x "$NODE_BIN_DIR/npm" && -x "$COMPOSER_BIN" ]] || { echo "Runtime applicatif introuvable." >&2; exit 1; }
[[ -f "$APP_HELPER" && ! -L "$APP_HELPER" ]] || { echo "Script applicatif absent ou invalide." >&2; exit 1; }
[[ "$(stat -c '%U' "$APP_DIR")" == "www" ]] || { echo "Le projet doit appartenir à www." >&2; exit 1; }

for cmd in runuser flock mysqldump gzip curl awk; do
    command -v "$cmd" >/dev/null 2>&1 || { echo "Commande manquante : $cmd" >&2; exit 1; }
done

mkdir -p "$BACKUP_DIR" /var/log/c2me /var/cache/c2me
chown root:root "$BACKUP_DIR" /var/log/c2me /var/cache/c2me
chmod 750 "$BACKUP_DIR" /var/log/c2me /var/cache/c2me
install -d -o www -g www -m 700 "$DEPLOY_HOME"
LOG_FILE="/var/log/c2me/deploy-$(date +%Y%m%d-%H%M%S).log"
touch "$LOG_FILE"
chown root:root "$LOG_FILE"
chmod 600 "$LOG_FILE"
exec >>"$LOG_FILE" 2>&1

exec 9>/run/lock/c2me-deploy.lock
flock -n 9 || { echo "Un déploiement C2ME est déjà en cours."; exit 1; }

log() { printf '[%s] %s\n' "$(date '+%F %T%z')" "$*"; }
run_www() {
    runuser -u www -- env \
        APP_DIR="$APP_DIR" DEPLOY_BRANCH="$DEPLOY_BRANCH" \
        PHP_BIN="$PHP_BIN" COMPOSER_BIN="$COMPOSER_BIN" \
        NODE_BIN_DIR="$NODE_BIN_DIR" DEPLOY_HOME="$DEPLOY_HOME" \
        bash "$APP_HELPER" "$1"
}

MAINTENANCE=0
finish() {
    status=$?
    if (( status != 0 )); then
        log "Déploiement interrompu (code $status)."
        if (( MAINTENANCE == 1 )); then
            log "Tentative de sortie du mode maintenance."
            run_www up || log "ERREUR : impossible de désactiver le mode maintenance."
        fi
    fi
    log "Fin du déploiement (code $status)."
    exit "$status"
}
trap finish EXIT

log "Début du déploiement C2ME."
[[ -d "$APP_DIR/.git" && -f "$APP_DIR/.env" ]] || { log "Dépôt ou .env absent."; exit 1; }

run_www preflight

# Lire les paramètres SQL avec Dotenv, sous www, sans afficher les valeurs.
mapfile -t DB_VALUES < <(runuser -u www -- env APP_DIR="$APP_DIR" PHP_BIN="$PHP_BIN" "$PHP_BIN" -r '
chdir(getenv("APP_DIR"));
require "vendor/autoload.php";
$env = Dotenv\Dotenv::parse(file_get_contents(".env"));
foreach (["DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"] as $key) {
    echo base64_encode((string) ($env[$key] ?? "")), PHP_EOL;
}
')
(( ${#DB_VALUES[@]} == 5 )) || { log "Impossible de lire la configuration SQL."; exit 1; }
DB_HOST="$(printf '%s' "${DB_VALUES[0]}" | base64 -d)"
DB_PORT="$(printf '%s' "${DB_VALUES[1]}" | base64 -d)"
DB_DATABASE="$(printf '%s' "${DB_VALUES[2]}" | base64 -d)"
DB_USERNAME="$(printf '%s' "${DB_VALUES[3]}" | base64 -d)"
DB_PASSWORD="$(printf '%s' "${DB_VALUES[4]}" | base64 -d)"
unset DB_VALUES
[[ -n "$DB_DATABASE" && -n "$DB_USERNAME" ]] || { log "Configuration DB incomplète."; exit 1; }
[[ "$DB_DATABASE" =~ ^[A-Za-z0-9_.-]+$ ]] || { log "Nom de base invalide pour le nom de sauvegarde."; exit 1; }

BACKUP_FILE="$BACKUP_DIR/${DB_DATABASE}_$(date +%Y%m%d-%H%M%S).sql.gz"
log "Sauvegarde SQL avant migration : $BACKUP_FILE"
DUMP_ARGS=(--user="$DB_USERNAME" --single-transaction --quick --routines --triggers --events --default-character-set=utf8mb4)
[[ -n "$DB_HOST" ]] && DUMP_ARGS+=(--host="$DB_HOST")
[[ -n "$DB_PORT" ]] && DUMP_ARGS+=(--port="$DB_PORT")
MYSQL_PWD="$DB_PASSWORD" mysqldump "${DUMP_ARGS[@]}" "$DB_DATABASE" | gzip -9 > "$BACKUP_FILE"
unset MYSQL_PWD DB_PASSWORD
gzip -t "$BACKUP_FILE"
chmod 600 "$BACKUP_FILE"
log "Sauvegarde SQL vérifiée."

log "Activation du mode maintenance."
run_www down
MAINTENANCE=1

run_www update

log "Redémarrage des processus Supervisor aaPanel C2ME."
"$SUPERVISORCTL" -c "$SUPERVISOR_CONFIG" restart "$QUEUE_PROGRAM:*"
"$SUPERVISORCTL" -c "$SUPERVISOR_CONFIG" restart "$REVERB_PROGRAM:*"

for program in "$QUEUE_PROGRAM" "$REVERB_PROGRAM"; do
    STATUS="$("$SUPERVISORCTL" -c "$SUPERVISOR_CONFIG" status "$program:*")"
    printf '%s\n' "$STATUS"
    awk 'NF && $2 != "RUNNING" { failed=1 } END { exit (NR == 0 || failed) }' <<< "$STATUS" || { log "Processus $program non RUNNING."; exit 1; }
done

log "Sortie du mode maintenance."
run_www up
MAINTENANCE=0

log "Health check : $HEALTH_URL"
HEALTH_OK=0
for attempt in {1..10}; do
    if curl --fail --silent --show-error --max-time 10 "$HEALTH_URL" >/dev/null; then
        HEALTH_OK=1
        break
    fi
    log "Health check en échec ($attempt/10), nouvelle tentative dans 3 secondes."
    sleep 3
done
(( HEALTH_OK == 1 )) || { log "Health check HTTP en échec."; exit 1; }

find "$BACKUP_DIR" -type f -name '*.sql.gz' -mtime +14 -delete
log "SUCCÈS : déploiement terminé."
