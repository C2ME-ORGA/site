# Déploiement CI/CD — C2ME

## Architecture

- Les Pull Requests vers `master` exécutent les contrôles PHP, Pest et le build frontend.
- Un push sur `master` déclenche les mêmes contrôles, puis le déploiement SSH uniquement si les contrôles réussissent.
- Le serveur exécute `scripts/deploy-production.sh`.
- Les secrets SSH restent dans les GitHub Actions Secrets ; aucun secret de production ne doit être commité.

## Pré-requis serveur aaPanel

Le script suppose le chemin actuel `/www/wwwroot/c2me.ovh` et un déploiement dans ce répertoire (pas encore un système de releases/symlinks).

Installer/valider sur le serveur :
- PHP CLI compatible avec le projet (PHP 8.4 recommandé ici), extensions Laravel nécessaires.
- Composer 2.
- Node correspondant à `.nvmrc` et npm.
- Git, curl, gzip, mysqldump (client MariaDB/MySQL).
- Supervisor et `supervisorctl`.
- Un compte SSH de déploiement dédié, avec accès au dépôt et droits limités au projet, au répertoire de sauvegarde et aux commandes nécessaires.

Le Document Root aaPanel doit rester `/www/wwwroot/c2me.ovh/public`.

## Configuration serveur

Créer `/etc/c2me/deploy.conf` :

```bash
APP_DIR="/www/wwwroot/c2me.ovh"
DEPLOY_BRANCH="master"
HEALTH_URL="https://c2me.fr/up"
BACKUP_DIR="/www/backup/c2me/database"
SUPERVISOR_PROGRAM="laravel-worker:*"
RELEASE_LOG_DIR="/www/wwwroot/c2me.ovh/storage/logs/deploy"
```

Adapter impérativement `SUPERVISOR_PROGRAM` au nom exact de la section `[program:...]` réellement configurée dans Supervisor. Vérifier avec `sudo supervisorctl status`.

Protéger le fichier :

```bash
sudo chown root:root /etc/c2me/deploy.conf
sudo chmod 640 /etc/c2me/deploy.conf
```

Le compte SSH de déploiement doit pouvoir lire ce fichier.

Le compte doit aussi pouvoir écrire dans le dépôt, `storage/`, `bootstrap/cache/`, créer les sauvegardes SQL et accéder au serveur MySQL avec les identifiants de `.env`. Ne pas donner un accès sudo général au compte de déploiement.

## Secrets et variables GitHub

Dans Settings > Secrets and variables > Actions :

**Secrets (idéalement dans l'environnement GitHub `production`)**
- `DEPLOY_HOST` : DNS ou IP du serveur.
- `DEPLOY_USER` : compte SSH dédié.
- `DEPLOY_PORT` : port SSH (facultatif, défaut 22).
- `DEPLOY_SSH_KEY` : clé privée SSH dédiée.
- `DEPLOY_KNOWN_HOSTS` : clé d'hôte SSH vérifiée hors bande.

**Environment variable**
- `DEPLOY_PATH` : `/www/wwwroot/c2me.ovh`.

Créer un environnement GitHub nommé `production`, avec approbation manuelle si souhaitée.

## Sauvegardes SQL

Le script exécute `mysqldump --single-transaction`, compresse le dump et vérifie l'archive avec `gzip -t`. Les sauvegardes locales sont conservées 14 jours dans `/www/backup/c2me/database`.

Cette sauvegarde locale ne suffit pas : prévoir une copie chiffrée hors du serveur, avec politique de rétention et tests réguliers de restauration. Les fichiers utilisateurs de `storage/app` doivent également être sauvegardés séparément : le dump SQL ne contient pas les pièces jointes et documents du CRM.

## Vérifications post-déploiement

- Le endpoint Laravel `/up` doit répondre HTTP 2xx sans authentification.
- Le worker Supervisor doit être RUNNING.
- Les logs du déploiement sont écrits dans `storage/logs/deploy/`.
- Laravel conserve ses logs applicatifs dans `storage/logs/`.

Le scheduler Laravel doit avoir un cron système actif :

```cron
* * * * * cd /www/wwwroot/c2me.ovh && php artisan schedule:run >> /dev/null 2>&1
```

Vérifier également le cron configuré dans aaPanel : ne pas installer un doublon.

## Déroulement et limites

Le script sauvegarde avant migration, installe les dépendances, compile les assets, active le mode maintenance, migre, reconstruit les caches, redémarre logiquement les workers, vérifie Supervisor et l'endpoint HTTP.

Le déploiement est actuellement « in-place » : le rollback applicatif n'est pas automatique. Une restauration SQL doit être décidée explicitement et coordonnée avec la version du code. Ne pas restaurer automatiquement une base après une migration échouée sans diagnostic.

Avant activation en production :
1. Vérifier le nom du worker Supervisor.
2. Tester une sauvegarde et une restauration SQL sur une base de test.
3. Vérifier les permissions du compte SSH.
4. Vérifier le Document Root aaPanel.
5. Ajouter les secrets GitHub.
6. Lancer un premier déploiement contrôlé en fenêtre de maintenance.

## Dépannage

- Logs déploiement : `storage/logs/deploy/`.
- État workers : `sudo supervisorctl status`.
- Scheduler : `php artisan schedule:list`.
- Santé HTTP : `curl -i https://c2me.fr/up`.
- Sauvegardes : `ls -lh /www/backup/c2me/database`.
