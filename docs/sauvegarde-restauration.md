# Sauvegarde et restauration

Ce document décrit la sauvegarde quotidienne de la base de données et la
sauvegarde des fichiers, puis la procédure de restauration. Il sert de runbook
en cas d'incident : chaque commande est prévue pour un serveur Linux
(VPS ou dédié) disposant de PHP, `mysqldump`, `tar` et `zip`.

## Ce qui est sauvegardé

| Donnée | Emplacement | Moyen | Fréquence |
| --- | --- | --- | --- |
| Base MySQL | serveur MySQL | `spatie/laravel-backup` | quotidienne (01:30) |
| Fichiers privés | `storage/app/private` | `tar` + `gzip` | quotidienne (cron système) |
| Images publiques | `storage/app/public` | `tar` + `gzip` | quotidienne (cron système) |
| Code, `.env`, `APP_KEY` | dépôt Git / coffre-fort | hors archive | à la demande |

L'archive de base ne contient **que** le dump SQL. Les fichiers sont
volontairement séparés : ils représentent un volume bien plus important, se
restaurent indépendamment et n'ont pas la même rétention.

Ne sont jamais sauvegardés dans l'archive applicative :

- `vendor/` et `node_modules/` (réinstallables) ;
- `storage/framework/` (cache, sessions) ;
- `.env` (secrets : à conserver dans un coffre-fort, jamais dans une archive
  déposée sur un serveur distant) ;
- `storage/app/backups/` et `storage/app/backup-temp/` (l'archive ne doit pas
  se contenir elle-même).

## Prérequis sur le serveur

```bash
mysqldump --version    # paquet mysql-client ou default-mysql-client
php -m | grep -i zip   # extension ZIP de PHP
tar --version
gzip --version
unzip -v               # restauration des archives chiffrées
```

Le planificateur Laravel doit tourner toutes les minutes :

```cron
* * * * * cd /var/www/c2me && php artisan schedule:run >> /dev/null 2>&1
```

Les tâches sont déclarées dans `routes/console.php` :

| Heure | Commande | Rôle |
| --- | --- | --- |
| 01:15 | `backup:clean` | applique la rétention avant de créer l'archive du jour |
| 01:30 | `backup:run --only-db` | crée `c2me-AAAA-MM-JJ-HH-MM-SS.zip` |
| 02:00 | `backup:monitor` | alerte si la dernière archive a plus de 2 jours |

`BACKUP_ENABLED=false` suspend les trois tâches sans toucher au cron.

## Configuration

Variables `.env` (voir `.env.example`) :

| Variable | Rôle | Défaut |
| --- | --- | --- |
| `BACKUP_ENABLED` | active les tâches planifiées | `true` |
| `BACKUP_DISK` | disque de destination (`config/filesystems.php`) | `backups` |
| `BACKUP_ARCHIVE_PASSWORD` | chiffrement AES du ZIP | vide (non chiffré) |
| `BACKUP_NOTIFICATION_EMAIL` | destinataire des alertes d'échec | `ADMIN_EMAIL` |
| `DB_DUMP_BINARY_PATH` | chemin vers `mysqldump` (utile en local) | vide (PATH) |

Le disque `backups` pointe sur `storage/app/backups` (les archives sont
rangées dans le sous-dossier `c2me/`), hors du dossier public : aucune archive
n'est téléchargeable depuis le web.

### Chiffrement : obligatoire en production

```dotenv
BACKUP_ARCHIVE_PASSWORD="mot-de-passe-de-32-caracteres-minimum"
```

L'archive contient des données personnelles (comptes, contacts, e-mails,
journal d'audit). Sans mot de passe, `encryption` passe automatiquement à
`none` : pratique en local, **interdit en production**.

Conservez le mot de passe dans un gestionnaire de secrets, séparément des
archives. Un mot de passe perdu rend les archives définitivement illisibles.

## Rétention

| Période | Conservation |
| --- | --- |
| 7 derniers jours | toutes les archives |
| 30 jours | une archive par jour |
| 12 semaines | une archive par semaine |
| 6 mois | une archive par mois |
| 2 ans | une archive par an |

Garde-fou : au-delà de 2 Go, les archives les plus anciennes sont supprimées
en priorité. La rétention est définie dans `config/backup.php`.

## Commandes courantes

```bash
# Créer une archive immédiate (base uniquement)
php artisan backup:run --only-db

# Vérifier le contenu du disque de sauvegarde
php artisan backup:list

# Contrôler la santé (âge et volume)
php artisan backup:monitor

# Appliquer la rétention manuellement
php artisan backup:clean

# Vérifier la planification
php artisan schedule:list
```

Les archives sont écrites dans `storage/app/backups/c2me/` :

```text
c2me-2026-10-31-01-30-00.zip
└── db-dumps/mysql-c2me.sql   # dump complet (DROP TABLE puis CREATE)
```

## Sauvegarde des fichiers

À ajouter dans le cron système (par exemple à 01:45), ou à lancer avant une
opération risquée :

```bash
cd /var/www/c2me

tar --create --gzip \
  --file="/var/backups/c2me/files-$(date +%F-%H%M).tar.gz" \
  --exclude='app/backups' \
  --exclude='app/backup-temp' \
  -C storage app/private app/public

sha256sum /var/backups/c2me/files-*.tar.gz > /var/backups/c2me/files.sha256
```

Contenu attendu de `storage/app/private` :

- `crm-activity-attachments/` : photos du mode terrain ;
- `quote-requests/` : plans joints aux demandes de devis ;
- `quotes/` : PDF de devis générés ;
- `mail-attachments/` : pièces jointes des e-mails entrants et sortants.

`storage/app/public` contient les images insérées dans les signatures e-mail.

### Copie hors site

Une sauvegarde sur le même serveur ne protège ni d'une panne matérielle ni
d'un rançongiciel. Copiez chaque nuit l'ensemble des archives vers un
emplacement distant :

```bash
rclone copy /var/backups/c2me remote:c2me-backups --checksum
# ou
scp /var/backups/c2me/*.zip backup@autre-serveur:/srv/backups/c2me/
```

Objectif 3-2-1 : trois copies, deux supports, une hors site. Vérifiez au
moins une fois par trimestre qu'une archive distante se restaure.

## Restauration

### 1. Se mettre en maintenance et sécuriser l'état actuel

```bash
cd /var/www/c2me
php artisan down --retry=60

# Toujours sauvegarder l'état courant avant d'écraser quoi que ce soit.
php artisan backup:run --only-db
```

### 2. Vérifier l'archive

```bash
cd /var/backups/c2me
sha256sum -c files.sha256
unzip -t -P "$BACKUP_ARCHIVE_PASSWORD" c2me-2026-10-31-01-30-00.zip   # intégrité
```

### 3. Décompresser

```bash
mkdir -p /tmp/c2me-restore
unzip -P "$BACKUP_ARCHIVE_PASSWORD" \
  c2me-2026-10-31-01-30-00.zip -d /tmp/c2me-restore
```

`unzip -P` (Info-ZIP) et `7z x -p…` lisent le chiffrement ZIP produit par
PHP. L'outil « Archive Utility » de macOS ne le prend pas en charge.

### 4. Restaurer la base

Le dump contient `DROP TABLE IF EXISTS` : l'import écrase les tables
existantes de la base ciblée.

```bash
mysql -h 127.0.0.1 -u c2me -p c2me < /tmp/c2me-restore/db-dumps/mysql-c2me.sql
```

Pour tester sans toucher à la production, importez dans une base jetable :

```bash
mysql -h 127.0.0.1 -u root -p -e \
  "CREATE DATABASE c2me_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -h 127.0.0.1 -u root -p c2me_restore < /tmp/c2me-restore/db-dumps/mysql-c2me.sql
```

### 5. Restaurer les fichiers

```bash
tar -xzf /var/backups/c2me/files-2026-10-31-0145.tar.gz -C /var/www/c2me/storage
chown -R www-data:www-data /var/www/c2me/storage/app
```

### 6. Finaliser

```bash
cd /var/www/c2me
php artisan optimize:clear
php artisan storage:link
php artisan migrate --force   # si le code est plus récent que la sauvegarde
php artisan up
```

`APP_KEY` et `.env` ne sont pas dans les archives : ils doivent déjà être en
place. Changer `APP_KEY` invaliderait les données chiffrées et les chemins
pseudonymes des images de signature.

### 7. Vérifier

- connexion au back-office avec un compte connu ;
- nombre de comptes, contacts et devis cohérent avec la veille ;
- ouverture d'une photo d'activité et d'un plan de demande de devis ;
- téléchargement d'un PDF de devis ;
- flux calendrier `GET /calendrier/{token}.ics` ;
- page `/terrain` : snapshot et activités hors ligne ;
- `php artisan backup:monitor` : aucune alerte.

## Exercice de restauration

À réaliser une fois par trimestre, sur une machine ou une base dédiée :

1. récupérer la dernière archive distante et son mot de passe ;
2. restaurer dans une base `c2me_restore` et un répertoire `storage` isolé ;
3. pointer un `.env` de test vers cette base et ce stockage ;
4. dérouler la liste de vérification ci-dessus ;
5. noter la durée réelle (objectif : moins d'une heure) et corriger la
   procédure si une étape a échoué.

## Dépannage

| Symptôme | Cause probable | Solution |
| --- | --- | --- |
| `mysqldump: command not found` | client MySQL absent | installer `mysql-client` / `default-mysql-client` |
| `The dump process failed with exitcode 2` | identifiants ou socket | vérifier `DB_*` et `DB_SOCKET` |
| `Disk quota exceeded` | disque plein | lancer `backup:clean`, augmenter le volume |
| `mysqldump: Error 2020` à la restauration | `max_allowed_packet` trop bas | augmenter la valeur côté serveur MySQL |
| Archive impossible à ouvrir | mot de passe erroné | vérifier `BACKUP_ARCHIVE_PASSWORD` |
| `backup:monitor` alerte « unhealthy » | archive trop ancienne | vérifier le cron et `storage/logs/laravel.log` |
| Aucune alerte reçue en cas d'échec | mail non configuré | tester `MAIL_*`, consulter le log |

### Développement local (Windows / Docker)

En local, MySQL tourne dans un conteneur Docker et `mysqldump` n'est pas
forcé sur l'hôte. Deux options pour tester :

```bash
# Dump direct depuis le conteneur
docker exec mysql-container mysqldump -uroot -p c2me > c2me-local.sql

# Ou indiquer un binaire accessible (wrapper ou chemin Herd)
# DB_DUMP_BINARY_PATH="C:/chemin/vers/bin/"
```

Le package transmet les identifiants dans un fichier temporaire
`--defaults-extra-file` : un wrapper Docker doit copier ce fichier dans le
conteneur avant d'appeler `mysqldump`.

Aucune sauvegarde n'est nécessaire en local : `php artisan migrate:fresh` et
`DemoDataSeeder` reconstituent un environnement de test.

## Sécurité et RGPD

- Les archives contiennent des données personnelles : chiffrement obligatoire,
  permissions `600`, répertoire `750`.
- Ne jamais committer d'archive ni de mot de passe ; `storage/app/backups` est
  ignoré par Git.
- Aligner la rétention des archives sur la politique de conservation
  (`docs/crm-rgpd.md`) : une donnée supprimée en base ne doit pas survivre
  indéfiniment dans une sauvegarde.
- À la fin d'un contrat ou sur demande d'effacement, supprimer aussi les
  archives concernées, y compris hors site.
