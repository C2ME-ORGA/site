# C2ME - CRM Module

## Overview

C2ME is a Laravel Livewire-based CRM application managing clients industriels, prescripteurs, and building companies. This module handles accounts, contacts, opportunities (affaires), devis, activities, and e-mail management.

## Architecture

### Core Resources

| Resource | Description |
|----------|-------------|
| **Accounts** (`/admin/prescriber-accounts`, `/admin/industrial-accounts`, `/admin/building-company-accounts`) | Gestion des comptes clients, éclatés par carnet (prescripteurs, industriels, bâtiment). |
| **Tableau de bord** (`/admin`) | KPI commerciaux : CA signé, conversion, pipeline par carnet, objectif mensuel, top affaires et devis à relancer. |
| **Recherche globale** (`/admin/global-search`) | Recherche unifiée inter-carnets sur les comptes, contacts, affaires et devis. |
| **Export & impression** | Export CSV des comptes et affaires (filtré par carnet et par droits), fiches compte et affaire imprimables au format A4. |
| **Contacts** | Contacts associés à un compte (commerciaux, décideurs). |
| **Opportunities (Affaires)** | Étapes de vente (Nouvelle, Qualification, Proposition, Acceptée, Perdue). |
| **Quotes (Devis)** | Parcours de demande de devis (devis → devis building → devis découpe laser → devis final). |
| **Activities** | Relances, appels, tâches associés aux comptes. |
| **E-mails** | Boîte de réception, envoi, réponses avec pièces jointes. |
| **Mailboxes** | Configuration des boîtes e-mail IMAP/SMTP. |

### User Roles

| Role | Permissions |
|------|-------------|
| **Admin** | Accès complet à toutes les ressources, suppression de tout, création/modification de tout. |
| **Commercial** | Accès en lecture/écriture aux ressources qu'il own (ses propres comptes, ses propres affaires, ses propres devis, ses propres contacts). Voir mais pas supprimer les ressources des autres. |

### Relationships

- **Account → Opportunities** (1:n) via `crm_account_id`
- **Account → Contacts** (1:n) via `crm_account_id`
- **Quote → Account** (1:n) via `crm_account_id`
- **EmailMessage → Account/Contact** (n:1) via `crm_account_id` / `crm_contact_id`
- **Activity → Account** (n:1) via `crm_account_id`

## Features Implemented (as of v1.0)

### 1. E-mail Module

- **RichText editing** via Filament's `RichEditor` (TipTap) for message bodies
- **CRM contact selection** in addition to free-text address input
  - Searchable multi-select with filtering (`do_not_contact` excluded)
  - De-duplication of addresses at send time
- **Attachments** support
  - `FileUpload` field (multiple, 10 Mo max, noms préservés)
  - Stockage sur disque `local` (`storage/app/private/mail-attachments`)
  - Nettoyage automatique après envoi
- **Réponse** (`Répondre`) via modal préremplie
  - Destinataire d'origine
  - Objet `Re: …` (décodage MIME)
  - Citation du message original
  - En-tête `In-Reply-To`/`References`
- **Mise en forme du corps** : nettoyage scripts/événements, `target="_blank"`/`rel="noopener noreferrer"` sur les liens
- **Pagination** en haut du tableau de réception
- **Bulk action** « Marquer comme lu » dans la barre d'outils

### 2. Policies & Authorization

- Policies définies pour : `EmailMessage`, `CrmAccount`, `CrmContact`, `CrmOpportunity`, `Quote`
- Vérifications fines : admin vs commercial, propriété des enregistrements (`owner_id`)
- Tests unitaires couvrant toutes les politiques (voir `tests/Feature/PolicyTest.php`)

### 3. Tests

- `php artisan test` → **200 tests, 847 assertions** ✅
- `composer types:check` → **0 erreur**
- `vendor/bin/pint` → **passé** sur tous les fichiers modifiés

### 4. Seeders & Démonstration

- **`DemoCrmSeeder`** : comptes des 3 carnets, contacts, affaires, devis, activités, messages
- **Usages** : `php artisan db:seed --class=DemoCrmSeeder` (hors `DatabaseSeeder` de production)

### 5. RGPD / Rétention

- Registre des traitements et procédure des droits : `docs/rgpd-registre.md`
- Commandes de purge selon `config/crm.retention.*` (prospects, activités, e-mails)
- Planification via tâche cron (non incluse dans cette démo, à ajouter en production)
- Anonymisation des données personnelles à la suppression

### 5. Commandes Artisan

| Commande | Description |
|----------|-------------|
| `php artisan crm:purge-prospects` | Purge des prospects inactifs selon `crm.retention.prospects_months` |
| `php artisan crm:purge-activities` | Purge des activités anciennes selon `crm.retention.activities_months` |
| `php artisan crm:purge-emails` | Purge des e-mails anciens selon `crm.email.retention_months` |
| `php artisan crm:send-follow-up-reminders --stage=due-day` | Envoie le digest des relances dues (voir section 10) |
| `php artisan backup:run --only-db` | Crée une archive chiffrée de la base (voir section 13) |
| `php artisan backup:list` | Liste les archives présentes sur le disque de sauvegarde |
| `php artisan backup:monitor` | Vérifie l'âge et le volume des sauvegardes |
| `php artisan backup:clean` | Applique la politique de rétention |

### 6. Configuration `.env`

| Variable | Description |
|----------|-------------|
| `IMAP_HOST` | Hôte du serveur IMAP |
| `IMAP_PORT` | Port IMAP (993 par défaut) |
| `IMAP_USERNAME` | Nom d'utilisateur IMAP |
| `IMAP_PASSWORD` | Mot de passe IMAP |
| `SMTP_HOST` | Hôte du serveur SMTP |
| `SMTP_PORT` | Port SMTP (587 par défaut) |
| `SMTP_USERNAME` | Nom d'utilisateur SMTP |
| `SMTP_PASSWORD` | Mot de passe SMTP |
| `LOGO_DEV` | Key publishable pour les logos Développeurs |
| `crm.retention.prospects_months` | Mois de conservation des prospects (entier) |
| `crm.retention.activities_months` | Mois de conservation des activités (entier) |
| `crm.email.retention_months` | Mois de conservation des e-mails (entier) |
| `CRM_FOLLOW_UPS_ENABLED` | Active ou désactive les rappels de relance (`true` par défaut) |
| `CRM_FOLLOW_UPS_DAY_BEFORE_TIME` | Heure du rappel la veille (`17:00` par défaut) |
| `CRM_FOLLOW_UPS_DUE_DAY_TIME` | Heure du rappel le jour J (`08:00` par défaut) |
| `CRM_FOLLOW_UPS_MAIL` | Active l'envoi des digests e-mail (`true` par défaut) |
| `CRM_DASHBOARD_MONTHLY_TARGET` | Objectif mensuel de CA devis en euros (`0` désactive la jauge) |

### 7. Déploiement & Maintenance

- **PWA / Service Worker** : mise en cache de l'application shell pour mode hors réseau
- **IndexedDB** : stockage des fiches, contacts et activités pour consultation/édition hors réseau
- **File d'attente de synchronisation** : envoi des modifications à la reconnexion (conflits à définir)
- **Indicateur d'état hors ligne / synchronisation** : affiché dans l'interface

### 8. Développement Futur (Proposé)

- Relances sur les activités (`due_at` / `remind_at`), en complément des relances comptes
- Notifications temps réel (broadcast) pour les nouveaux e-mails
- Gestion dédiée des tags (création/renommage/fusion)
- Journal d'audit des modifications sensibles
- Signatures e-mail configurables par utilisateur
- Support OAuth Microsoft 365 / Google Workspace en plus d'IMAP par mot de passe
- Revue d'accessibilité du back-office Filament et du mode terrain

### 9. Recherche globale inter-carnets

La page **Recherche globale** (`/admin/global-search`, groupe CRM) permet de retrouver en une seule saisie des enregistrements appartenant aux trois carnets.

- **Périmètre** : comptes, contacts, affaires et devis.
- **Moteur** : `App\Filament\Services\GlobalSearchService` (requêtes `LIKE` sur les champs pertinents, `with('account')` pour éviter le N+1).
- **Page Filament** : `App\Filament\Pages\GlobalSearch`, vue `resources/views/filament/pages/global-search.blade.php`.
- **Fonctionnement** :
  - recherche live (`wire:model.live.debounce.300ms`), à partir de 2 caractères ;
  - résultats groupés par type, avec icône et compteur ;
  - filtres par carnet, statut d'affaire et statut de devis, bouton de réinitialisation ;
  - 10 résultats par groupe, dépliables via « Voir les N résultats » ;
  - terme recherché surligné (`<mark>`) ;
  - navigation clavier (↑ / ↓, Entrée pour ouvrir).
- **Sécurité** : chaque résultat est filtré par `Gate::allows('view', …)` ; un commercial ne voit jamais les fiches dont il n'est pas propriétaire.
- **Liens** : les comptes pointent vers la ressource de leur carnet ; les contacts et affaires vers le compte rattaché ; les devis vers leur fiche d'édition.

### 10. Relances automatiques

Les rappels sont calculés à partir de `crm_accounts.next_follow_up_at` par `App\Services\FollowUpReminderService`.

- **Planification** : rappel la veille (`day_before`) à 17:00 et le jour J (`due_day`) à 08:00 dans `routes/console.php`. La planification peut être désactivée avec `CRM_FOLLOW_UPS_ENABLED=false`.
- **Destinataires** : le propriétaire du compte et les administrateurs actifs. Un compte sans propriétaire, ou dont le propriétaire est inactif, est envoyé uniquement aux administrateurs.
- **Canaux** : notification Filament in-app et e-mail digest. Les deux envois sont suivis séparément dans `crm_follow_up_reminders`, ce qui permet de réessayer un e-mail sans renvoyer la notification in-app.
- **Anti-doublon** : contrainte unique sur `(crm_account_id, user_id, due_at, stage)`. Un rappel en retard est rattrapé une seule fois.
- **Périmètre** : comptes uniquement, hors comptes inactifs par défaut. Un administrateur peut les afficher avec le filtre « Tous les comptes ».
- **Page** : `/admin/relances` (groupe CRM) avec badge de navigation, filtres par carnet/statut/propriétaire/période et actions « Marquer comme relancé », « Reporter », « Ouvrir la fiche » et « Modifier ». Le commercial ne voit que les comptes dont il est propriétaire.
- **Commande** : `php artisan crm:send-follow-up-reminders --stage=day-before|due-day`. Options : `--dry-run`, `--user=`, `--force` (renvoie sans créer de doublon), `--retry` (reprend les échecs récents).

### 11. Tableau de bord enrichi

Le tableau de bord (`/admin`) est une page Filament personnalisée (`App\Filament\Pages\Dashboard`) avec un filtre de période global, conservé dans l'URL et en session.

- **Période** : mois, trimestre, année ou période personnalisée. Par défaut, l'année civile en cours. Les bornes sont calculées dans le fuseau `APP_TIMEZONE` (Europe/Paris).
- **CA signé — devis** : somme de `quotes.amount_ht` des devis `accepted` dont `accepted_at` est dans la période.
- **CA gagné — affaires** : somme de `crm_opportunities.amount_estimated` des affaires `won` dont `won_at` est dans la période. Les deux CA sont affichés séparément pour éviter de compter deux fois un même dossier.
- **Taux de conversion** : devis acceptés parmi les devis dont `sent_at` est dans la période. Affiche `—` si aucun devis n'a été envoyé.
- **Panier moyen** : CA devis / nombre de devis acceptés, avec le panier moyen des affaires gagnées en description.
- **Pipeline par carnet** : affaires ouvertes uniquement (`nouveau`, `qualifie`, `etude`, `devis_envoye`, `negociation`), en brut et pondéré par probabilité. Le graphique indique le nombre d'affaires et la part de chaque carnet.
- **Graphiques Apache ECharts** (`elemind/filament-echarts`, plugin enregistré dans le panel) :
  - **CA sur 12 derniers mois** : barres des devis acceptés et courbe des affaires gagnées, mois par mois ;
  - **Pipeline par carnet** : barres groupées brut/pondéré ;
  - **Top 5 comptes par pipeline** : barres horizontales, montants formatés en euros ;
  - **Taux de conversion des devis** : jauge 0–100 % sur la période sélectionnée ;
  - **Activités par type** : anneau des activités enregistrées sur la période.
  - Les montants sont formatés en français (`Intl.NumberFormat`), les axes et infobulles sont harmonisés et chaque graphique gère son état vide.
- **Top 5 des affaires ouvertes** : triées par montant décroissant, avec lien vers la fiche compte.
- **Devis à relancer ou expirés** : devis `sent` dont `valid_until` arrive dans les 7 jours, devis `sent` dépassés et devis `expired`, avec lien vers la fiche devis.
- **Objectif mensuel** : compare le CA devis du mois en cours à `CRM_DASHBOARD_MONTHLY_TARGET`. Une valeur à `0` masque la jauge et affiche « Objectif non défini ».
- **Scoping** : un commercial ne voit que ses devis et affaires ; un administrateur voit l'ensemble. Les devis sans propriétaire ne sont visibles que par les administrateurs.
- **Calculs** : centralisés dans `App\Services\DashboardMetricsService` et `App\Support\DashboardPeriod`.

### 12. Export CSV et impression des fiches

Les exports et impressions respectent le carnet courant, les filtres/recherches du tableau et les policies (`owner_id` pour les commerciaux, accès global pour les administrateurs).

- **Comptes (CSV)** : bouton « Exporter les comptes (CSV) » sur les listes des trois carnets. Le fichier reprend les colonnes communes, les champs spécifiques au carnet et les étiquettes.
- **Affaires (CSV)** : bouton « Exporter les affaires (CSV) » sur les listes de comptes (affaires des comptes filtrés) et sur l'onglet Affaires d'une fiche compte.
- **Format** : CSV UTF-8 avec BOM, séparateur `;`, dates `jj/mm/aa` et montants décimaux à virgule, pour une ouverture directe dans Excel.
- **Fiche compte** : action « Imprimer la fiche » sur la fiche compte. La vue A4 inclut coordonnées, suivi commercial, champs spécifiques, contacts, affaires, devis et dernières activités.
- **Fiche affaire** : action « Imprimer » sur chaque ligne d'affaire. La vue A4 inclut l'affaire, le compte, le contact, les devis liés et les activités.
- **Sécurité** : `GET /admin/print/comptes/{account}` et `GET /admin/print/affaires/{opportunity}` sont protégés par `auth`, l'utilisateur actif et `Gate::authorize('view', …)`.
- **Code** : `App\Services\CrmCsvExporter`, `App\Http\Controllers\PrintController`, vues `resources/views/print/*`.

### 13. Sauvegarde et restauration

La base est sauvegardée chaque nuit dans `storage/app/backups` avec
`spatie/laravel-backup` ; les fichiers (photos, plans, pièces jointes, PDF)
sont archivés séparément par une commande `tar` à planifier dans le cron
système (voir la procédure).

- **Commandes** : `backup:run --only-db`, `backup:list`, `backup:monitor`, `backup:clean`.
- **Planification** : `backup:clean` à 01:15, `backup:run --only-db` à 01:30, `backup:monitor` à 02:00 ; `BACKUP_ENABLED=false` suspend les trois.
- **Chiffrement** : AES-256 du ZIP via `BACKUP_ARCHIVE_PASSWORD`, obligatoire en production.
- **Rétention** : 7 jours intégraux, 30 jours en quotidien, 12 semaines, 6 mois, 2 ans, plafond 2 Go.
- **Configuration** : `config/backup.php`, disque `backups` de `config/filesystems.php`.
- **Procédure complète** (sauvegarde des fichiers, restauration, exercice trimestriel, dépannage) : `docs/sauvegarde-restauration.md`.

### 15. En-têtes de pages (Page Header)

- **Plugin** : `mortalkiller/filament-page-header` `^2.4`, enregistré dans `AdminPanelProvider` en mode **compact** (l'en-tête se réduit au défilement et reste épinglé).
- **Schémas partagés** dans `app/Filament/Resources/{Ressource}/Schemas/{Modèle}Header.php`, découverts automatiquement par le trait `HasPageHeader` :
  - `CrmAccountHeader` couvre les trois carnets (les ressources filles héritent d'`AccountResource`) ;
  - `QuoteHeader`, `CrmActivityHeader`, `ContactRequestHeader`, `QuoteRequestHeader`, `EmailMessageHeader`, `UserHeader`, `EmailMailboxHeader`, `CrmTagHeader`.
- **Contenu** : titre, description, initiales ou logo d'entreprise (comptes), badges de statut avec couleurs, métadonnées avec icônes (commercial, coordonnées, dates) et actions natives existantes (modifier, supprimer, imprimer, transitions de devis…).
- **Compact** : avatar, titre, badges et actions restent visibles ; seules les métadonnées clés (commercial, prochaine relance, montant, échéance) sont conservées.
- **Pages concernées** : vue et édition des fiches (comptes, devis, activités, demandes de contact et de devis, e-mails, utilisateurs, boîtes e-mail, étiquettes).
- **Tests** : `tests/Feature/PageHeaderTest.php`.

### 14. Accessibilité et RGPD

- **Accessibilité** : lien d'évitement, `<main id="main">`, focus visible global, menus `aria-expanded`/`aria-controls` fermables par `Échap`, formulaires avec `aria-required`/`aria-invalid`/`aria-describedby` et résumé d'erreurs `role="alert"`, galerie en boutons `aria-pressed`, visionneuse avec restitution du focus. Contrastes texte ≥ 4,5:1 (texte atténué `ink-700/70`, `brand-600` assombri en `#0b7c7c`, boutons en `brand-600`). Audit et limites connues : `docs/accessibilite.md`.
- **RGPD** : registre des traitements, sous-traitants, cookies et procédure des droits dans `docs/rgpd-registre.md` ; durées de purge dans `docs/crm-rgpd.md`.
- **Droits des personnes** : sur la fiche compte, onglet Contacts (administrateurs), boutons « Exporter les données (RGPD) » (JSON : contact, compte, affaires, devis, activités, e-mails) et « Anonymiser » (nom, coordonnées et notes effacés, historique conservé). Les deux opérations sont journalisées dans le journal d'audit.
- **Mention formulaires** : `x-ui.privacy-notice` affiche la finalité et le lien vers la politique de confidentialité sous les formulaires de contact et de devis.
- **Tests** : `tests/Feature/AccessibilityTest.php`, `tests/Feature/ContactPrivacyTest.php`.

---

## Développement

### Lancer en local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan serve
```

### Tests

```bash
composer types:check   # 0 erreur
vendor/bin/pint        # formatage
php artisan test       # 200 tests, 847 assertions
```

### Structure des dossiers importants

- `app/Filament/Resources/` : ressources Filament (comptes, contacts, affaires, devis, e-mails)
- `app/Http/Controllers/PrintController.php` : fiches compte/affaire imprimables
- `app/Policies/` : policies d'autorisation
- `app/Models/` : modèles Eloquent (EmailMessage, CrmAccount, CrmContact, CrmOpportunity, Quote)
- `app/Services/CrmCsvExporter.php` : génération des exports CSV comptes/affaires
- `config/crm.php` : paramètres de rétention, etc.
- `database/factories/` : factories pour les tests
- `resources/views/print/` : gabarits A4 des fiches compte et affaire
- `tests/Feature/` : suite de tests (PolicyTest, QuoteStatusTest, ComposeEmailTest, CrmExportPrintTest)

---

**Projet créé dans le cadre du CRM C2ME – branche `feature/crm`.**