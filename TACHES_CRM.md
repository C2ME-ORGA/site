# CRM C2ME — Liste de tâches

> État au 23/10/2026 — branche `feature/crm`.
> Qualité : Pint OK · PHPStan niveau 7 (0 erreur, tests inclus via `pest-plugin-phpstan`). 213 tests / 873 assertions.

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

**Priorité 1 — usage quotidien sur le terrain**
- [ ] Créer une activité depuis le terrain (appel, visite, note, tâche) avec échéance et rattachement au compte/contact.
- [ ] Éditer hors ligne les coordonnées du compte et des contacts (téléphone, e-mail, adresse, notes) avec file de synchronisation.
- [ ] Actions rapides : appeler, envoyer un SMS, écrire un e-mail, ouvrir l'adresse dans Plans.
- [ ] Trier et filtrer par carnet, statut, relance à venir et activités en retard ; tri par date de relance ou distance.
- [ ] Mettre en tête de liste les activités du jour et les retards.

**Priorité 2 — fiabilité de la synchronisation**
- [ ] Détecter les conflits : exploiter `base_updated_at` (envoyé mais actuellement ignoré) et signaler les modifications concurrentes.
- [ ] Synchronisation incrémentale (`updated_since`) au lieu de recharger tout le snapshot ; supprimer la limite arbitraire de 50 activités par compte.
- [ ] Reprise automatique (retry avec backoff) et conservation des erreurs de synchronisation par élément.
- [ ] Gérer l'expiration de session hors ligne : conserver le snapshot et la file sans perte, puis reconnecter.
- [ ] Bouton « Vider le cache » avec confirmation et affichage de l'espace utilisé.

**Priorité 3 — richesse fonctionnelle**
- [ ] Photos et pièces jointes prises sur le terrain, stockées localement puis envoyées à la synchronisation.
- [ ] Notes vocales (au minimum pièce jointe audio, idéalement retranscription).
- [ ] Signature client sur devis / bon de réception.
- [ ] Géolocalisation : « autour de moi », distance, itinéraire multi-étapes de la tournée.
- [ ] Mode « tournée du jour » avec progression et prochaine étape.
- [ ] Recherche accents-insensible étendue aux contacts, affaires et devis.

**Priorité 4 — ergonomie mobile et qualité**
- [ ] Refonte mobile : cibles tactiles ≥ 44 px, navigation par onglets, en-tête compact, mode sombre vérifié.
- [ ] Installation PWA guidée (invite, icônes, écran de démarrage) et indicateur de version du cache.
- [ ] Accessibilité : contrastes, focus, labels ARIA, navigation clavier.
- [ ] Tests : snapshot (scoping admin/commercial), conflits, reprise après erreur, quota IndexedDB, parcours hors ligne.

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

## Proposition de développement supplémentaire

- [ ] **Amélioration du mode terrain** : édition hors ligne, création d'activités, actions rapides, conflits de synchronisation, photos et tournée — détail en section 7.
- [x] Recherche globale Filament inter-carnets (comptes, contacts, affaires, devis).
- [x] Relances automatiques : notifications/rappels à partir de `next_follow_up_at` — implémenté (voir section 8).
- [x] Tableau de bord enrichi : CA signé, taux de conversion, pipeline par carnet — implémenté (voir section 9).
- [x] Export CSV des comptes/affaires et impression des fiches — implémenté (voir section 10).
- [x] Notifications temps réel (broadcast) pour les nouveaux e-mails — implémenté (voir section 11).
- [x] Visionneuse de logs dans le panel pour le suivi technique — implémenté (voir section 12).
- [x] Gestion dédiée des tags (création/renommage/fusion) avec couleurs, badges, filtres par carnet et outils de nettoyage des doublons.
- [ ] Journal d'audit des modifications sensibles (statuts, suppressions, envois).
- [ ] Signatures e-mail configurables par utilisateur.
- [ ] Support OAuth Microsoft 365 / Google Workspace en plus d'IMAP par mot de passe.
- [ ] Sauvegarde/restauration documentée de la base et des fichiers.
- [ ] Accessibilité et revue RGPD finale avant mise en production.