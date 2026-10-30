# Déploiement CI/CD — C2ME

## Architecture

Les deux environnements sont hébergés sur le même serveur OVH, mais utilisent des installations Laravel indépendantes.

| Environnement | Branche | Domaine | Répertoire |
|---|---|---|---|
| Préproduction | `master` | `https://c2me.ovh` | `/www/wwwroot/c2me.ovh` |
| Production | `production` | `https://c2me.fr` | `/www/wwwroot/c2me.fr` |

Chaque installation doit avoir son propre `.env`, sa base de données, son `APP_KEY`, ses sessions, son cache, son stockage `storage/` et ses sauvegardes. Ne jamais faire pointer les deux environnements vers la même base ou le même répertoire de fichiers utilisateurs.

Les Document Roots aaPanel doivent être respectivement :
- Préproduction : `/www/wwwroot/c2me.ovh/public`.
- Production : `/www/wwwroot/c2me.fr/public`.

### Déclenchement GitHub Actions

- Pull Request vers `master` : CI uniquement, aucun déploiement.
- Push sur `master` après réussite de la CI : déploiement sur `preproduction`, domaine `c2me.ovh`.
- Push sur `production` après réussite de la CI : déploiement sur `production`, domaine `c2me.fr`.
- Les deux jobs utilisent un verrou de concurrence commun car les environnements partagent le même serveur.

Créer dans GitHub Settings > Environments deux environnements distincts :
- `preproduction`, avec déploiements autorisés uniquement depuis `master`.
- `production`, avec déploiements autorisés uniquement depuis `production` et approbation manuelle obligatoire (Required reviewers). Désactiver l'auto-approbation si l'option est disponible.

Chaque environnement doit contenir ses secrets de connexion SSH : `DEPLOY_HOST`, `DEPLOY_USER` (= `deploy`), `DEPLOY_PASSWORD`, `DEPLOY_PORT` (facultatif, 22 par défaut) et `DEPLOY_KNOWN_HOSTS`. Les valeurs peuvent être identiques puisque le serveur est partagé. Ne jamais enregistrer le mot de passe dans le dépôt ou les logs.

## Configuration serveur

Valeurs runtime actuellement prévues, à confirmer sur le serveur :
- PHP : `/www/server/php/84/bin/php` (PHP 8.4).
- Composer : `/usr/bin/composer`.
- Node/npm : `/www/server/nodejs/v22.17.1/bin`.
- Supervisor aaPanel : `/www/server/panel/pyenv/bin/supervisorctl`.
- Configuration Supervisor : `/etc/supervisor/supervisord.conf`.
- Compte applicatif : `www`.
- Compte SSH CI/CD : `deploy`.

Créer les deux configurations root à partir des exemples versionnés :
- `deploy/config/preproduction.conf.example` vers `/etc/c2me/preproduction.conf`.
- `deploy/config/production.conf.example` vers `/etc/c2me/production.conf`.

Les fichiers finaux doivent appartenir à root et ne pas être modifiables par group/others :
```bash
chown root:root /etc/c2me/preproduction.conf /etc/c2me/production.conf
chmod 600 /etc/c2me/preproduction.conf /etc/c2me/production.conf
```

Les chemins applicatifs, branches, noms Supervisor et répertoires de sauvegarde sont contrôlés par le script root :
- Préproduction : `/www/wwwroot/c2me.ovh`, branche `master`, programmes `c2mequeue` et `c2mereverb`.
- Production : `/www/wwwroot/c2me.fr`, branche `production`, programmes `c2meprodqueue` et `c2meprodreverb`.

Le script refuse une configuration qui mélange ces valeurs. Les sauvegardes sont séparées dans `/www/backup/c2me/preproduction` et `/www/backup/c2me/production`.

## Supervisor

Les processus existants `c2mequeue` et `c2mereverb` restent réservés à la préproduction. La production doit avoir ses propres programmes Supervisor, nommés `c2meprodqueue` et `c2meprodreverb`. Ils doivent être créés avant le premier déploiement de production.

Exemple de configuration de worker Queue (adapter le nombre de processus et les options aux besoins réels) :

```ini
[program:c2meprodqueue]
process_name=%(program_name)s_%(process_num)02d
command=/www/server/php/84/bin/php /www/wwwroot/c2me.fr/artisan queue:work --sleep=3 --tries=3 --timeout=90
directory=/www/wwwroot/c2me.fr
user=www
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/www/wwwroot/c2me.fr/storage/logs/queue-worker.log
```

Exemple de configuration Reverb :

```ini
[program:c2meprodreverb]
command=/www/server/php/84/bin/php /www/wwwroot/c2me.fr/artisan reverb:start
directory=/www/wwwroot/c2me.fr
user=www
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/www/wwwroot/c2me.fr/storage/logs/reverb.log
```

Les commandes et chemins doivent être validés avec la configuration réellement utilisée par aaPanel. Reproduire les programmes équivalents pour la préproduction en conservant les noms déjà en service. Après ajout, recharger Supervisor et vérifier que les quatre groupes ciblés sont indépendants et RUNNING. Ne pas redémarrer ou arrêter les groupes préproduction pendant une opération de production, et inversement.

## Installation du point d'entrée privilégié

Après fusion de la PR et avant tout déploiement automatique, installer le script root :
```bash
install -o root -g root -m 0750 scripts/deploy-production-root.sh /usr/local/sbin/c2me-deploy
```

Le script applicatif `scripts/deploy-production-app.sh` doit être présent dans chacun des deux dépôts applicatifs, appartenir à `www:www` et être lisible/exécutable par `www`. Le script root copie le helper de l'environnement ciblé dans un emplacement temporaire protégé puis l'exécute sous `www`.

### Sudoers

Créer `/etc/sudoers.d/c2me-deploy` :
```sudoers
deploy ALL=(root) NOPASSWD: /usr/local/sbin/c2me-deploy preproduction, /usr/local/sbin/c2me-deploy production
```

Puis vérifier :
```bash
chown root:root /etc/sudoers.d/c2me-deploy
chmod 0440 /etc/sudoers.d/c2me-deploy
visudo -cf /etc/sudoers.d/c2me-deploy
sudo -l -U deploy
```

Ne pas ajouter `deploy` aux groupes `sudo` ou `www`. Ne pas lui donner d'accès en écriture aux dépôts, configurations root, scripts privilégiés ou répertoires de sauvegarde.

## Déroulement d'un déploiement

Pour la cible sélectionnée :
1. CI complète : contrôles PHP, tests et build frontend.
2. Connexion SSH via le compte `deploy`.
3. Appel sudo strictement limité à `c2me-deploy preproduction` ou `c2me-deploy production`.
4. Précontrôles Git exécutés sous `www` (branche attendue, dépôt propre, avance fast-forward).
5. Sauvegarde SQL et `storage/app` de l'environnement ciblé.
6. Activation du mode maintenance sur cette seule application.
7. Arrêt de ses propres groupes Queue et Reverb.
8. Pull fast-forward, Composer, `npm ci`/build, migrations et caches.
9. Redémarrage et contrôle de ses propres groupes Supervisor.
10. Sortie de maintenance et health check HTTP sur le domaine ciblé.

Un verrou global empêche deux déploiements simultanés sur le serveur. Le déploiement de préproduction ne doit jamais toucher aux processus, fichiers ou à la base de production.

## Sauvegardes et limites

Les sauvegardes SQL et `storage/app` sont locales et séparées par environnement. Elles sont conservées 14 jours. Elles ne remplacent pas une copie chiffrée hors serveur. Organiser le transfert hors serveur et tester périodiquement la restauration.

Le déploiement est « in-place » : aucun rollback automatique du code ou de la base n'est effectué. Une restauration SQL doit être décidée explicitement et coordonnée avec la version du code. Tester la procédure sur la préproduction avant d'autoriser le premier déploiement de production.

## Checklist avant activation

1. Créer les deux répertoires applicatifs et leurs Document Roots aaPanel.
2. Vérifier que chaque dépôt est checkouté sur sa branche dédiée (`master` / `production`) et appartient à `www:www`.
3. Créer deux bases distinctes et deux fichiers `.env` indépendants ; vérifier les clés, URL, sessions, cache et stockage.
4. Créer les configurations root à partir des exemples et vérifier leurs permissions.
5. Configurer les deux programmes Supervisor de production, puis vérifier les quatre groupes.
6. Installer le script root et valider sudoers.
7. Configurer les environnements GitHub et leurs secrets ; exiger l'approbation pour `production`.
8. Tester sauvegarde et restauration de préproduction.
9. Effectuer un déploiement contrôlé sur `master` et vérifier `https://c2me.ovh/up`.
10. Tester le déploiement de production uniquement après validation complète de la préproduction.

## Dépannage

- Logs privilégiés : `/var/log/c2me/`.
- Logs applicatifs : `storage/logs/` dans le répertoire de l'environnement concerné.
- Supervisor : `/www/server/panel/pyenv/bin/supervisorctl -c /etc/supervisor/supervisord.conf status`.
- Santé préproduction : `curl -i https://c2me.ovh/up`.
- Santé production : `curl -i https://c2me.fr/up`.
- Sauvegardes : `/www/backup/c2me/preproduction` et `/www/backup/c2me/production`.
