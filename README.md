# C2ME - CRM Module

## Overview

C2ME is a Laravel Livewire-based CRM application managing clients industriels, prescripteurs, and building companies. This module handles accounts, contacts, opportunities (affaires), devis, activities, and e-mail management.

## Architecture

### Core Resources

| Resource | Description |
|----------|-------------|
| **Accounts** (`/admin/prescriber-accounts`, `/admin/industrial-accounts`, `/admin/building-company-accounts`) | Gestion des comptes clients, éclatés par carnet (prescripteurs, industriels, bâtiment). |
| **Recherche globale** (`/admin/global-search`) | Recherche unifiée inter-carnets sur les comptes, contacts, affaires et devis. |
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

- `php artisan test` → **158 tests, 608 assertions** ✅
- `composer types:check` → **0 erreur** (173 fichiers)
- `vendor/bin/pint` → **passé** sur tous les fichiers modifiés

### 4. Seeders & Démonstration

- **`DemoCrmSeeder`** : comptes des 3 carnets, contacts, affaires, devis, activités, messages
- **Usages** : `php artisan db:seed --class=DemoCrmSeeder` (hors `DatabaseSeeder` de production)

### 5. RGPD / Rétention

- Commandes de purge selon `config/crm.retention.*` (prospects, activités, e-mails)
- Planification via tâche cron (non incluse dans cette démo, à ajouter en production)
- Anonymisation des données personnelles à la suppression

### 5. Commandes Artisan

| Commande | Description |
|----------|-------------|
| `php artisan crm:purge-prospects` | Purge des prospects inactifs selon `crm.retention.prospects_months` |
| `php artisan crm:purge-activities` | Purge des activités anciennes selon `crm.retention.activities_months` |
| `php artisan crm:purge-emails` | Purge des e-mails anciens selon `crm.email.retention_months` |

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

### 7. Déploiement & Maintenance

- **PWA / Service Worker** : mise en cache de l'application shell pour mode hors réseau
- **IndexedDB** : stockage des fiches, contacts et activités pour consultation/édition hors réseau
- **File d'attente de synchronisation** : envoi des modifications à la reconnexion (conflits à définir)
- **Indicateur d'état hors ligne / synchronisation** : affiché dans l'interface

### 8. Développement Futur (Proposé)

- Relances automatiques à partir de `next_follow_up_at`
- Tableau de bord enrichi (CA signé, taux de conversion, pipeline par carnet)
- Export CSV des comptes/affaires et impression des fiches
- Notifications temps réel (broadcast) pour les nouveaux e-mails
- Gestion dédiée des tags (création/renommage/fusion)
- Journal d'audit des modifications sensibles
- Signatures e-mail configurables par utilisateur
- Support OAuth Microsoft 365 / Google Workspace en plus d'IMAP par mot de passe
- Documentation de sauvegarde/restauration
- Accessibilité et revue RGPD finale avant mise en production

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
php artisan test       # 148 tests, 584 assertions
```

### Structure des dossiers importants

- `app/Filament/Resources/` : ressources Filament (comptes, contacts, affaires, devis, e-mails)
- `app/Policies/` : policies d'autorisation
- `app/Models/` : modèles Eloquent (EmailMessage, CrmAccount, CrmContact, CrmOpportunity, Quote)
- `config/crm.php` : paramètres de rétention, etc.
- `database/factories/` : factories pour les tests
- `tests/Feature/` : suite de tests (PolicyTest, QuoteStatusTest, ComposeEmailTest, CrmAdminPagesTest)

---

**Projet créé dans le cadre du CRM C2ME – branche `feature/crm`.**