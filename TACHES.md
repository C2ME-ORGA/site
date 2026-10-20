# CRM C2ME — Liste de tâches

> État au 20/10/2026 — branche `feature/crm`.
> Qualité : Pint OK · PHPStan niveau 7 (0 erreur) · 133 tests / 465 assertions.

## A Faire

### 1. Mode terrain hors ligne
- [ ] Ajouter un manifest PWA et un service worker (cache de l'app shell).
- [ ] Stocker les fiches, contacts et activités en IndexedDB pour consultation/édition hors réseau.
- [ ] File d'attente de synchronisation au retour de connexion (résolution des conflits à définir).
- [ ] Indicateur d'état hors ligne / synchronisation dans l'interface.

### 2. Seeders de démonstration
- [ ] Créer des factories pour `EmailMailbox`, `EmailMessage` et `CrmTag` (aujourd'hui créées à la main dans les tests).
- [ ] `DemoCrmSeeder` : comptes des 3 carnets, contacts, affaires, devis, activités, messages.
- [ ] Brancher un `--class=DemoCrmSeeder` documenté (jamais dans `DatabaseSeeder` de production).

### 3. Finitions du module e-mail
- [ ] Ajouter les pièces jointes à `ComposeEmailAction` (le service `MailboxMailer` les accepte déjà).
- [ ] Répondre depuis l'application avec `In-Reply-To` / `References` et citation du message (remplacer le `mailto:`).
- [ ] Lier un message à une affaire ou un devis (`crm_opportunity_id`, `quote_id` existent mais ne sont pas exposés).
- [ ] Afficher les pièces jointes des messages entrants (actuellement non importées).

### 4. RGPD / rétention
- [ ] Commande de purge des prospects inactifs selon `crm.retention.prospects_months` + anonymisation.
- [ ] Commande de purge des activités selon `crm.retention.activities_months`.
- [ ] Purge des e-mails selon `crm.email.retention_months` (config présente, non utilisée).
- [ ] Planifier ces commandes et documenter la politique de conservation.

### 5. Tests & robustesse
- [ ] Tests dédiés aux policies (scoping admin / commercial sur comptes, devis, e-mails, activités).
- [ ] Tests Livewire de `ComposeEmailAction` (validation, pièces jointes, archivage).
- [ ] Étendre PHPStan aux `tests/` (actuellement seuls `app/`, `bootstrap`, `config`, `database`, `routes` sont analysés).
- [ ] Vérifier la couverture des transitions de devis (`QuoteStatusTest` à compléter sur les cas limites).

### 6. Documentation
- [ ] Créer un `README` du module CRM (architecture, rôles, commandes planifiées).
- [ ] Documenter les variables `.env` spécifiques (IMAP/SMTP, logo.dev, conservation).
- [ ] Ajouter les commandes de vérification dans `AGENTS.md` si absent.

## Proposition de développement supplémentaire

- [ ] Recherche globale Filament inter-carnets (comptes, contacts, affaires, devis).
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
