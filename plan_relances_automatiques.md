# Plan de développement : relances automatiques

> **État : implémenté (21/10/2026).**
> Branche cible : `feature/crm`.
> Périmètre : rappels à partir de `crm_accounts.next_follow_up_at`.
> Décisions validées : propriétaire + administrateurs, notification in-app + e-mail, rappel la veille et le jour J, page « Relances » dédiée, comptes uniquement.
> Vérification : Pint OK · PHPStan 0 erreur · 180 tests / 715 assertions.

## 1. Objectif

Automatiser les rappels de relance commerciale à partir de `next_follow_up_at` :

- prévenir le commercial **la veille** de la relance prévue ;
- le prévenir **le jour J** si la relance est toujours à faire ;
- donner aux administrateurs un **récapitulatif global** ;
- permettre de **traiter la relance en un clic** : marquer comme relancé, reporter, ouvrir la fiche ;
- garantir qu'un même rappel n'est **jamais envoyé deux fois** pour la même échéance.

## 2. État des lieux

| Élément | Existant | À faire |
|---|---|---|
| `next_follow_up_at` | Colonne nullable et indexée sur `crm_accounts` | Exploiter la date pour déclencher les rappels |
| Filtre « Relance due » | Présent dans `AccountsTable` | Réutiliser la logique sur la page Relances |
| Stat dashboard | `CrmStatsOverview` compte les relances dues | Rendre la stat cliquable vers la page Relances |
| Notifications Filament | `->databaseNotifications()` activé | Ajouter une notification de digest |
| E-mail transactionnel | `QuoteStatusChangedMail` + `QuoteNotifier` servent de modèle | Ajouter un digest e-mail |
| Planification | `routes/console.php` planifie déjà les purges et l'expiration des devis | Ajouter deux exécutions quotidiennes |
| Actions de traitement | Aucune | Créer une page Relances avec actions |
| Anti-doublon | Aucun | Ajouter une table de suivi des rappels |

## 3. Décisions fonctionnelles

### 3.1 Destinataires

- **Commercial propriétaire** du compte : reçoit ses propres relances.
- **Administrateurs actifs** : reçoivent un récapitulatif global de toutes les relances.
- **Compte sans propriétaire** : seuls les administrateurs sont notifiés.
- **Propriétaire inactif** : le compte bascule vers les administrateurs.
- Un administrateur qui est aussi propriétaire ne reçoit qu'un seul digest, sans doublon.

### 3.2 Canaux

- **Notification Filament en base** : visible dans la cloche, avec un lien vers la page Relances.
- **E-mail digest** : un seul e-mail par utilisateur et par échéance, listant les comptes concernés.
- Si l'e-mail échoue, la notification in-app est conservée et l'e-mail peut être retenté.

### 3.3 Rythme : veille + jour J

| Exécution | Heure par défaut | Comptes concernés | Notification |
|---|---|---|---|
| `day_before` | 17:00 | `next_follow_up_at` tombe le lendemain | « Relances de demain » |
| `due_day` | 08:00 | `next_follow_up_at` aujourd'hui ou déjà dépassé | « Relances du jour » |

Règles :

- Pas de rappel quotidien répété pour un compte resté en retard : le rappel `due_day` n'est envoyé qu'une fois par échéance.
- Si le cron a été manqué, le prochain passage `due_day` rattrape les comptes en retard une seule fois.
- Si `next_follow_up_at` est modifié, la nouvelle échéance génère de nouveaux rappels ; les anciens restent dans l'historique.
- Les comptes au statut **Inactif** sont exclus par défaut.
- Les comptes supprimés (soft delete) sont exclus.

### 3.4 Traitement d'une relance

Depuis la page Relances :

- **Marquer comme relancé** : renseigne `last_contacted_at`, efface `next_follow_up_at`, crée une activité `CrmActivityType::FollowUp` avec le compte rendu.
- **Reporter** : choisit une nouvelle date (+3 j, +7 j, +14 j ou date personnalisée) et met à jour `next_follow_up_at`.
- **Ouvrir la fiche** : accès direct au compte.
- Les actions sont soumises aux policies : un commercial ne traite que ses comptes, un admin traite tout.

## 4. Architecture proposée

```text
Scheduler (17:00 / 08:00)
        │
        ▼
crm:send-follow-up-reminders --stage=day-before|due-day
        │
        ▼
FollowUpReminderService
        │  sélectionne les comptes dus
        │  résout les destinataires (propriétaire + admins)
        │  crée les lignes de suivi (anti-doublon)
        ▼
FollowUpNotifier
        ├── Notification Filament (database)
        └── FollowUpDigestMail (e-mail)
        │
        ▼
Marquage database_sent_at / mail_sent_at
```

La page Relances et les notifications pointent vers la même source de vérité : les comptes dont `next_follow_up_at` est dû.

## 5. Modèle de données

### 5.1 Nouvelle table `crm_follow_up_reminders`

Une ligne par **compte + destinataire + échéance + étape**.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | bigint | Clé primaire |
| `crm_account_id` | FK `crm_accounts` | Compte concerné, suppression en cascade |
| `user_id` | FK `users` | Destinataire, suppression en cascade |
| `due_at` | timestamp | Copie de `next_follow_up_at` au moment du rappel |
| `stage` | string(20) | `day_before` ou `due_day` |
| `database_sent_at` | timestamp nullable | Envoi in-app effectué |
| `mail_sent_at` | timestamp nullable | Envoi e-mail effectué |
| `attempts` | unsigned tinyint | Nombre de tentatives |
| `last_error` | text nullable | Dernière erreur d'envoi |
| `created_at` / `updated_at` | timestamps | Suivi |

Contraintes et index :

- `unique(crm_account_id, user_id, due_at, stage)` : garantit l'anti-doublon.
- Index sur `(user_id, stage)` pour les digests.
- Index sur `due_at` pour les rattrapages et le nettoyage.

### 5.2 Enum `FollowUpReminderStage`

- `DayBefore = 'day_before'`
- `DueDay = 'due_day'`

Avec libellés, titres de notification et couleurs :

- `day_before` : « Relances de demain », couleur `info`.
- `due_day` : « Relances du jour », couleur `warning` ou `danger` si retard.

### 5.3 Modèle `CrmFollowUpReminder`

- Relations `account()` et `user()`.
- Casts `due_at`, `database_sent_at`, `mail_sent_at`, `stage`.
- Scopes : `pendingDatabase()`, `pendingMail()`, `forStage()`.
- Méthode `isComplete()` : les deux canaux sont envoyés.
- Aucun champ modifié sur `crm_accounts` : la date de relance reste la seule source de vérité.

### 5.4 Migration

Nom proposé : `2026_10_21_120000_create_crm_follow_up_reminders_table.php`.

## 6. Moteur d'envoi

### 6.1 Commande Artisan

```bash
php artisan crm:send-follow-up-reminders --stage=day-before
php artisan crm:send-follow-up-reminders --stage=due-day
```

Options :

| Option | Rôle |
|---|---|
| `--stage=day-before|due-day` | Obligatoire en production ; étape à traiter |
| `--dry-run` | Affiche les comptes et destinataires sans rien écrire ni envoyer |
| `--user=ID` | Limite à un utilisateur, pour test ou renvoi ciblé |
| `--force` | Ignore l'anti-doublon (à réserver au support) |
| `--retry` | Retente les canaux en échec des derniers jours |

Sortie attendue :

```text
Relances de demain : 12 compte(s), 4 destinataire(s), 12 rappel(s) créé(s).
Notification in-app : 4 envoyée(s), 0 échec(s).
E-mail : 4 envoyé(s), 0 échec(s).
```

### 6.2 Sélection des comptes

- `day_before` : `whereDate('next_follow_up_at', now()->addDay()->toDateString())`.
- `due_day` : `next_follow_up_at <= now()->endOfDay()`.
- Exclure `AccountStatus::Inactive` si `crm.follow_ups.exclude_inactive` est vrai.
- Eager loading : `owner`, `contacts` (contact principal), éventuellement `opportunities` pour le contexte.
- Traitement par `chunkById(100)` pour éviter les pics mémoire.

### 6.3 Résolution des destinataires

Pour chaque compte :

```text
utilisateurs actifs où role = admin
OU id = owner_id (si le propriétaire est actif)
```

- Si `owner_id` est nul ou pointe vers un utilisateur inactif, seuls les admins restent.
- Déduplication par `user_id` avant l'envoi du digest.
- Les admins reçoivent tous les comptes, y compris ceux des commerciaux : c'est le récapitulatif global demandé.
- Les commerciaux ne reçoivent que leurs comptes.

### 6.4 Anti-doublon et idempotence

1. Pour chaque triplet `(compte, utilisateur, échéance, étape)`, créer la ligne avec `firstOrCreate` ou `insertOrIgnore`.
2. Ne jamais recréer une ligne si elle existe déjà.
3. Envoyer uniquement les canaux dont le timestamp est nul.
4. Marquer `database_sent_at` et `mail_sent_at` après succès.
5. En cas d'échec : incrémenter `attempts`, stocker `last_error`, laisser le timestamp nul pour retry.
6. La contrainte unique et `withoutOverlapping()` / `onOneServer()` protègent contre les courses.

### 6.5 Gestion des erreurs

- Un échec e-mail ne bloque pas la notification in-app.
- Les erreurs sont journalisées via `report($exception)`.
- Le prochain passage peut retenter les e-mails en échec des `retry_days` derniers jours (7 par défaut).
- Si un compte a été modifié entre la sélection et l'envoi, revérifier que `next_follow_up_at` correspond toujours à `due_at` avant d'envoyer ; sinon ignorer la ligne.

## 7. Notifications

### 7.1 Notification Filament

Classe : `App\Notifications\FollowUpDigest`.

Contenu :

- Titre : « Relances de demain (3) » ou « Relances du jour (5) ».
- Corps : les 5 premiers comptes, puis « + N autres ».
- Format d'une ligne : `• ACME — 22/10 à 09:00 (en retard de 2 j)`.
- Icône : `heroicon-o-arrow-path` (veille) ou `heroicon-o-exclamation-triangle` (jour J / retard).
- Couleur : `info` la veille, `warning` le jour J, `danger` si au moins un compte est en retard.
- Action : « Ouvrir mes relances » vers la page Relances, avec le filtre d'étape.

La notification reste dans la cloche Filament et est marquée lue/non lue par le mécanisme natif.

### 7.2 E-mail digest

Classe : `App\Mail\FollowUpDigestMail`.

Vue : `resources/views/emails/follow-up-digest.blade.php`, dans le style de `quote-status.blade.php`.

Contenu :

- En-tête C2ME et titre selon l'étape.
- Tableau : compte, carnet, statut, propriétaire, échéance, téléphone, contact principal.
- Lien « Ouvrir la fiche » pour chaque compte.
- Lien global vers la page Relances.
- Pied de page « Notification automatique — back-office C2ME ».

### 7.3 Pourquoi un digest et pas une notification par compte

- Évite de saturer la cloche et la boîte mail.
- Un administrateur avec 40 relances reçoit un seul message.
- Les rappels restent actionnables grâce au lien vers la page Relances.
- Le détail par compte reste disponible dans la page.

## 8. Page « Relances » dédiée

### 8.1 Page Filament

- Classe : `App\Filament\Pages\FollowUps`.
- URL : `/admin/relances`.
- Groupe de navigation : **CRM**.
- Icône : `heroicon-o-arrow-path`.
- Badge de navigation : nombre de relances dues pour l'utilisateur.
- Titre : « Relances à traiter ».

### 8.2 Tableau

Colonnes :

| Colonne | Détail |
|---|---|
| Compte | Nom + ville, lien vers la fiche |
| Carnet | Badge `CrmBook` |
| Catégorie | Badge ou texte |
| Statut | Badge `AccountStatus` |
| Propriétaire | Nom, visible pour les admins |
| Échéance | Date/heure, rouge si en retard |
| Dernier contact | Date |
| Téléphone | Lien `tel:` |
| Contact principal | Nom + e-mail/téléphone |

Filtres :

- Carnet.
- Statut.
- Propriétaire (admin uniquement).
- Période : « En retard », « Aujourd'hui », « Cette semaine », « Toutes les relances dues ».
- Recherche sur le nom, la ville, le code, le SIRET.

Tri par défaut : échéance la plus ancienne d'abord.

### 8.3 Actions

| Action | Comportement |
|---|---|
| **Marquer comme relancé** | Modale avec compte rendu optionnel ; met `last_contacted_at = now()`, `next_follow_up_at = null`, crée une activité `FollowUp` liée au compte et à l'utilisateur |
| **Reporter** | Modale +3 j / +7 j / +14 j / date personnalisée ; met à jour `next_follow_up_at` ; conserve l'historique des rappels |
| **Ouvrir la fiche** | Lien vers la ressource compte correspondante |
| **Modifier** | Accès à l'édition complète selon policy |

Actions groupées :

- Marquer plusieurs comptes comme relancés.
- Reporter plusieurs comptes à la même date.

### 8.4 Scoping et policies

- Commercial : uniquement les comptes dont il est propriétaire.
- Admin : tous les comptes.
- Vérifier `CrmAccountPolicy::view` et `update` avant chaque action.
- Les comptes inactifs sont masqués par défaut, avec un filtre « Afficher les inactifs » pour les admins.

### 8.5 Intégration dashboard

- Rendre la stat « Relances dues » cliquable vers `/admin/relances`.
- Le badge de navigation et la stat utilisent la même requête `followUpDue()` + scoping.

## 9. Planification

Dans `routes/console.php` :

```php
Schedule::command('crm:send-follow-up-reminders --stage=day-before')
    ->dailyAt(config('crm.follow_ups.day_before_time', '17:00'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('crm:send-follow-up-reminders --stage=due-day')
    ->dailyAt(config('crm.follow_ups.due_day_time', '08:00'))
    ->withoutOverlapping()
    ->onOneServer();
```

Ajouter une condition `when(fn () => config('crm.follow_ups.enabled'))` pour pouvoir désactiver les rappels sans modifier le code.

Fuseau horaire : `config('app.timezone')` vaut déjà `Europe/Paris`.

## 10. Configuration

Ajouter dans `config/crm.php` :

```php
'follow_ups' => [
    'enabled' => (bool) env('CRM_FOLLOW_UPS_ENABLED', true),
    'day_before_time' => env('CRM_FOLLOW_UPS_DAY_BEFORE_TIME', '17:00'),
    'due_day_time' => env('CRM_FOLLOW_UPS_DUE_DAY_TIME', '08:00'),
    'mail_enabled' => (bool) env('CRM_FOLLOW_UPS_MAIL', true),
    'exclude_inactive' => true,
    'retry_days' => 7,
    'max_notification_items' => 5,
    'batch_size' => 100,
],
```

Variables `.env` documentées :

```dotenv
CRM_FOLLOW_UPS_ENABLED=true
CRM_FOLLOW_UPS_DAY_BEFORE_TIME=17:00
CRM_FOLLOW_UPS_DUE_DAY_TIME=08:00
CRM_FOLLOW_UPS_MAIL=true
```

## 11. Tests

### 11.1 Tests du moteur

Fichier : `tests/Feature/FollowUpReminderTest.php`.

- Un compte dû demain génère un rappel `day_before` au propriétaire.
- Un compte dû aujourd'hui génère un rappel `due_day` au propriétaire.
- Un compte en retard est rattrapé une seule fois.
- Aucun rappel avant la fenêtre (J-1 / jour J).
- Aucun doublon si la commande est relancée.
- Une nouvelle échéance après report génère un nouveau rappel.
- Un admin reçoit le récapitulatif global.
- Un commercial ne reçoit que ses comptes.
- Un compte sans propriétaire notifie les admins.
- Un propriétaire inactif bascule vers les admins.
- Un compte inactif ou supprimé ne génère rien.
- `--dry-run` ne crée ni ligne ni notification.
- Les canaux in-app et e-mail sont bien envoyés (`Notification::fake`, `Mail::fake`).
- Un échec e-mail laisse la notification in-app et permet un retry.

### 11.2 Tests de la page

Fichier : `tests/Feature/FollowUpPageTest.php`.

- Un admin voit toutes les relances.
- Un commercial ne voit que les siennes.
- Un commercial ne peut pas traiter le compte d'un autre.
- « Marquer comme relancé » met à jour le compte et crée une activité.
- « Reporter » met à jour `next_follow_up_at`.
- Le badge de navigation reflète le nombre de relances dues.
- La page est accessible depuis le panneau et renvoie 200.

### 11.3 Vérifications

```bash
php artisan test --filter=FollowUp
vendor/bin/pint --test
composer types:check
```

Objectif : conserver la CI verte (Pint, PHPStan 0 erreur, tous les tests).

## 12. Documentation

- `README.md` : section « Relances automatiques » (comportement, horaires, destinataires, commande manuelle).
- `README.md` : ajouter les variables `.env` et les deux entrées de planification.
- `AGENTS.md` : ajouter `php artisan crm:send-follow-up-reminders --stage=due-day --dry-run`.
- `TACHES_CRM.md` : cocher la tâche « Relances automatiques » et lister les fichiers ajoutés.
- Ce plan : passer l'état à « implémenté » une fois terminé, comme pour la recherche globale.

## 13. Étapes de mise en œuvre

### Phase 1 — Socle de données

- [x] Créer la migration `crm_follow_up_reminders`.
- [x] Créer l'enum `FollowUpReminderStage`.
- [x] Créer le modèle `CrmFollowUpReminder` avec relations, casts et scopes.
- [x] Ajouter la configuration `crm.follow_ups`.

### Phase 2 — Moteur d'envoi

- [x] Créer `FollowUpReminderService` (sélection, destinataires, anti-doublon).
- [x] Créer `FollowUpNotifier` (digest in-app + e-mail).
- [x] Créer la notification `FollowUpDigest`.
- [x] Créer le mailable `FollowUpDigestMail` et sa vue.
- [x] Créer la commande `crm:send-follow-up-reminders`.
- [x] Gérer `--dry-run`, `--user`, `--force`, `--retry` et les erreurs.

### Phase 3 — Planification et page Relances

- [x] Planifier `day-before` à 17:00 et `due-day` à 08:00.
- [x] Créer la page `FollowUps` et son tableau.
- [x] Implémenter les actions « Marquer comme relancé » et « Reporter ».
- [x] Ajouter le badge de navigation et le lien depuis la stat du dashboard.
- [x] Vérifier le scoping admin/commercial via les policies.

### Phase 4 — Tests et documentation

- [x] Écrire les tests du moteur et de la page.
- [x] Vérifier Pint, PHPStan et la suite complète.
- [x] Mettre à jour `README.md`, `AGENTS.md` et `TACHES_CRM.md`.
- [x] Passer ce plan en « implémenté ».

## 14. Points de vigilance

- **Fuseau horaire** : `next_follow_up_at` est stocké en base ; l'application est en `Europe/Paris`. Ne pas comparer avec des dates UTC dans les requêtes.
- **Heure de la relance** : le rappel du jour J part à 08:00, même si `next_follow_up_at` est fixé à 14:00. C'est le comportement attendu d'un digest quotidien.
- **Changement de date** : si un utilisateur reporte une relance après l'envoi de la veille, la nouvelle échéance doit générer ses propres rappels ; l'anti-doublon est basé sur le couple `due_at` + `stage`.
- **Admins** : le récapitulatif global peut être volumineux. Limiter le corps de la notification aux 5 premiers comptes et laisser l'e-mail afficher la liste complète.
- **E-mails** : un échec SMTP ne doit jamais annuler la notification in-app.
- **RGPD** : la table ne stocke que des identifiants de comptes et d'utilisateurs. Prévoir une purge des lignes de plus de 12 mois si la table grossit.
- **Performance** : index sur `next_follow_up_at` déjà présent ; utiliser `chunkById` et éviter les notifications individuelles.

## 15. Critères d'acceptation

- [x] Un compte dû demain déclenche un rappel la veille à 17:00.
- [x] Un compte dû aujourd'hui déclenche un rappel le jour J à 08:00.
- [x] Un compte en retard est rattrapé une seule fois, sans répétition quotidienne.
- [x] Le propriétaire reçoit ses relances ; les admins reçoivent le récapitulatif global.
- [x] Un compte sans propriétaire ou avec propriétaire inactif notifie les admins.
- [x] Aucun doublon même si la commande est relancée.
- [x] La notification in-app et l'e-mail contiennent un lien vers la page Relances.
- [x] « Marquer comme relancé » efface l'échéance et crée une activité.
- [x] « Reporter » met à jour l'échéance et génère de nouveaux rappels.
- [x] Le commercial ne voit et ne traite que ses comptes.
- [x] `--dry-run` ne modifie rien.
- [x] Pint, PHPStan et les tests passent.

## 16. Hors périmètre (évolutions futures)

- Rappels basés sur `CrmActivity.due_at` / `remind_at`.
- Préférences de notification par utilisateur (canaux, heures, désactivation).
- Rappel quotidien répété des retards.
- Notifications temps réel par broadcast.
- Rappels SMS ou push PWA.
- Personnalisation du contenu par carnet.

Ces évolutions pourront réutiliser `FollowUpReminderService` et la table de suivi.
