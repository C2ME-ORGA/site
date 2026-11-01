# C2ME — Procédure de sauvegarde et de restauration

**Entreprise :** SARL C2ME  
**Application :** c2me.ovh  
**Environnement :** Ubuntu 24.04 / aaPanel / Laravel 13  
**Base de données :** MariaDB 10.11  
**Stockage distant :** OVH Object Storage — Région Paris  
**Classification :** Document interne — Procédure technique

---

## 1. Objectif et périmètre

Ce document décrit le dispositif de sauvegarde de l'application C2ME et les procédures permettant de restaurer ses données après une panne, une corruption ou une indisponibilité du serveur.

Les objectifs sont :

- Conserver une copie quotidienne de la base de données.
- Conserver une copie quotidienne des fichiers de l'application.
- Disposer de sauvegardes mensuelles supplémentaires.
- Stocker les archives hors du VPS de production.
- Chiffrer les sauvegardes avant leur transfert.
- Pouvoir reconstruire l'application sur un nouveau serveur.

### 1.1 Environnement de production

| Élément | Configuration |
|---|---|
| Système | Ubuntu 24.04 LTS |
| Panneau d'administration | aaPanel 8.0.7 |
| PHP | 8.4.27 |
| Laravel | 13.38.0 |
| MariaDB | 10.11.10 |
| Application | `/www/wwwroot/c2me.ovh` |
| Base de données | `c2me_ovh` |
| Utilisateur applicatif | `c2me_ovh` |
| Serveur web | Nginx / PHP-FPM |

### 1.2 Architecture des sauvegardes

Deux mécanismes de sauvegarde coexistent :

1. **Sauvegardes aaPanel** : sauvegardes locales historiques, conservées sans modification.
2. **Sauvegardes distantes C2ME** : scripts dédiés, chiffrement GPG et transfert vers OVH Object Storage.

Les deux mécanismes sont complémentaires.

---

## 2. Stockage distant OVH

### 2.1 Configuration

| Paramètre | Valeur |
|---|---|
| Fournisseur | OVHcloud |
| Service | Object Storage S3 |
| Bucket | `c2me-backup` |
| Région | `eu-west-par` |
| Localisation | Paris |
| Endpoint | `https://s3.eu-west-par.io.cloud.ovh.net/` |
| Chiffrement serveur | SSE-OMK |
| Versioning | Activé |
| Object Lock | Désactivé |

Le stockage utilise un compte technique dédié, limité au bucket de sauvegarde.

### 2.2 Organisation du bucket

```text
c2me-backup/
│
├── database/
│   ├── daily/
│   └── monthly/
│
├── files/
│   ├── daily/
│   └── monthly/
│
└── test/
```

Le répertoire `test/` contient des fichiers témoins utilisés lors des tests de fonctionnement.

Ne pas supprimer ces éléments sans vérification préalable.

### 2.3 Accès distant

L'accès au stockage est configuré avec rclone.

Fichier de configuration :

```text
/root/.config/rclone/rclone.conf
```

Remote :

```text
ovh-c2me
```

Permissions :

```text
Répertoire : 700
Configuration : 600
```

Vérification de l'accès :

```bash
rclone lsd ovh-c2me:
```

Vérification du bucket :

```bash
rclone lsf ovh-c2me:c2me-backup
```

Les identifiants S3 ne doivent jamais être inscrits dans un script, un dépôt Git ou une documentation accessible publiquement.

---

## 3. Chiffrement des sauvegardes

### 3.1 Principe

Toutes les archives produites par les scripts C2ME sont chiffrées avec GPG avant leur transfert vers OVH.

Le chiffrement repose sur une clé asymétrique RSA 4096 bits.

Empreinte de la clé :

```text
ACD892F7B9AFFEED4C86798FBFFA52F9F2FCF87E
```

Sous-clé de chiffrement :

```text
F0B1ABBBB29F28A4
```

Identité :

```text
C2ME - Sauvegardes
contact@c2me.ovh
```

### 3.2 Emplacement de la clé

La clé privée est conservée sur le VPS dans :

```text
/root/.gnupg
```

Une copie externe de secours a également été exportée et vérifiée.

La passphrase doit rester conservée séparément dans un gestionnaire de mots de passe sécurisé.

**La perte de la clé privée ou de sa passphrase rendrait les sauvegardes chiffrées irrécupérables.**

### 3.3 Vérification de la clé

```bash
gpg --list-keys
```

Vérification de l'empreinte :

```bash
gpg --fingerprint contact@c2me.ovh
```

Ne jamais supprimer la clé privée tant que des archives chiffrées dépendant de cette clé doivent être conservées.

---

## 4. Sauvegarde de la base de données

### 4.1 Script

Emplacement :

```text
/usr/local/sbin/c2me-db-backup.sh
```

Permissions :

```text
root:root 700
```

Le script accepte deux paramètres :

```bash
c2me-db-backup.sh daily
c2me-db-backup.sh monthly
```

### 4.2 Fonctionnement

Le script :

1. Vérifie le type de sauvegarde.
2. Acquiert un verrou pour empêcher les exécutions simultanées.
3. Effectue un dump MariaDB.
4. Chiffre le flux SQL avec GPG.
5. Transfère l'archive sur OVH.
6. Vérifie le transfert avec rclone.
7. Supprime les fichiers temporaires.
8. Enregistre le résultat dans un journal.

Options principales du dump :

```text
--single-transaction
--quick
--routines
--triggers
--events
--hex-blob
--default-character-set=utf8mb4
```

Le compte de connexion est défini dans :

```text
/root/.my.cnf
```

Permissions :

```text
root:root 600
```

Ne jamais afficher ni transmettre le contenu de ce fichier.

### 4.3 Destination

Sauvegardes quotidiennes :

```text
ovh-c2me:c2me-backup/database/daily/
```

Sauvegardes mensuelles :

```text
ovh-c2me:c2me-backup/database/monthly/
```

Format des fichiers :

```text
c2me_ovh-YYYYMMDD_HHMMSS.sql.gpg
```

### 4.4 Planification

| Fréquence | Horaire | Commande |
|---|---|---|
| Quotidienne | 03:00 | `c2me-db-backup.sh` |
| Mensuelle | Le 1er à 04:00 | `c2me-db-backup.sh monthly` |

---

## 5. Sauvegarde des fichiers de l'application

### 5.1 Script

Emplacement :

```text
/usr/local/sbin/c2me-files-backup.sh
```

Permissions :

```text
root:root 700
```

Paramètres :

```bash
c2me-files-backup.sh daily
c2me-files-backup.sh monthly
```

### 5.2 Éléments sauvegardés

L'archive comprend :

- Le code source Laravel.
- Le fichier `.env`.
- Le fichier `artisan`.
- Les répertoires `app`, `bootstrap`, `config`, `database`, `public`, `resources` et `routes`.
- Les images des réalisations.
- Les documents privés.
- Les pièces jointes des courriels.
- Les demandes de devis et leurs documents.
- Les fichiers publics de stockage.
- Les autres fichiers du projet présents dans le périmètre de sauvegarde.

### 5.3 Éléments exclus

Les éléments suivants sont volontairement exclus :

| Élément | Motif |
|---|---|
| `vendor/` | Dépendances Composer réinstallables |
| `node_modules/` | Dépendances NPM réinstallables |
| `storage/framework/` | Données temporaires Laravel |
| `storage/logs/` | Journaux |
| `storage/app/backups/` | Éviter une sauvegarde récursive |
| `storage/app/private/livewire-tmp/` | Fichiers temporaires |
| `bootstrap/cache/` | Cache régénérable |

### 5.4 Destination

Sauvegardes quotidiennes :

```text
ovh-c2me:c2me-backup/files/daily/
```

Sauvegardes mensuelles :

```text
ovh-c2me:c2me-backup/files/monthly/
```

Format :

```text
c2me_files-YYYYMMDD_HHMMSS.tar.gz.gpg
```

### 5.5 Planification

| Fréquence | Horaire | Commande |
|---|---|---|
| Quotidienne | 03:30 | `c2me-files-backup.sh` |
| Mensuelle | Le 1er à 04:30 | `c2me-files-backup.sh monthly` |

---

## 6. Configuration des tâches cron

Les tâches sont installées dans la crontab de root.

Vérification :

```bash
crontab -l
```

Configuration C2ME :

```cron
0 3 * * * /usr/local/sbin/c2me-db-backup.sh
30 3 * * * /usr/local/sbin/c2me-files-backup.sh

0 4 1 * * /usr/local/sbin/c2me-db-backup.sh monthly
30 4 1 * * /usr/local/sbin/c2me-files-backup.sh monthly
```

Les autres tâches aaPanel sont conservées indépendamment.

**Ne pas remplacer l'intégralité de la crontab lors d'une intervention.**

### 6.1 Journaux

Base de données :

```text
/var/log/c2me-db-backup.log
```

Fichiers :

```text
/var/log/c2me-files-backup.log
```

Consultation :

```bash
tail -n 50 /var/log/c2me-db-backup.log
tail -n 50 /var/log/c2me-files-backup.log
```

Recherche des erreurs :

```bash
grep -iE 'error|failed|fatal|critical' \
  /var/log/c2me-db-backup.log \
  /var/log/c2me-files-backup.log
```

Une exécution réussie doit notamment confirmer le transfert et le contrôle rclone sans différence.

---

## 7. Sauvegardes aaPanel conservées

Les sauvegardes historiques aaPanel restent actives.

### 7.1 Sauvegardes SQL

Horaire : 01:30 quotidien.

Répertoire :

```text
/www/backup/database/mysql/crontab_backup/
```

Rétention configurée : 3 copies locales.

### 7.2 Sauvegardes des sites

Sauvegarde hebdomadaire, tous les 7 jours, à 01:30.

Répertoire :

```text
/www/backup/site/
```

Les archives aaPanel ne sont pas chiffrées individuellement par notre dispositif GPG.

Elles constituent donc une sauvegarde locale complémentaire, mais ne remplacent pas les sauvegardes distantes chiffrées.

---

## 8. Procédure de restauration sur un nouveau VPS

Cette procédure concerne une reconstruction après perte du serveur de production.

### 8.1 Préparation du serveur

Installer et configurer :

- Ubuntu 24.04 LTS.
- aaPanel.
- Nginx.
- PHP 8.4 avec les extensions nécessaires à Laravel.
- MariaDB 10.11.
- Composer.
- Node.js et npm si une reconstruction des ressources frontend est nécessaire.
- rclone.
- GPG.

Créer le site :

```text
/www/wwwroot/c2me.ovh
```

Vérifier que le serveur dispose de suffisamment d'espace disque.

### 8.2 Récupération des accès

Restaurer les éléments suivants :

1. La configuration rclone.
2. La clé privée GPG.
3. La passphrase correspondante, depuis le gestionnaire sécurisé.

Les fichiers de configuration doivent être protégés.

```bash
chmod 700 /root/.config/rclone
chmod 600 /root/.config/rclone/rclone.conf
```

Vérifier la présence de la clé :

```bash
gpg --list-keys
```

Vérifier l'accès OVH :

```bash
rclone lsd ovh-c2me:
```

### 8.3 Récupération des archives

Lister les sauvegardes :

```bash
rclone lsf ovh-c2me:c2me-backup/database/daily/
rclone lsf ovh-c2me:c2me-backup/database/monthly/

rclone lsf ovh-c2me:c2me-backup/files/daily/
rclone lsf ovh-c2me:c2me-backup/files/monthly/
```

Choisir les archives correspondant à la date de restauration souhaitée.

Exemple de récupération :

```bash
mkdir -p /root/c2me-restore

chmod 700 /root/c2me-restore
```

Télécharger les archives choisies avec `rclone copyto` :

```bash
rclone copyto \
  ovh-c2me:c2me-backup/database/daily/NOM_ARCHIVE.sql.gpg \
  /root/c2me-restore/database.sql.gpg

rclone copyto \
  ovh-c2me:c2me-backup/files/daily/NOM_ARCHIVE.tar.gz.gpg \
  /root/c2me-restore/files.tar.gz.gpg
```

Remplacer les noms d'exemple par les noms réels obtenus avec `rclone lsf`.

### 8.4 Vérification des archives

Avant restauration, vérifier que les fichiers sont présents :

```bash
ls -lh /root/c2me-restore/
```

Tester le déchiffrement de la base :

```bash
gpg --batch --decrypt \
  /root/c2me-restore/database.sql.gpg \
  > /dev/null
```

Tester intégralement la lecture de l'archive des fichiers :

```bash
set -o pipefail

gpg --batch --decrypt \
  /root/c2me-restore/files.tar.gz.gpg \
  | tar -tzf - > /root/c2me-restore/files-list.txt

echo "Code retour : $?"
```

Le code retour attendu est `0`.

### 8.5 Restauration des fichiers

Créer le répertoire cible :

```bash
mkdir -p /www/wwwroot/c2me.ovh
```

Extraire les fichiers :

```bash
set -o pipefail

gpg --batch --decrypt \
  /root/c2me-restore/files.tar.gz.gpg \
  | tar --extract --gzip --file=- \
      --directory=/www/wwwroot/c2me.ovh \
      --no-same-owner
```

L'extraction doit être réalisée dans un environnement isolé ou sur le serveur de remplacement, jamais directement sur une production encore en fonctionnement.

### 8.6 Création de la base MariaDB

Dans aaPanel :

1. Ouvrir Database.
2. Créer la base `c2me_ovh`.
3. Créer un utilisateur applicatif dédié.
4. Générer un mot de passe robuste.
5. Limiter les droits à cette base.
6. Configurer les paramètres dans le fichier `.env`.

Ne jamais attribuer des privilèges globaux à l'utilisateur applicatif.

### 8.7 Importation SQL

Une fois la base créée et les identifiants configurés, exécuter :

```bash
set -o pipefail

gpg --batch --decrypt \
  /root/c2me-restore/database.sql.gpg \
  | mariadb \
      --host=127.0.0.1 \
      --user=c2me_ovh \
      --password \
      c2me_ovh

echo "Code retour : $?"
```

Saisir le mot de passe lorsque MariaDB le demande.

Le code retour attendu est `0`.

Ne pas lancer de migration Laravel pour remplacer une restauration SQL complète.

### 8.8 Reconstitution des dépendances Laravel

Le répertoire `vendor/` étant exclu, réinstaller les dépendances :

```bash
cd /www/wwwroot/c2me.ovh

composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader
```

Le répertoire `node_modules/` étant également exclu, reconstruire les ressources frontend uniquement si nécessaire :

```bash
npm ci
npm run build
```

Vérifier les dépendances et la compatibilité avec la version PHP du serveur.

### 8.9 Recréation des répertoires Laravel

```bash
cd /www/wwwroot/c2me.ovh

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache
```

### 8.10 Restauration des permissions

Utilisateur du serveur web : `www:www`.

Appliquer :

```bash
chown -R www:www /www/wwwroot/c2me.ovh

chmod 600 /www/wwwroot/c2me.ovh/.env

chmod -R 775 \
  /www/wwwroot/c2me.ovh/storage \
  /www/wwwroot/c2me.ovh/bootstrap/cache

chmod 755 /www/wwwroot/c2me.ovh
chmod 755 /www/wwwroot/c2me.ovh/bootstrap
```

Vérifier les permissions :

```bash
stat -c '%U:%G %a %n' \
  /www/wwwroot/c2me.ovh \
  /www/wwwroot/c2me.ovh/.env \
  /www/wwwroot/c2me.ovh/storage \
  /www/wwwroot/c2me.ovh/bootstrap/cache
```

### 8.11 Vérifications Laravel

Contrôler les paramètres du `.env` :

- `APP_ENV`
- `APP_DEBUG`
- `APP_URL`
- `APP_KEY`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`

Conserver impérativement l'ancienne valeur `APP_KEY` pour pouvoir déchiffrer les données Laravel déjà chiffrées.

Vérifier que :

```dotenv
APP_DEBUG=false
```

Puis exécuter :

```bash
php artisan optimize:clear
php artisan config:cache
php artisan about
```

Vérifier également la connexion MariaDB, les sessions, les fichiers privés et les fonctions principales de l'application.

Ne remettre le site en service qu'après validation.

---

## 9. Politique de rétention

Politique souhaitée :

| Type de sauvegarde | Conservation |
|---|---:|
| Base quotidienne | 35 jours |
| Fichiers quotidiens | 35 jours |
| Base mensuelle | 12 mois |
| Fichiers mensuels | 12 mois |
| Anciennes versions S3 | 30 jours |

**État de mise en œuvre :**

- Le versioning OVH est activé.
- Object Lock est désactivé.
- Les règles Lifecycle OVH ne sont pas activées.
- Les effets de la suppression des anciennes versions n'ont pas encore été validés.
- La purge automatique conforme à cette politique reste à mettre en place et à tester.

Ne pas activer de règle Lifecycle sans vérifier son impact sur les versions courantes et non courantes.

---

## 10. Contrôles périodiques

### Contrôle quotidien

Vérifier les journaux :

```bash
tail -n 30 /var/log/c2me-db-backup.log
tail -n 30 /var/log/c2me-files-backup.log
```

Vérifier les dernières archives :

```bash
rclone lsf ovh-c2me:c2me-backup/database/daily/
rclone lsf ovh-c2me:c2me-backup/files/daily/
```

### Contrôle mensuel

Vérifier la présence des archives mensuelles :

```bash
rclone lsf ovh-c2me:c2me-backup/database/monthly/
rclone lsf ovh-c2me:c2me-backup/files/monthly/
```

Vérifier le fonctionnement des scripts et les résultats des transferts.

### Contrôle périodique de restauration

Effectuer un test dans un environnement isolé :

- Déchiffrement d'une archive SQL.
- Importation dans une base temporaire.
- Comparaison des volumes de données.
- Extraction complète de l'archive fichiers.
- Vérification de la présence des documents privés et des images.
- Vérification des permissions nécessaires à Laravel.

Une restauration réelle doit être testée périodiquement, idéalement sur un serveur de secours.

---

## 11. Historique des tests réalisés

**Date des tests : 31 octobre 2026**

### Base de données

| Contrôle | Résultat |
|---|---|
| Création du dump MariaDB | Réussie |
| Chiffrement GPG | Réussi |
| Transfert OVH | Réussi |
| Contrôle rclone | Réussi |
| Téléchargement de l'archive | Réussi |
| Déchiffrement | Réussi |
| Importation temporaire | Réussie |
| Présence des tables | 29 tables |
| Comparaison des données | 7 contrôles identiques |

Base temporaire utilisée :

```text
c2me_restore_test
```

### Fichiers

| Contrôle | Résultat |
|---|---|
| Création de l'archive TAR | Réussie |
| Chiffrement GPG | Réussi |
| Transfert OVH | Réussi |
| Contrôle rclone | Réussi |
| Déchiffrement | Réussi |
| Extraction complète | Réussie |
| Fichiers restaurés | 709 |
| Taille extraite | 45 Mo |
| `.env` | Présent |
| Code Laravel | Présent |
| Images des réalisations | Présentes |
| Documents privés | Présents |

Répertoire temporaire :

```text
/root/backup-test-gpg/monthly-check/files-restore
```

### Limites des tests

Les restaurations de la base et des fichiers ont été validées séparément.

En revanche, la reconstruction complète de Laravel sur un nouveau VPS, le démarrage de l'application restaurée et le fonctionnement des services externes n'ont pas encore été testés de bout en bout.

---

## 12. Éléments critiques à protéger

Les éléments suivants sont indispensables à la reprise :

1. La clé privée GPG et sa copie externe.
2. La passphrase GPG.
3. Les identifiants S3 OVH.
4. Les paramètres MariaDB.
5. Le fichier `.env`.
6. La valeur originale de `APP_KEY`.
7. Les scripts de sauvegarde.
8. La configuration du serveur web et du domaine.

La documentation ne doit jamais contenir de mots de passe, de clés privées ou de secrets d'API.

---

## 13. État général du dispositif

| Composant | État |
|---|---|
| Sauvegarde DB quotidienne | Opérationnelle |
| Sauvegarde fichiers quotidienne | Opérationnelle |
| Sauvegarde DB mensuelle | Programmée |
| Sauvegarde fichiers mensuelle | Programmée |
| Stockage OVH | Opérationnel |
| Chiffrement GPG | Opérationnel |
| Versioning S3 | Activé |
| Restauration DB temporaire | Validée |
| Restauration fichiers temporaire | Validée |
| Reconstruction complète du VPS | À tester |
| Purge automatique des anciennes versions | À mettre en place |
| Règles Lifecycle OVH | Non activées |

**Conclusion :** C2ME dispose d'un système de sauvegarde distant chiffré et de procédures de restauration testées séparément pour la base de données et les fichiers. Les prochaines améliorations concernent la validation d'une reconstruction complète sur serveur de secours et la mise en place maîtrisée de la politique de rétention.
