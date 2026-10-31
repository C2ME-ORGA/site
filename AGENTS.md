# AGENTS.md — C2ME

## Vue d'ensemble

Application Laravel 13 + Filament 5 pour le CRM et le site vitrine de C2ME.

- Site public : pages de contenu, demandes de devis et de contact.
- Back-office : `/admin` (Filament), comptes, contacts, activités, affaires, devis, relances.
- Mode terrain : `/terrain`, page autonome sans Livewire, utilisable hors ligne (IndexedDB + service worker).

L'interface, les libellés et les commentaires sont en français.

## Démarrage

- Le site est servi par Laravel Herd sur `https://c2me.test` ; ne pas lancer de serveur web pour le développement courant.
- PHP, Composer et Node passent par Herd lorsque le site est isolé (`herd php`, `herd composer`).
- Sous PowerShell, utiliser `npm.cmd` si l'exécution des scripts npm est désactivée.

Commandes utiles :

```bash
composer setup          # installation complète
npm run dev             # Vite
npm run build           # assets de production
php artisan migrate
php artisan test
composer ci:check      # Pint + PHPStan + Pest, comme la CI
vendor/bin/pint
vendor/bin/phpstan analyse --memory-limit=1G
```

Jeu de démonstration pour les tests manuels :

```bash
php artisan db:seed --class=DemoDataSeeder   # idempotent, ne supprime rien
```

- Il crée comptes, contacts, affaires, activités (dont photos privées), devis, relances, e-mails, demandes du site, demandes de devis avec plans et événements de calendrier.
- Comptes de test : `demo.commercial@c2me.fr` et `demo.be@c2me.fr`, mot de passe `password` (jamais réinitialisé si le compte existe déjà).

Sauvegarde de la base (voir `docs/sauvegarde-restauration.md`) :

```bash
php artisan backup:run --only-db   # archive chiffrée dans storage/app/backups
php artisan backup:list
php artisan backup:monitor
php artisan backup:clean
```

- Les archives ne sont jamais commitées ni servies publiquement ; en production, `BACKUP_ARCHIVE_PASSWORD` est obligatoire.
- En local, MySQL tourne dans Docker : `mysqldump` n'est pas sur l'hôte. Utiliser `docker exec` ou `DB_DUMP_BINARY_PATH` pour tester une sauvegarde.

## Tests

### Tests PHP

```bash
php artisan test
php artisan test --filter=FieldMode
php artisan test --filter=FieldAttachment
```

Sous PowerShell, éviter `--filter="A|B"` : lancer les filtres séparément.

### Tests end-to-end (Playwright)

```bash
npx playwright install chromium
npm run test:e2e
```

- Configuration : `playwright.config.js`.
- Tests : `tests/e2e/*.spec.js`.
- Le serveur de test utilise une base SQLite dédiée `database/e2e.sqlite`, recréée à chaque lancement (`tests/e2e/prepare-db.mjs`, `php artisan migrate:fresh`, `tests/e2e/seed.php`).
- Ne jamais pointer ces tests vers la base de développement : `migrate:fresh` supprime les données.
- L'authentification est faite une seule fois par `tests/e2e/auth.setup.js` et réutilisée via `tests/e2e/.auth/admin.json` (ignoré par Git).
- Le serveur est lancé avec `php -S` plutôt qu'`artisan serve` : sous Windows, `artisan serve` ne transmet pas les variables `DB_*` au processus PHP enfant.
- Compte de test : `admin@e2e.test` / `password`.

Couverture actuelle : onglets mobiles et thème, rechargement hors ligne, création d'activité hors ligne, conflit et résolution, upload de photo, tournée et itinéraires, session expirée, invite PWA et version du cache.

## Mode terrain

### Fichiers clés

| Fichier | Rôle |
| --- | --- |
| `resources/views/field/index.blade.php` | Page `/terrain`, styles, onglets, dialogue d'installation |
| `public/js/field-mode.js` | Snapshot, file d'opérations, conflits, photos, tournée, thème |
| `public/js/c2me-pwa.js` | Service worker, invite d'installation, version du cache |
| `public/sw.js` | Cache de l'app shell et navigation hors ligne |
| `app/Http/Controllers/FieldModeController.php` | Snapshot, activités, synchronisation, photos |
| `app/Models/CrmActivityAttachment.php` | Photos privées et purge des fichiers |
| `tests/Feature/FieldModeTest.php` | Synchronisation, conflits, reprise, session |
| `tests/Feature/FieldAttachmentTest.php` | Upload, droits, purge, back-office |
| `tests/e2e/` | Parcours navigateur Playwright |

### IndexedDB (`c2me-field`, version 3)

- `snapshot` : comptes et contacts pour le hors ligne.
- `activities` : activités mises en cache.
- `ops` : file d'opérations en attente, en conflit ou en erreur.
- `photos` : blobs JPEG compressés en attente d'envoi.
- `meta` : curseur de snapshot et tournée du jour.

Toute évolution de structure impose d'incrémenter `DB_VERSION` dans `field-mode.js` et de conserver les stores existants dans `onupgradeneeded`.

### Synchronisation

- `POST /terrain/sync` traite des lots d'opérations (`create_activity`, `update_activity`, `update_account`, `update_contact`).
- `client_uuid` rend les créations et les uploads idempotents.
- `base_updated_at` détecte les conflits ; ils ne sont jamais supprimés silencieusement.
- Reprise avec backoff 5 s, 30 s, 2 min, 10 min.
- Une session expirée conserve la file et affiche un bandeau de reconnexion.

### Photos

- Compression navigateur en JPEG (1600 px max) avant stockage IndexedDB.
- 5 photos maximum par activité, 5 Mo maximum après compression.
- Stockage privé sur le disque `local`, jamais dans `public/`.
- Lecture uniquement via `GET /terrain/attachments/{attachment}` (authentifiée et contrôlée par policy).
- Suppression des fichiers avec l'activité et lors de la purge de rétention.

### Tournée

- Sélection manuelle persistée pour la journée.
- Réinitialisation automatique au changement de date.
- Itinéraires Google Maps découpés par tronçons de 10 étapes (destination + 9 waypoints).

### Interface

- Onglets fixes en bas sur mobile (`Comptes`, `Tournée`, `Données`) ; sur desktop, les trois panneaux restent visibles.
- Cibles tactiles d'au moins 44 px sur mobile, focus visible, labels ARIA et navigation clavier des onglets.
- Thème clair/sombre : préférence système par défaut, bascule manuelle mémorisée dans `localStorage` (`c2me-field-theme`).
- Invite PWA via `beforeinstallprompt`, avec report de 14 jours (`c2me-field-install-dismissed`).

## Calendrier

- Flux ICS privé par utilisateur : `GET /calendrier/{token}.ics`, jeton stocké dans `users.calendar_token`.
- Le jeton et l'URL sont des secrets porteurs : ne pas les journaliser, permettre leur régénération.
- `App\Services\IcsCalendar` exporte les activités planifiées non terminées, les relances de comptes et les événements personnels, avec rappels, lieu et lien CRM.
- Événements personnels : modèle `CalendarEvent`, privés par défaut, partageables avec tous ou une sélection d'utilisateurs (lecture seule pour les destinataires), récurrence hebdomadaire/mensuelle simple.
- Actions Filament sur `/admin/calendar` : nouvel événement, édition au clic, suppression, s'abonner, exporter, régénérer.
- Tests : `tests/Feature/CalendarFeedTest.php` et `tests/Feature/CalendarEventTest.php`.
- Le flux ne doit jamais être mis en cache par le service worker ni par un proxy partagé.

## Accessibilité et RGPD

- Le site public doit conserver le lien d'évitement, `<main id="main" tabindex="-1">` et l'indicateur de focus global (`:focus-visible` dans `resources/css/app.css`).
- Tout formulaire public utilise `x-ui.form-errors` (résumé `role="alert"`) et `x-ui.privacy-notice` (mention RGPD + lien vers `/confidentialite`), avec `aria-required`, `aria-invalid` et `aria-describedby` sur les champs en erreur.
- Contrastes : ne pas descendre sous `text-ink-700/70` pour du texte, utiliser `brand-600` (assombri) pour les libellés et `brand-700` pour les liens sur fond clair. Le texte blanc ne se pose que sur `bg-brand-600` ou plus foncé.
- Les actions « Exporter les données (RGPD) » et « Anonymiser » d'un contact sont réservées aux administrateurs et journalisées ; ne jamais écrire de donnée personnelle effacée dans le journal d'audit.
- Documentation : `docs/accessibilite.md` (audit et limites connues) et `docs/rgpd-registre.md` (registre des traitements et procédure des droits).
- Tests : `tests/Feature/AccessibilityTest.php` et `tests/Feature/ContactPrivacyTest.php`.

## Conventions

- Ne pas committer, pousser ou ouvrir de pull request sans demande explicite.
- Tests PHP en Pest, assertions en français lorsque c'est pertinent.
- Factories : `CrmActivityFactory` crée un contact (et donc éventuellement un compte) si `crm_contact_id` n'est pas forcé à `null`.
- Les fichiers clients (photos, plans, pièces jointes) restent sur un disque privé et passent par une route authentifiée.
- Toute modification du cache ou des assets du service worker impose d'incrémenter `VERSION` dans `public/sw.js` et de vérifier la version affichée dans l'onglet Données.
- Les réponses API du mode terrain ne doivent jamais être mises en cache par le service worker.
- Les migrations doivent rester compatibles avec SQLite et MySQL.
- Les archives de sauvegarde ne sont jamais commitées ni placées dans `public/` ; en production elles sont chiffrées (`BACKUP_ARCHIVE_PASSWORD`) et copiées hors site.

## Avant de livrer

1. `composer ci:check` (Pint + PHPStan level 7 + Pest, commande exacte de la CI)
2. `npm run test:e2e` si le mode terrain est touché
3. `node --check` sur les fichiers JS modifiés
4. Rafraîchir la preview `/terrain` et vérifier mobile (375–390 px) et desktop, console vide
5. Mettre à jour `plan_mode_terrain.md` et `TACHES_CRM.md`
