# Déploiement CI/CD — C2ME

## Architecture

- Les Pull Requests vers `master` exécutent les contrôles PHP, Pest et le build frontend.
- Un push sur `master` déclenche les contrôles, puis le déploiement SSH si les contrôles réussissent.
- GitHub Actions se connecte avec le compte SSH dédié `deploy` et le secret `DEPLOY_PASSWORD`.
- `deploy` peut uniquement lancer `sudo -n /usr/local/sbin/c2me-deploy`.
- Le script privilégié est installé et détenu par root. Il sauvegarde la base, orchestre le déploiement sous `www` et contrôle Supervisor aaPanel.
- Le script applicatif du dépôt s'exécute uniquement sous `www`; il ne reçoit aucun privilège root.

## Environnement confirmé sur le serveur C2ME

- Application : `/www/wwwroot/c2me.ovh`, propriétaire `www:www`.
- PHP : `/www/server/php/84/bin/php` (8.4.27).
- Composer : `/usr/bin/composer`.
- Node/npm : `/www/server/nodejs/v22.17.1/bin` (Node 22.17.1).
- Supervisor aaPanel : `/www/server/panel/pyenv/bin/supervisorctl`.
- Configuration Supervisor : `/etc/supervisor/supervisord.conf`.
- Processus C2ME : `c2mequeue:c2mequeue_00` et `c2mereverb:c2mereverb_00`.
- Le processus BATISTACK `batistack` est explicitement exclu du déploiement.

## Pré-requis serveur

Le Document Root aaPanel doit rester `/www/wwwroot/c2me.ovh/public`.

Installer/valider les commandes `git`, `runuser`, `flock`, `curl`, `gzip`, `mysqldump` et `sudo`. `sshpass` est installé sur le runner GitHub, pas sur le serveur.

### Configuration root

Créer `/etc/c2me/deploy.conf`, appartenant à root et non modifiable par deploy/www :

```bash
APP_DIR="/www/wwwroot/c2me.ovh"
DEPLOY_BRANCH="master"
HEALTH_URL="https://c2me.fr/up"
BACKUP_DIR="/www/backup/c2me/database"
PHP_BIN="/www/server/php/84/bin/php"
COMPOSER_BIN="/usr/bin/composer"
NODE_BIN_DIR="/www/server/nodejs/v22.17.1/bin"
QUEUE_PROGRAM="c2mequeue"
REVERB_PROGRAM="c2mereverb"
SUPERVISORCTL="/www/server/panel/pyenv/bin/supervisorctl"
SUPERVISOR_CONFIG="/etc/supervisor/supervisord.conf"
```

Puis :

```bash
chown root:root /etc/c2me/deploy.conf
chmod 600 /etc/c2me/deploy.conf
```

Ne pas placer de secrets GitHub ni de mot de passe SSH dans ce fichier. Les identifiants SQL restent dans le `.env` de l'application.

### Installer le script privilégié

Après fusion du PR, depuis le répertoire du dépôt :

```bash
install -o root -g root -m 0750 scripts/deploy-production-root.sh /usr/local/sbin/c2me-deploy
```

Le helper applicatif reste dans le dépôt et appartient à `www` :

```bash
chown www:www /www/wwwroot/c2me.ovh/scripts/deploy-production-app.sh
chmod 0750 /www/wwwroot/c2me.ovh/scripts/deploy-production-app.sh
```

Le script root exécute ce helper avec `runuser -u www`. Le helper étant exécuté sous www, son contenu ne doit jamais être exécuté en root.

### Autorisation sudo limitée

Créer `/etc/sudoers.d/c2me-deploy` :

```sudoers
deploy ALL=(root) NOPASSWD: /usr/local/sbin/c2me-deploy
```

Puis :

```bash
chown root:root /etc/sudoers.d/c2me-deploy
chmod 0440 /etc/sudoers.d/c2me-deploy
visudo -cf /etc/sudoers.d/c2me-deploy
sudo -l -U deploy
```

Ne jamais ajouter `deploy` aux groupes `sudo` ou `www`. Ne pas lui donner d'accès en écriture au dépôt, au fichier de configuration, au script root ou au répertoire de sauvegarde.

Le script root valide les chemins et noms des programmes Supervisor et n'accepte aucun argument.

## Secrets GitHub

Dans Settings > Secrets and variables > Actions, idéalement dans l'environnement `production` :

- `DEPLOY_HOST` : adresse IP ou DNS du serveur.
- `DEPLOY_USER` : `deploy`.
- `DEPLOY_PASSWORD` : mot de passe SSH du compte deploy.
- `DEPLOY_PORT` : port SSH, facultatif (22 par défaut).
- `DEPLOY_KNOWN_HOSTS` : entrée de clé hôte SSH vérifiée indépendamment.

Le workflow impose `DEPLOY_USER=deploy`, vérifie la clé d'hôte et utilise sshpass via `SSHPASS`. Ne jamais afficher ni écrire le mot de passe dans les logs.

Créer un environnement GitHub nommé `production`, avec approbation manuelle si souhaitée. Restreindre les déploiements à la branche `master`.

## Déroulement

1. Contrôles et tests sur GitHub.
2. Connexion SSH avec `deploy`.
3. Exécution de l'unique commande sudo autorisée.
4. Précontrôles Git exécutés sous www.
5. Sauvegarde SQL gzip et vérification de l'archive.
6. Activation maintenance sous www.
7. Git fast-forward, Composer, npm ci/build, migrations et caches sous www.
8. Redémarrage et vérification de `c2mequeue` et `c2mereverb` via Supervisor aaPanel.
9. Sortie de maintenance et contrôle HTTP de `https://c2me.fr/up`.
10. Conservation locale des sauvegardes SQL pendant 14 jours.

Le service `batistack` n'est jamais redémarré.

## Sauvegardes et limites

La sauvegarde SQL est locale dans `/www/backup/c2me/database`. Elle ne remplace pas une copie chiffrée hors serveur. Les fichiers utilisateurs de `storage/app` doivent également être sauvegardés séparément.

Le déploiement est « in-place » : aucun rollback automatique du code ou de la base n'est effectué. Une restauration SQL doit être décidée explicitement et coordonnée avec la version du code.

## Vérifications avant activation

1. Confirmer le Document Root aaPanel.
2. Vérifier `/etc/c2me/deploy.conf`, ses permissions et les chemins PHP/Node.
3. Installer le script privilégié.
4. Valider sudoers avec `visudo), puis tester `sudo -l -U deploy`.
5. Vérifier que `deploy` ne peut pas modifier le dépôt, le script root ou la configuration.
6. Ajouter les secrets GitHub et vérifier `DEPLOY_KNOWN_HOSTS`.
7. Tester une sauvegarde/restauration sur une base de test.
8. Faire un premier déploiement contrôlé avec approbation manuelle.

## Dépannage

- Logs privilégiés : `/var/log/c2me/`.
- Logs applicatifs : `storage/logs/`.
- État workers : `/www/server/panel/pyenv/bin/supervisorctl -c /etc/supervisor/supervisord.conf status`.
- Santé HTTP : `curl -i https://c2me.fr/up`.
- Sauvegardes : `ls -lh /www/backup/c2me/database`.
