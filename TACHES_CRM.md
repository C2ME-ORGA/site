# CRM C2ME — Liste de tâches

> État au 21/10/2026 — branche `feature/crm`.
> Qualité : Pint OK · PHPStan niveau 7 (0 erreur, tests inclus via `pest-plugin-phpstan`). 157 tests / 607 assertions.

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

## Proposition de développement supplémentaire

- [ ] **Amélioration du mode terrain** : édition hors ligne, création d'activités, actions rapides, conflits de synchronisation, photos et tournée — détail en section 7.
- [x] Recherche globale Filament inter-carnets (comptes, contacts, affaires, devis).
- [ ] Relances automatiques : notifications/rappels à partir de `next_follow_up_at`.
- [ ] Tableau de bord enrichi : CA signé, taux de conversion, pipeline par carnet.
- [ ] Export CSV des comptes/affaires et impression des fiches.
- [ ] Notifications temps réel (broadcast) pour les nouveaux e-mails.
- [ ] Gestion dédiée des tags (création/renommage/fusion) au lieu de la saisie libre.
- [ ] Journal d'audit des modifications sensibles (statuts, suppressions, envois).
- [ ] Signatures e-mail configurables par utilisateur.
- [ ] Support OAuth Microsoft 365 / Google Workspace en plus d'IMAP par mot de passe.
- [ ] Sauvegarde/restauration documentée de la base et des fichiers.
- [ ] Accessibilité et revue RGPD finale avant mise en production.