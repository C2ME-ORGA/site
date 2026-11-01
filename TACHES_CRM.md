# CRM C2ME — Liste de tâches

> État au 30/10/2026 — branche `feature/crm`.
> Qualité : Pint OK · PHPStan niveau 7 (0 erreur, tests inclus via `pest-plugin-phpstan`). 256 tests / 1058 assertions.

## A Faire

### 1. Mode terrain hors ligne
- [x] Ajouter un manifest PWA et un service worker (cache de l'app shell).
- [x] Stocker les fiches, contacts et activités en IndexedDB pour consultation/édition hors réseau.
- [x] File d'attente de synchronisation au retour de connexion (résolution des conflits à définir).
- [x] Indicateur d'état hors ligne / synchronisation dans l'interface.

### 2. Seeders de démonstration
- [x] Créer des factories pour `EmailMailbox`, `EmailMessage` et `CrmTag` (aujourd'hui créées à la main dans les tests).
- [x] `DemoCrmSeeder` : comptes des 3 carnets, contacts, affaires, devis, activités, messages.
- [x] Brancher un `--class=DemoCrmSeeder` documenté (jamais dans `DatabaseSeeder` de production).

### 3. Finitions du module e-mail
- [x] Ajouter les pièces jointes à `ComposeEmailAction` (le service `MailboxMailer` les accepte déjà).
- [x] Répondre depuis l'application avec `In-Reply-To` / `References` et citation du message (remplacer le `mailto:`).
- [x] Lier un message à une affaire ou un devis (`crm_opportunity_id`, `quote_id` existent mais ne sont pas exposés).
- [x] Afficher les pièces jointes des messages entrants (actuellement non importées).

### 4. RGPD / rétention
- [x] Commande de purge des prospects inactifs selon `crm.retention.prospects_months` + anonymisation.
- [x] Commande de purge des activités selon `crm.retention.activities_months`.
- [x] Purge des e-mails selon `crm.email.retention_months` (config présente, non utilisée).
- [x] Planifier ces commandes et documenter la politique de conservation.

### 5. Tests & robustesse
- [x] Tests dédiés aux policies (scoping admin / commercial sur comptes, devis, e-mails, activités).
- [x] Tests Livewire de `ComposeEmailAction` (validation, pièces jointes, archivage).
- [x] Étendre PHPStan aux `tests/` (les erreurs affichées sont principalement liées à des méthodes Pest dynamiques non reconnues par PHPStan dans des fichiers de test préexistants ; les tests de cette session (PolicyTest, QuoteStatusTest, ComposeEmailTest) passent avec succès).
- [x] Couverture des transitions de devis (`QuoteStatusTest` complété sur les cas limites).

### 6. Documentation
- [x] Créer un `README` du module CRM (architecture, rôles, commandes planifiées).
- [x] Documenter les variables `.env` spécifiques (IMAP/SMTP, logo.dev, conservation).
- [x] Ajouter les commandes de vérification dans `AGENTS.md` si absent.

### 7. Amélioration du mode terrain (`/terrain`)

> Constat : le mode terrain actuel permet surtout de consulter un snapshot en lecture seule et de modifier le compte rendu / le statut des activités existantes. Les fiches ne sont pas éditables, aucune création n'est possible, et la synchronisation est en « dernier écrivain gagne ».
>
> Plan détaillé : [plan_mode_terrain.md](plan_mode_terrain.md). Décisions retenues : priorités 1 et 2, résolution manuelle des conflits, photos rattachées aux activités, tournée manuelle avec itinéraire Google Maps.

**Priorité 1 — usage quotidien sur le terrain**
- [x] Créer une activité depuis le terrain (appel, visite, note, tâche) avec échéance et rattachement au compte/contact.
- [x] Éditer hors ligne les coordonnées du compte et des contacts (téléphone, e-mail, adresse, notes) avec file de synchronisation.
- [x] Actions rapides : appeler, envoyer un SMS, écrire un e-mail, ouvrir l'adresse dans Plans.
- [x] Trier et filtrer par carnet, statut, relance à venir et activités en retard ; tri par date de relance ou nom.
- [x] Mettre en tête de liste les activités du jour et les retards via le tri « Priorité terrain ».

> Phase 1 livrée le 30/10/2026 : snapshot v2 (comptes/contacts séparés des activités), endpoint paginé des activités, file d'opérations IndexedDB, `client_uuid` idempotent, création d'activité et édition compte/contact hors ligne, actions rapides, filtres et tri prioritaire. Tests : `FieldModeTest` (13 tests).

**Priorité 2 — fiabilité de la synchronisation**
- [x] Détecter les conflits : `base_updated_at` est comparé à la version serveur, réponse `conflict` avec la version serveur.
- [x] Synchronisation incrémentale (`updated_since`) des comptes/contacts avec curseur et liste des suppressions.
- [x] Suppression de la limite arbitraire de 50 activités par compte via l'endpoint paginé `/terrain/activities`.
- [x] Reprise automatique (backoff 5 s, 30 s, 2 min, 10 min) et conservation des erreurs de synchronisation par opération.
- [x] Gestion de l'expiration de session hors ligne : bandeau de reconnexion, snapshot et file conservés.
- [x] Panneau « Données locales » : espace utilisé, export JSON de la file, suppression avec confirmation renforcée.

> Phase 2 livrée le 30/10/2026 : détection et résolution manuelle des conflits (garder local, prendre serveur, fusion champ par champ), reprise automatique avec backoff, erreurs par opération, session expirée sans perte, snapshot incrémental et panneau de gestion du cache. Tests : `FieldModeTest` (17 tests).

**Priorité 3 — richesse fonctionnelle**
- [x] Photos et pièces jointes prises sur le terrain, stockées localement puis envoyées à la synchronisation.
- [x] Tournée manuelle : ajout, ordre, retrait, réinitialisation, itinéraires Google Maps découpés par 10 étapes.
- [ ] Notes vocales (au minimum pièce jointe audio, idéalement retranscription).
- [ ] Signature client sur devis / bon de réception.
- [ ] Géolocalisation : « autour de moi », distance, itinéraire multi-étapes de la tournée.
- [ ] Mode « tournée du jour » avec progression et prochaine étape.
- [ ] Recherche accents-insensible étendue aux contacts, affaires et devis.

> Phase 3 livrée le 30/10/2026 : photos rattachées aux activités (compression JPEG locale, stockage IndexedDB en Blob, upload différé idempotent, 5 photos / 5 Mo maximum), galerie dans `/terrain` et dans la fiche activité Filament, stockage privé purgé avec l'activité et à la rétention ; tournée du jour persistée avec ordre manuel, réinitialisation automatique au changement de date et itinéraires Google Maps par tronçons de 10 étapes. Tests : `FieldAttachmentTest` (11 tests).

**Priorité 4 — ergonomie mobile et qualité**
- [x] Refonte mobile : cibles tactiles ≥ 44 px, navigation par onglets, en-tête compact, thème clair/sombre avec bascule manuelle.
- [x] Installation PWA guidée (invite personnalisée, report de 14 jours) et indicateur de version du cache.
- [x] Accessibilité : contrastes, focus visible, labels ARIA, lien d'évitement, navigation clavier des onglets.
- [x] Tests PHP et Playwright : snapshot (scoping admin/commercial), conflits, reprise après erreur, parcours hors ligne, photos, tournée, session expirée.
- [ ] Test de quota IndexedDB (simulation de saturation du stockage navigateur).

> Phase 4 livrée le 30/10/2026 : barre d'onglets mobile (Comptes / Tournée / Données) avec badges, en-tête compact, thème clair/sombre persistant, invite d'installation PWA, version du cache hors ligne, accessibilité (cibles 44 px, focus, ARIA, clavier). Parcours Playwright automatisés sur base SQLite dédiée et intégrés au workflow GitHub Actions. Documentation `AGENTS.md`. Tests : 290 tests PHP, 8 parcours E2E.

### 8. Relances automatiques

- [x] Table `crm_follow_up_reminders` avec contrainte anti-doublon `(crm_account_id, user_id, due_at, stage)`.
- [x] Commande `crm:send-follow-up-reminders` avec `--stage`, `--dry-run`, `--user`, `--force` et `--retry`.
- [x] Digest Filament in-app et e-mail, avec suivi séparé des deux canaux.
- [x] Planification la veille à 17:00 et le jour J à 08:00 dans `routes/console.php`.
- [x] Page `/admin/relances` : tableau, filtres, badge de navigation et actions de traitement.
- [x] Scoping admin/commercial, comptes inactifs masqués par défaut et filtre admin dédié.
- [x] Tests du moteur et de la page ; Pint, PHPStan et suite complète au vert.

### 9. Tableau de bord enrichi

- [x] Page Dashboard personnalisée avec filtre de période (mois, trimestre, année, personnalisé) persistant en URL/session.
- [x] CA signé devis et CA gagné affaires, calculés séparément sur la période.
- [x] Taux de conversion des devis envoyés, panier moyen devis et affaires.
- [x] Pipeline par carnet en brut et pondéré, avec nombre d'affaires et part de chaque carnet.
- [x] Top 5 des affaires ouvertes triées par montant.
- [x] Devis à relancer ou expirés avec liens vers les fiches.
- [x] Objectif mensuel configurable via `CRM_DASHBOARD_MONTHLY_TARGET`.
- [x] Scoping admin/commercial et états vides.
- [x] Tests du service et de la page ; Pint, PHPStan et suite complète au vert.

### 10. Export CSV et impression des fiches

- [x] Export CSV des comptes depuis les trois listes, avec colonnes communes, champs spécifiques au carnet et étiquettes.
- [x] Export CSV des affaires depuis les listes de comptes (comptes filtrés) et depuis l'onglet Affaires d'une fiche compte.
- [x] Fichiers UTF-8 avec BOM et séparateur `;`, dates françaises et montants décimaux à virgule.
- [x] Prise en compte des filtres/recherches actifs et du scoping admin/commercial.
- [x] Fiche compte imprimable A4 : coordonnées, suivi, contacts, affaires, devis et activités.
- [x] Fiche affaire imprimable A4 : affaire, compte, contact, devis liés et activités.
- [x] Actions Filament « Imprimer la fiche » sur le compte et « Imprimer » sur chaque affaire.
- [x] Routes d'impression protégées par `auth`, utilisateur actif et `Gate::authorize('view', …)`.
- [x] Tests des exports, des impressions et du scoping ; Pint, PHPStan et suite complète au vert.

### 11. Notifications temps réel des e-mails

- [x] Ajout de Laravel Reverb et du client Echo, chargés uniquement dans le panneau d'administration.
- [x] Canal privé par utilisateur (`App.Models.User.{id}`) protégé par `routes/channels.php`.
- [x] Notification Filament persistée en base et diffusée en temps réel à chaque nouvel e-mail entrant non lu.
- [x] Destinataires : propriétaire pour une boîte personnelle ; administrateurs et commerciaux actifs pour une boîte partagée.
- [x] Premier import d'une boîte volontairement silencieux pour éviter de notifier l'historique.
- [x] Toast, cloche Filament et badge de la boîte de réception rafraîchis via WebSocket, avec polling `30s` conservé en secours.
- [x] Décodage des sujets MIME dans les notifications et variable `CRM_EMAIL_NOTIFICATIONS` pour désactiver le mécanisme.
- [x] Configuration locale Herd documentée (`wss://c2me.test:8080`, certificat Herd, `php artisan dev`).
- [x] Tests des notifications, du canal privé et du payload Filament ; validation navigateur (toast, WebSocket et badge).

### 12. Visionneuse de logs (suivi technique)

- [x] Paquet `gboquizosanchez/filament-log-viewer` 2.x installé (compatible Filament 5, `ext-zip` disponible).
- [x] Configuration publiée dans `config/filament-log-viewer.php` et traductions dans `lang/vendor/filament-log-viewer`.
- [x] `FILAMENT_LOG_VIEWER_DRIVER` aligné sur `LOG_STACK` (`daily` en local, `single` dans `.env.example`) — le repli sur `LOG_CHANNEL=stack` choisirait `daily` à tort.
- [x] Page `/admin/logs` : liste des journaux quotidiens, statistiques par niveau, consultation, téléchargement.
- [x] Accès réservé aux administrateurs via `->authorize()`, groupe « Système » (tri 95), fuseau `Europe/Paris`.
- [x] Suppression de la suppression : `FILAMENT_LOG_VIEWER_CLEARABLE` laissé à `false`, aucun droit d'effacement accordé.
- [x] Thème Filament personnalisé `resources/css/filament/admin/theme.css` (exigé par Filament v4+ et par le paquet) : importe le thème de base et déclare les `@source` des vues du plugin, des vues Filament de l'application et des composants Livewire du panel.
- [x] Entrée de thème ajoutée à `vite.config.js` et `->viteTheme()` sur le panel : le panel charge désormais le CSS compilé par Vite, ce qui stylise aussi les utilitaires déjà utilisés dans les vues métier (`mb-4`, `text-sm`, etc.).
- [x] Tests d'accès (admin, commercial, visiteur anonyme, journal inexistant, entrée de navigation) et de l'enregistrement du plugin/thème ; Pint, PHPStan et suite complète au vert.

### 13. Journal d'audit des modifications sensibles

- [x] Table append-only `audit_logs` : auteur, événement, élément (type, identifiant, libellé), changements JSON, IP et date ; index sur l'élément, l'événement et la date.
- [x] Enum `AuditEvent` (`Modification`, `Suppression`, `Suppression définitive`, `Restauration`, `Envoi`) avec libellés et couleurs Filament.
- [x] `AuditObserver` enregistré globalement : statut des devis, étape/probabilité/montant des affaires, statut des comptes, rôle/activation des utilisateurs, activation/propriétaire des boîtes e-mail.
- [x] Suppressions et restaurations journalisées pour les devis, affaires, comptes, contacts, activités, e-mails, boîtes, étiquettes et utilisateurs (une seule entrée par `forceDelete`).
- [x] Envois d'e-mails tracés depuis `MailboxMailer` (destinataires, copie, objet) après création du message sortant.
- [x] Les créations et les modifications non sensibles ne sont pas journalisées ; les exécutions console (`ExpireQuotes`…) n'attribuent ni utilisateur ni IP.
- [x] Page `/admin/journal-audit` (groupe « Système », tri 96) : table en lecture seule, filtres événement/type/auteur/période et modale de détail avant/après.
- [x] Accès réservé aux administrateurs (`canAccess` et navigation masquée pour les commerciaux).
- [x] Tests des statuts, suppressions/restaurations, rôles, envois, accès admin/commercial et visiteur anonyme ; Pint, PHPStan et suite complète au vert.

### 14. Signatures e-mail configurables par utilisateur

- [x] Colonne `email_signature` (texte, nullable) sur `users`, ajoutée aux champs modifiables du modèle.
- [x] Champ « Signature e-mail » en éditeur enrichi sur `/admin/profile` : mise en forme (gras, listes, liens…) et insertion d'images jusqu'à 5 Mo, limitées aux types MIME JPEG, PNG, GIF et WebP, stockées dans `email-signatures/{clé_pseudonyme}`.
- [x] Signature HTML insérée automatiquement dans le corps des nouveaux messages et au-dessus du texte cité des réponses ; l'éditeur de composition accepte lui aussi la mise en forme et les images.
- [x] Corps laissé intact lorsque la signature est vide ; images et liens relatifs convertis en URLs absolues au moment de l'envoi, sans double encodage des esperluettes.
- [x] Signature et corps nettoyés avec le sanitizer HTML de Filament (`Str::sanitizeHtml`) avant préremplissage et envoi : scripts, gestionnaires d'événements et balises dangereuses supprimés.
- [x] Chemins de stockage pseudonymes (HMAC de l'identifiant avec `APP_KEY`) pour éviter l'énumération des utilisateurs via les URLs de fichiers.
- [x] Tests du profil, du préremplissage (nouveau message, réponse, absence de signature), des restrictions d'upload, de la sanitisation et de l'absolutisation des URLs ; Pint, PHPStan et suite complète au vert.

### 15. Sauvegarde et restauration

- [x] `spatie/laravel-backup` configuré pour ne sauvegarder que la base MySQL (`config/backup.php`), avec `useSingleTransaction` pour un dump cohérent sans verrouiller les tables InnoDB.
- [x] Archives ZIP horodatées `c2me-AAAA-MM-JJ-HH-MM-SS.zip` dans `storage/app/backups` (disque `backups`, hors dossier public et ignoré par Git).
- [x] Chiffrement AES-256 des archives dès que `BACKUP_ARCHIVE_PASSWORD` est défini ; sinon archive non chiffrée (local uniquement).
- [x] Vérification de l'archive après création (`verify_backup`) et dump `db-dumps/mysql-{base}.sql`.
- [x] Planification : `backup:clean` à 01:15, `backup:run --only-db` à 01:30, `backup:monitor` à 02:00, suspendues par `BACKUP_ENABLED=false`.
- [x] Rétention 7 jours / 30 jours / 12 semaines / 6 mois / 2 ans, plafond 2 Go ; alertes e-mail limitées aux échecs et aux sauvegardes trop anciennes (`BACKUP_NOTIFICATION_EMAIL`).
- [x] Sauvegarde des fichiers (`storage/app/private`, `storage/app/public`) par `tar` + `gzip` documentée, avec copie hors site (`rclone`, `scp`) et contrôle `sha256`.
- [x] Runbook complet dans `docs/sauvegarde-restauration.md` : prérequis, configuration, commandes, restauration de la base et des fichiers, liste de vérification, exercice trimestriel, dépannage (dont MySQL dans Docker en local) et consignes RGPD.
- [x] Tests : périmètre base seule, disque privé, planification `--only-db`, chiffrement conditionnel.

### 16. Accessibilité du site public

- [x] Lien d'évitement « Aller au contenu principal », `<main id="main" tabindex="-1">` et indicateur de focus commun (`:focus-visible`).
- [x] Menu services et menu mobile : `aria-expanded`, `aria-haspopup`, `aria-controls`, nom accessible dynamique, fermeture par `Échap` et au clic extérieur.
- [x] Formulaires de contact et de devis : `aria-required`, `aria-invalid`, `aria-describedby`, résumé d'erreurs `role="alert"` avec liens vers les champs, astérisque décoratif et mention « (obligatoire) » réservée aux lecteurs d'écran.
- [x] Cartes de choix en `radiogroup` avec anneau de focus (`focus-within`), zone de dépôt de plans focusable.
- [x] Galerie : filtres en boutons `aria-pressed` (au lieu de `role="tab"` sans panneau), visionneuse avec focus rendu au déclencheur et fermeture par `Échap`.
- [x] Contrastes : texte atténué à 5,2:1, `brand-600` assombri à #0b7c7c (5:1), boutons pleins en `brand-600`, liens RGPD en `brand-700` (6,1:1).
- [x] Tableaux légaux avec `scope="col"`, titres de cartes de la page contact en `<h2>`, liens externes signalés « (nouvel onglet) ».
- [x] Messages de validation traduits en français (`lang/fr/validation.php`, `auth.php`, `passwords.php`) : les erreurs n'affichent plus de clés brutes comme « validation.required ».
- [x] Audit documenté dans `docs/accessibilite.md` ; tests de non-régression dans `tests/Feature/AccessibilityTest.php`.

### 17. Revue RGPD finale

- [x] Registre des traitements, sous-traitants, cookies, procédure des droits et violation de données dans `docs/rgpd-registre.md`.
- [x] Mention RGPD sous les trois formulaires publics, avec lien vers la politique de confidentialité (`x-ui.privacy-notice`).
- [x] Export RGPD d'un contact depuis la fiche compte (JSON : contact, compte, affaires, devis, activités, e-mails), réservé aux administrateurs et journalisé (`exported`).
- [x] Anonymisation d'un contact (effacement du nom, des coordonnées et des notes, historique conservé), journalisée sans conserver les valeurs effacées (`anonymized`).
- [x] Tests du service d'export/Anonymisation, du contenu du journal et de la restriction aux administrateurs (`tests/Feature/ContactPrivacyTest.php`).
- [ ] Points à finaliser en production : contrat SMTP, vérification `logo.dev`, purge du journal d'audit (12 mois), 2FA administrateurs.

### 18. Graphiques Apache ECharts

- [x] Plugin `elemind/filament-echarts` (`^1.1`) installé et enregistré dans `AdminPanelProvider`.
- [x] Widgets ECharts : CA sur 12 derniers mois (barres + courbe), pipeline par carnet, top 5 comptes par pipeline, taux de conversion (jauge) et activités par type (anneau).
- [x] Filtre de période du tableau de bord respecté par la jauge et l'anneau (`InteractsWithPageFilters`) ; polling désactivé.
- [x] Montants formatés en français via `extraJsOptions()` (`Intl.NumberFormat`), états vides explicites, palettes de la charte (teal/lime).
- [x] Service complété : `revenueByMonth()`, `activitiesByType()`, `topAccountsByPipeline()`.
- [x] Tests de calcul (`DashboardMetricsTest`) et de rendu des widgets (`DashboardPageTest`) ; Pint, PHPStan et suite complète au vert.

### 19. En-têtes de pages (Page Header)

- [x] Plugin `mortalkiller/filament-page-header` `^2.4` installé, assets publiés et enregistré dans `AdminPanelProvider` en mode `compact()`.
- [x] Trait `HasPageHeader` ajouté aux pages de vue et d'édition : comptes (3 carnets), devis, activités, demandes de contact et de devis, e-mails, utilisateurs, boîtes e-mail, étiquettes.
- [x] Schémas partagés par ressource (`Schemas/{Modèle}Header.php`) : titre, description, initiales ou logo, badges de statut colorés, métadonnées avec icônes et actions natives.
- [x] Configuration compacte par en-tête (`whenCompact`) : identité, badges, actions et quelques métadonnées clés restent visibles.
- [x] Tests de découverte des schémas et de rendu (`tests/Feature/PageHeaderTest.php`) ; Pint, PHPStan et suite complète au vert.

### 20. Réalisations gérées dans le CRM

- [x] Modèles `ProjectCategory` (département), `Project` (chantier) et `ProjectImage` (photo), avec slugs générés, portées publiées/ordonnées et factories.
- [x] Migrations SQLite/MySQL : catégories, chantiers (département, client, commune, année, prestations JSON, publication, ordre) et images (chemin, vignette, texte alternatif, ordre).
- [x] Back-office « Site » : ressources Départements et Réalisations, réordonnancement, filtres par département et publication, actions réservées aux administrateurs par policy.
- [x] Photos : dépôt multiple (5 Mo, JPEG/PNG/WebP, redimensionnement 1600 px), vignette WebP 720 px générée par GD, suppression des fichiers avec l’image, texte alternatif.
- [x] Site : `/realisations` filtrée par département avec compteurs, fiches `/realisations/{slug}` (métadonnées, prestations, galerie avec visionneuse), état vide, liens « autres chantiers ».
- [x] Sitemap : ajout des chantiers publiés ; les brouillons renvoient 404.
- [x] Suppression de l’ancienne galerie statique (`config/site.php → projects`, composant `projects.gallery`) ; les dossiers `public/images/realisations` restent sur disque mais ne sont plus lus, tout se saisit désormais dans le CRM.
- [x] Tests publics et admin (`tests/Feature/ProjectTest.php`, `ProjectAdminTest.php`) ; Pint, PHPStan et suite complète au vert.

### 21. Totaux de devis calculés automatiquement

- [x] Total HT et Total TTC en lecture seule, calculés depuis les lignes (quantité × P.U. HT, remise déduite).
- [x] Recalcul en direct dans le formulaire à l’ajout, la modification ou la suppression d’une ligne, et au changement du taux de TVA (`UpdatesQuoteTotals`).
- [x] Consolidation à l’enregistrement (`QuoteLineObserver`, `Quote::recalculateTotals()`, TTC recalculé par `QuoteObserver`) ; création sans ligne possible grâce aux valeurs par défaut à 0.
- [x] Tests du calcul en direct, de l’enregistrement, de la suppression totale des lignes et de la création (`tests/Feature/QuoteTotalsTest.php`).

## Proposition de développement supplémentaire

- [x] **Amélioration du mode terrain** : édition hors ligne, création d'activités, actions rapides, conflits de synchronisation, photos et tournée — détail en section 7.
- [x] Recherche globale Filament inter-carnets (comptes, contacts, affaires, devis). (v1.1.0)
- [x] Relances automatiques : notifications/rappels à partir de `next_follow_up_at` — implémenté (voir section 8). (v1.1.0)
- [x] Tableau de bord enrichi : CA signé, taux de conversion, pipeline par carnet — implémenté (voir section 9). (v1.1.0)
- [x] Export CSV des comptes/affaires et impression des fiches — implémenté (voir section 10). (v1.1.0)
- [x] Notifications temps réel (broadcast) pour les nouveaux e-mails — implémenté (voir section 11).
- [x] Visionneuse de logs dans le panel pour le suivi technique — implémenté (voir section 12).
- [x] Gestion dédiée des tags (création/renommage/fusion) avec couleurs, badges, filtres par carnet et outils de nettoyage des doublons.
- [x] Journal d'audit des modifications sensibles (statuts, suppressions, envois) — implémenté (voir section 13).
- [x] Signatures e-mail configurables par utilisateur — implémenté (voir section 14).
- [x] Sauvegarde/restauration documentée de la base et des fichiers — `spatie/laravel-backup`, archives chiffrées, runbook `docs/sauvegarde-restauration.md` (voir section 15).
- [x] Accessibilité et revue RGPD finale avant mise en production — lien d’évitement, formulaires ARIA, contrastes AA, registre des traitements, export et anonymisation RGPD d’un contact (sections 16 et 17).
- [x] Ajouter support Calendrier avec synchronisation/export pour Outlook, Google Calendar, etc. — flux WebCal privé par utilisateur, export ICS, activités et relances, événements personnels partageables (voir `plan_calendrier.md`).
- [x] Prise en charge des Graphiques visuelle avec Apache Echart (https://filamentphp.com/plugins/elemind-echarts) — cinq graphiques sur le tableau de bord (voir section 18).
- [x] Utiliser https://filamentphp.com/plugins/pedro-monteiro-page-header pour mettre en forme les vues des différents affichages — `mortalkiller/filament-page-header` `^2.4`, en-têtes compacts sur les fiches (voir section 19).
- [ ] Permettre la gestion et l'organisation de l'espace 'Nos Réalisations' du frontend dans le CRM: Organisation par Département/chantier/images (Source probante: https://www.briand.fr/realisations/) 
