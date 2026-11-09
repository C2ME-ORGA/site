# Surveillance de sécurité — procédure d’exploitation

Cette page décrit l’exploitation du module de sécurité
(`mkwebdesign/filament-watchdog-v5`) en production. La documentation
utilisateur se trouve dans la base documentaire (`/aide/administration/securite`).

## 1. Composants

| Élément | Rôle |
| --- | --- |
| `watchdog:scan --force` | Hache les fichiers surveillés, détecte les modifications et les signatures de malwares. |
| `watchdog:baseline` | Enregistre l’état de référence de tous les fichiers surveillés. |
| `security:lockdown:release` | Lève un verrouillage d’urgence expiré (mode maintenance compris). |
| `watchdog:cleanup --force` | Supprime les journaux et alertes résolues au-delà de la rétention. |
| `/admin/security/dashboard` | Tableau de bord (administrateurs uniquement). |
| `/admin/security-alerts` | Alertes : consultation, acquittement, résolution. |

Les autres écrans (`/admin/file-integrities`, `/admin/malware-detections`,
`/admin/activity-logs`) sont également réservés aux administrateurs et en
lecture seule.

## 2. Planification

Dans `routes/console.php` :

- `watchdog:scan --force` toutes les 15 minutes, sans chevauchement ;
- `security:lockdown:release` toutes les 10 minutes ;
- `watchdog:cleanup --force` une fois par jour (planifié par le paquet).

La planification du paquet (`watchdog:scan` chaque minute) est désactivée via
`monitoring.enabled = false`. `--force` est indispensable : sans lui, la
commande s’arrête immédiatement quand la surveillance est désactivée.

Le scan est **désactivé sous Windows** (`PHP_OS_FAMILY`) : le paquet y calcule
des chemins absolus qui faussent la détection des suppressions. Il ne s’exécute
qu’en production Linux.

Prérequis serveur :

- cron Laravel actif : `* * * * * cd /chemin/du/site && php artisan schedule:run >> /dev/null 2>&1` ;
- cache partagé (Redis) pour `onOneServer()` et `withoutOverlapping()` ;
- fonctions `exec` et `proc_open` autorisées pour le mode maintenance ;
- droits d’écriture sur `storage/app` (quarantaine) et `resources/views/errors`.

## 3. Baseline après déploiement

Sans baseline, tous les fichiers sont signalés comme nouveaux. Avec un
baseline obsolète, chaque déploiement produit des centaines d’alertes.

Procédure recommandée, **après** un déploiement volontaire et vérifié :

```bash
php artisan down                 # optionnel : éviter un scan pendant la copie
# déploiement (git pull, composer install --no-dev, migrations, npm run build)
php artisan watchdog:baseline
php artisan up
```

Contrôles avant le baseline :

1. le déploiement provient d’un commit relu et tagué ;
2. aucun fichier inconnu n’a été ajouté (`git status --short` doit être
   propre sur le serveur) ;
3. les journaux ne montrent pas d’activité suspecte.

Précautions :

- Ne **jamais** créer un baseline automatiquement au démarrage ou après un
  scan : cela neutraliserait la détection des modifications malveillantes.
- Ne **jamais** créer un baseline sur un serveur suspect. Après une intrusion,
  restaurer d’abord une version saine (sauvegarde ou dépôt), puis créer le
  baseline.
- Le baseline est stocké en base (table `file_integrity_checks`), pas sur
  disque. Il suit donc la sauvegarde de la base.
- Sur un premier déploiement, créer le baseline avant de considérer les
  alertes comme fiables.

En cas de doute, la commande échoue explicitement si aucun fichier n’est
surveillé (mauvais `monitored_paths`) et indique le nombre de fichiers
enregistrés.

## 4. Traitement des alertes

Les alertes sont créées automatiquement : fichier nouveau, modifié, supprimé,
malware détecté, force brute, verrouillage. Elles ne sont jamais créées à la
main.

| Transition | Condition | Effet |
| --- | --- | --- |
| Acquitter | statut `new` | statut `acknowledged`, auteur et date renseignés |
| Résoudre | statut `new` ou `acknowledged` | statut `resolved`, auteur et date renseignés |

Une alerte résolue ne peut plus être modifiée. Les champs techniques
(gravité, description, métadonnées) sont en lecture seule : le droit
générique `update` est refusé par la politique, seules les deux transitions
sont autorisées.

En cas de faux positif : résoudre l’alerte en indiquant la vérification dans
le compte rendu d’exploitation. Ne pas supprimer la ligne.

## 5. Notifications

- `WATCHDOG_EMAIL_ENABLED` : active ou coupe l’envoi.
- `WATCHDOG_EMAIL_RECIPIENTS` : liste séparée par des virgules. Vide →
  `ADMIN_EMAIL`.
- `WATCHDOG_ADMIN_EMAILS` : destinataires des notifications de verrouillage.
- Limite globale : 10 e-mails par heure (clé de cache `watchdog_alerts_sent`).
- Toutes les gravités sont envoyées ; il n’existe pas de seuil minimal.
- Un échec d’envoi est journalisé (`Log::error`) sans interrompre la création
  de l’alerte.

## 6. Verrouillage d’urgence

Activation depuis le tableau de bord (administrateurs uniquement) :

- alerte critique créée et journalisée ;
- site en maintenance (`php artisan down --secret=…`) ;
- sessions fermées si `SESSION_DRIVER=database` (sans effet en `file`) ;
- sauvegarde d’urgence et notification des administrateurs ;
- URL d’accès : `https://<domaine>/<secret>`, valable 24 heures ;
- ajout de règles de blocage d’IP dans `public/.htaccess` (sans effet sous
  nginx ; l’IP de l’administrateur qui déclenche est exclue).

Levée :

1. bouton **Deactivate Lockdown** tant que l’état est en cache ;
2. `php artisan security:lockdown:release` après expiration (automatique
   toutes les 10 minutes) ;
3. `php artisan up` en dernier recours si le cache a été vidé ou si la
   commande échoue.

Ne jamais tester le verrouillage sur la production : utiliser un
environnement de recette.

## 7. Journal d’activité

- Connexions réussies (`successful_login`, risque faible).
- Connexions échouées (`failed_login`, risque moyen).
- Au-delà de 5 échecs depuis la même IP en une heure : alerte critique
  `brute_force_attempt`.
- Aucun mot de passe, jeton ou secret n’est enregistré.
- Les événements hors requête web (CLI, file d’attente) sont ignorés.
- Purge quotidienne : `watchdog:cleanup --force` (30 jours par défaut).

## 8. Dépannage

| Symptôme | Cause probable |
| --- | --- |
| Le site reste en maintenance | Cache vidé ou secret expiré : `php artisan up`. |
| `watchdog:scan` ne fait rien | Lancer avec `--force`. |
| Aucune alerte après déploiement | Baseline non recréé → recréer. |
| Aucun e-mail | `WATCHDOG_EMAIL_ENABLED=false`, liste vide sans `ADMIN_EMAIL`, ou limite horaire atteinte. |
| `exec()` indisponible | Le mode maintenance échoue ; prévoir `php artisan down` manuel. |
| Sessions non fermées | `SESSION_DRIVER=file` : comportement attendu, seules les sessions en base sont supprimées. |

> Le paquet journalise le secret d’accès d’urgence dans `storage/logs` au
> moment de l’activation. Les journaux sont réservés aux administrateurs
> (visionneuse) et purgés avec le reste de la rétention, mais le secret doit
> être considéré comme sensible : ne pas exporter les journaux sans les
> vérifier.
