# Plan de développement : amélioration du mode terrain

> **État : phase 1 implémentée (30/10/2026). Phases 2 à 4 planifiées.**
> Branche cible : `feature/crm`.
> Périmètre retenu : priorités 1 et 2, avec photos rattachées aux activités et tournée manuelle.
> Décisions validées : résolution manuelle des conflits, photos compressées localement puis synchronisées, tournée ordonnée manuellement avec itinéraire Google Maps.

## 1. Objectif

Transformer `/terrain` en un véritable outil de travail hors ligne :

- créer des activités (appel, visite, note, tâche) sans réseau ;
- modifier les coordonnées d'un compte ou d'un contact hors ligne ;
- retrouver rapidement les actions du jour et les retards ;
- lancer les actions rapides (appel, SMS, e-mail, itinéraire) ;
- synchroniser sans perte, avec reprise automatique ;
- détecter et résoudre les modifications concurrentes ;
- ajouter des photos aux activités ;
- préparer une tournée du jour et ouvrir l'itinéraire correspondant.

## 2. État des lieux

| Élément | Existant | À faire |
|---|---|---|
| Page `/terrain` | Page autonome, mise en cache par le service worker | Refonte de l'interface et des interactions |
| Snapshot | Tous les comptes du périmètre, 50 activités max par compte | Synchronisation incrémentale et pagination des activités |
| File locale | IndexedDB `queue`, clé `activity_id` | File d'opérations génériques, clé `op_id` |
| Création hors ligne | Impossible | Activités avec identifiant client idempotent |
| Édition compte/contact | Lecture seule | Édition hors ligne avec file de synchronisation |
| Conflits | `base_updated_at` envoyé mais ignoré | Comparaison serveur, réponse 409 et résolution guidée |
| Reprise | Nouvel essai au retour du réseau uniquement | Backoff, compteur de tentatives, erreurs par opération |
| Session expirée | Perte potentielle de la file | Conserver snapshot et file, reconnecter sans effacer |
| Photos | Aucune table ni stockage | Table d'attachements, compression et upload différé |
| Tournée | Aucune | Sélection manuelle, ordre, itinéraire Google Maps |
| Cache | Bouton « Effacer les données » simple | Espace utilisé, confirmation renforcée si file en attente |

## 3. Décisions fonctionnelles

### 3.1 Périmètre de la première livraison

- Création et édition hors ligne.
- Actions rapides.
- Filtres et tri.
- Synchronisation fiable avec conflits et reprise.
- Photos d'activité.
- Tournée manuelle avec itinéraire.

La refonte mobile complète, la PWA guidée, la signature client, les notes vocales et l'optimisation automatique de tournée restent hors périmètre immédiat.

### 3.2 Conflits

Résolution manuelle guidée :

1. le client envoie `base_updated_at` ;
2. si la version serveur diffère, le serveur répond `409` avec la version serveur ;
3. l'utilisateur choisit :
   - conserver sa version locale ;
   - prendre la version serveur ;
   - fusionner champ par champ.
4. l'opération reste dans la file tant qu'elle n'est pas résolue.

### 3.3 Photos

- Rattachement à une activité.
- Sélection par appareil photo ou galerie.
- Compression locale avant stockage IndexedDB.
- Upload après obtention de l'identifiant serveur de l'activité.
- Stockage privé côté serveur, jamais exposé directement.
- Consultation via une route authentifiée.

### 3.4 Tournée

- Sélection manuelle des comptes.
- Ordre modifiable par l'utilisateur.
- Persistance locale de la sélection et de l'ordre.
- Ouverture d'un itinéraire multi-étapes Google Maps.
- Pas de géolocalisation dans cette phase.

## 4. Architecture cible

```text
/terrain (page autonome)
        │
        ├── IndexedDB
        │     ├── accounts
        │     ├── contacts
        │     ├── activities
        │     ├── ops        (file d'opérations)
        │     ├── photos     (blobs compressés)
        │     └── meta       (curseurs, cache, tournée)
        │
        ├── GET  /terrain/snapshot?updated_since=…
        ├── GET  /terrain/activities?updated_since=…&after_id=…
        ├── POST /terrain/sync
        ├── POST /terrain/activities/{activity}/attachments
        └── GET  /terrain/attachments/{attachment}
```

### 4.1 Identifiants et idempotence

- Les entités existantes conservent leur identifiant numérique.
- Une activité créée hors ligne reçoit un `client_uuid` généré par le navigateur.
- Le serveur ajoute `crm_activities.client_uuid` (nullable, unique).
- Une opération rejouée avec le même `client_uuid` retourne l'enregistrement existant au lieu d'en créer un second.
- La réponse de synchronisation associe `op_id` → identifiant serveur → `updated_at`.

### 4.2 Base de données

Nouvelle migration :

- `crm_activities.client_uuid` : `uuid` nullable, unique.
- `crm_activity_attachments` :
  - `id`
  - `crm_activity_id` (FK, cascade)
  - `client_uuid` (nullable, unique, idempotence)
  - `path`
  - `original_name`
  - `mime_type`
  - `size`
  - `created_at`

Le disque utilisé pour les photos reste privé (`local` ou disque dédié), comme les plans de demandes de devis.

### 4.3 Endpoints

#### `GET /terrain/snapshot`

Paramètre `updated_since` optionnel.

Retourne :

- `generated_at`
- `full` (premier chargement ou non)
- `accounts` avec contacts
- `deleted` (identifiants de comptes/contacts supprimés depuis la date)
- `cursor`

Les activités ne sont plus incluses dans ce payload.

#### `GET /terrain/activities`

Paramètres :

- `updated_since`
- `after_id`
- `limit` (200 par défaut, plafonné)

Retourne les activités paginées, avec `next_cursor`. Le client boucle jusqu'à épuisement.

Cela supprime la limite arbitraire de 50 activités par compte.

#### `POST /terrain/sync`

Charge utile :

```json
{
  "operations": [
    {
      "op_id": "uuid",
      "type": "create_activity|update_activity|update_account|update_contact",
      "entity_id": 12,
      "client_uuid": "uuid",
      "payload": {},
      "base_updated_at": "2026-10-30T10:00:00+00:00"
    }
  ]
}
```

Réponse par opération :

```json
{
  "op_id": "uuid",
  "status": "ok|conflict|error",
  "entity_type": "activity",
  "entity_id": 42,
  "client_uuid": "uuid",
  "updated_at": "2026-10-30T10:05:00+00:00",
  "server": {},
  "errors": {}
}
```

- Une opération en erreur de validation retourne `422` pour l'opération concernée.
- Un conflit retourne `409` pour l'opération concernée.
- Les autres opérations du lot continuent d'être traitées.

#### `POST /terrain/activities/{activity}/attachments`

- `multipart/form-data`
- validation MIME : JPEG, PNG, WebP
- taille maximale : 5 Mo
- autorisation basée sur la policy de l'activité
- retourne les métadonnées de l'attachement

#### `GET /terrain/attachments/{attachment}`

- téléchargement ou affichage authentifié
- autorisation basée sur la policy de l'activité liée

## 5. File d'opérations côté navigateur

### 5.1 Structure

```js
{
  op_id: "uuid",
  type: "update_activity",
  entity_type: "activity",
  entity_id: 42,
  client_uuid: null,
  payload: { body: "…", completed_at: "…" },
  base_updated_at: "…",
  queued_at: 1761820000000,
  attempts: 0,
  next_attempt_at: 1761820000000,
  status: "pending|conflict|error",
  last_error: null
}
```

### 5.2 Reprise

- Backoff exponentiel : 5 s, 30 s, 2 min, 10 min.
- Les erreurs sont conservées par opération.
- Une opération en conflit n'est jamais supprimée automatiquement.
- Le bouton « Synchroniser » force une tentative immédiate.
- Le retour du réseau déclenche la reprise.

### 5.3 Session expirée

- Une réponse `401` ou `419` ne vide pas la file.
- Un bandeau invite à se reconnecter.
- Après reconnexion, la page reprend la file existante.
- Le snapshot reste consultable hors ligne.

## 6. Interface terrain

### 6.1 Liste des comptes

- Recherche accents-insensible.
- Filtres : carnet, statut, relance à venir, activités en retard.
- Tri : relance, nom, statut.
- Bloc « Aujourd'hui » en tête de liste.
- Bloc « En retard » distinct.

### 6.2 Fiche compte hors ligne

- Coordonnées éditables : téléphone, mobile, e-mail, adresse, notes.
- Contacts éditables : nom, fonction, téléphone, mobile, e-mail, notes.
- Bouton d'enregistrement local.
- Indicateur « Modifié localement » tant que l'opération n'est pas synchronisée.

### 6.3 Activités

- Création : type, sujet, contact, échéance, compte rendu.
- Modification : compte rendu, clôture.
- Ajout de photos.
- Affichage des activités du jour et en retard.

### 6.4 Actions rapides

- Appeler : `tel:`
- SMS : `sms:`
- E-mail : `mailto:`
- Itinéraire : Google Maps avec l'adresse du compte
- Copier l'adresse

### 6.5 Tournée du jour

- Cases de sélection sur les comptes.
- Liste ordonnée.
- Boutons monter/descendre.
- Bouton « Ouvrir l'itinéraire ».
- Réinitialisation explicite.
- Conservation de la sélection pour la journée.

## 7. Gestion du cache

- Affichage de l'espace utilisé via `navigator.storage.estimate()`.
- Bouton « Vider le cache » avec confirmation.
- Si des opérations sont en attente :
  - avertissement explicite ;
  - proposer d'exporter la file en JSON avant suppression ;
  - suppression uniquement après confirmation renforcée.
- Le cache de l'application (service worker) reste distinct des données métier.

## 8. Sécurité et autorisations

- Chaque opération de synchronisation est contrôlée par les policies existantes.
- Un commercial ne peut modifier que ses comptes, contacts et activités.
- Les identifiants envoyés ne suffisent pas : l'appartenance est revérifiée côté serveur.
- Les photos sont validées par MIME et taille, puis stockées sur un disque privé.
- Les téléchargements de photos passent par une route authentifiée.
- Limitation de débit sur les endpoints de synchronisation et d'upload.
- Protection CSRF conservée.

## 9. Tests

### Tests PHP

- Snapshot complet et incrémental.
- Scoping admin/commercial.
- Pagination des activités.
- Création d'activité idempotente avec `client_uuid`.
- Modification de compte/contact autorisée et refusée.
- Conflit détecté avec `base_updated_at`.
- Résolution d'un conflit et rejeu d'une opération.
- Validation des lots d'opérations.
- Upload de photo : MIME, taille, autorisation.
- Téléchargement de photo : autorisation.
- Suppression d'une activité : suppression des photos associées.
- Purge des activités : purge des photos.

### Tests navigateur

- Premier chargement en ligne puis rechargement hors ligne.
- Création d'activité hors ligne puis synchronisation.
- Modification de compte hors ligne puis synchronisation.
- Conflit simulé et résolution manuelle.
- Retour du réseau après erreur.
- Session expirée sans perte de file.
- Photos hors ligne puis upload.
- Tournée : sélection, ordre, itinéraire.

## 10. Phasage

### Phase 1 — Socle hors ligne

- Refonte IndexedDB.
- File d'opérations.
- Snapshot v2 et activités paginées.
- Création d'activité hors ligne.
- Édition compte/contact hors ligne.
- Actions rapides.
- Filtres et tri.

### Phase 2 — Fiabilité

- `client_uuid` et idempotence.
- Détection de conflits.
- Résolution manuelle.
- Backoff et reprise.
- Gestion de session expirée.
- Gestion du cache et export de la file.

### Phase 3 — Photos et tournée

- Table `crm_activity_attachments`.
- Compression et stockage local.
- Upload différé.
- Affichage authentifié.
- Tournée manuelle et itinéraire Google Maps.

### Phase 4 — Finitions

- Refonte mobile et accessibilité.
- Tests de parcours complets.
- Documentation `AGENTS.md`.
- Mise à jour de `TACHES_CRM.md`.

## 11. Points de vigilance

- La taille du snapshot peut devenir importante : privilégier les endpoints paginés.
- Le service worker ne doit jamais mettre en cache les réponses API.
- Le changement de structure IndexedDB impose une migration de version.
- Le numéro de version du cache PWA doit être incrémenté à chaque livraison.
- Les opérations en conflit ne doivent jamais être supprimées silencieusement.
- Les photos ne doivent pas être stockées en base64 dans IndexedDB si des Blob sont disponibles.
- Les coordonnées et notes modifiées hors ligne doivent être validées avec les mêmes règles que le back-office.
