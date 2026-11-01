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
php artisan db:seed --class=ProjectDemoSeeder # départements, 14 chantiers et 74 photos
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

## Base documentaire

- Panneau Filament séparé `knowledge-base` sur `/aide` (`KnowledgeBasePanelProvider`), même garde d'authentification que `/admin` (utilisateurs actifs uniquement).
- Contenu en Markdown dans `docs/knowledge-base/fr` (une arborescence par locale, trois niveaux maximum, un fichier parent par dossier).
- Le paquet est mis en cache : après une modification d'article, exécuter `php artisan cache:clear`.
- Aide contextuelle : `HasKnowledgeBase::getDocumentation()` sur les ressources et pages renvoie l'identifiant d'article (notation pointée, ex. `crm.devis`).
- Le plugin compagnon est déclaré sur le panneau `admin` (`KnowledgeBaseCompanionPlugin`, bouton latéral + aide en modale).
- Tests : `tests/Feature/KnowledgeBaseTest.php`.

## Accessibilité et RGPD

- Le site public doit conserver le lien d'évitement, `<main id="main" tabindex="-1">` et l'indicateur de focus global (`:focus-visible` dans `resources/css/app.css`).
- Tout formulaire public utilise `x-ui.form-errors` (résumé `role="alert"`) et `x-ui.privacy-notice` (mention RGPD + lien vers `/confidentialite`), avec `aria-required`, `aria-invalid` et `aria-describedby` sur les champs en erreur.
- Contrastes : ne pas descendre sous `text-ink-700/70` pour du texte, utiliser `brand-600` (assombri) pour les libellés et `brand-700` pour les liens sur fond clair. Le texte blanc ne se pose que sur `bg-brand-600` ou plus foncé.
- Les actions « Exporter les données (RGPD) » et « Anonymiser » d'un contact sont réservées aux administrateurs et journalisées ; ne jamais écrire de donnée personnelle effacée dans le journal d'audit.
- Documentation : `docs/accessibilite.md` (audit et limites connues) et `docs/rgpd-registre.md` (registre des traitements et procédure des droits).
- Tests : `tests/Feature/AccessibilityTest.php` et `tests/Feature/ContactPrivacyTest.php`.

## Conventions

- Graphiques : widgets `Elemind\FilamentECharts\Widgets\EChartWidget` dans `app/Filament/Widgets`, options renvoyées par `getOptions()`, formatage des montants via `extraJsOptions()` (`RawJs`). `$pollingInterval` est une propriété **d'instance** (`protected ?string $pollingInterval = null;`), pas `static`.
- En-têtes de pages : `mortalkiller/filament-page-header` en mode compact, schémas partagés dans `app/Filament/Resources/{Ressource}/Schemas/{Modèle}Header.php` et trait `HasPageHeader` sur les pages de vue/édition.
- Réalisations : gérées dans le groupe Filament « Site » (`ProjectCategory`, `Project`, `ProjectImage`) ; photos sur le disque public (`storage/app/public/projects`) avec vignette WebP générée, jamais dans `public/` en direct.
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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
