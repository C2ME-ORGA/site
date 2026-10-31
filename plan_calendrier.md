# Plan de développement : calendrier et synchronisation

> **État : implémenté (31/10/2026).**
> Branche cible : `feature/crm`.

## 1. Objectif

Permettre à chaque utilisateur d'abonner son agenda Google, Outlook ou Apple
Calendrier au CRM, sans OAuth ni application tierce, et d'y ajouter ses propres
événements.

Le flux contient :

- les activités planifiées non terminées (`due_at`) ;
- les relances de comptes (`next_follow_up_at`) en journée entière ;
- les événements personnels créés depuis le calendrier.

## 2. Décisions retenues

- **Flux WebCal + export ICS** plutôt qu'une synchronisation bidirectionnelle
  OAuth : compatible avec tous les agendas, aucune clé API à gérer.
- **Un flux privé par utilisateur** : le scoping reprend les policies existantes
  (admin = tous les comptes, commercial = ses fiches).
- **Jeton secret par utilisateur** (`users.calendar_token`) dans l'URL, car les
  clients calendrier ne peuvent pas s'authentifier par session.
- **Événements enrichis** : rappel (`VALARM`), lieu (adresse du compte) et lien
  vers la fiche CRM dans la description.
- **Événements personnels privés par défaut**, partageables avec tous les
  utilisateurs ou une liste choisie ; les destinataires sont en lecture seule.
- **Récurrence simple** : aucune, hebdomadaire ou mensuelle, avec date de fin
  optionnelle (`RRULE` basique).

## 3. Architecture

### 3.1 Jeton et URL

- `User::ensureCalendarToken()` génère un jeton aléatoire de 48 caractères.
- `User::regenerateCalendarToken()` invalide immédiatement l'ancienne URL.
- `User::calendarFeedUrl()` et `calendarWebcalUrl()` construisent les adresses
  HTTPS et `webcal://`.
- Le jeton est masqué de la sérialisation (`#[Hidden]`).

### 3.2 Flux

- Route publique : `GET /calendrier/{token}.ics`, limitation `60/min`, format du
  jeton validé par expression régulière.
- `CalendarFeedController` refuse les comptes inactifs, renvoie `404` pour un
  jeton inconnu et gère `If-None-Match` (`304`).
- En-têtes : `text/calendar`, `Cache-Control: private, max-age=300`,
  `X-Robots-Tag: noindex, nofollow`, `ETag`.

### 3.3 Événements ICS

| Source | UID | Type | Détails |
| --- | --- | --- | --- |
| Activité | `activity-{id}@c2me.fr` | Heure fixe, 1 h par défaut | Rappel `remind_at` ou 15 min avant, lieu, URL fiche |
| Relance compte | `follow-up-{id}@c2me.fr` | Journée entière | Rappel à 9 h, lieu, URL fiche |
| Événement personnel | `event-{id}@c2me.fr` | Heure fixe ou journée entière | Lieu, notes, rappel relatif, `RRULE` si récurrent |

- Dates en UTC (`Z`), fuseau d'affichage fourni par `X-WR-TIMEZONE`.
- Lignes échappées (virgules, points-virgules, retours à la ligne) et repliées
  à 75 octets conformément à la RFC 5545.
- Les activités terminées sont exclues.
- Les événements personnels ne sont visibles que par leur propriétaire et les
  personnes avec qui ils sont partagés.
- Le calcul des récurrences est plafonné à `crm.calendar.max_occurrences`
  (500 par défaut), pour éviter qu'une récurrence sans fin ne produise un
  nombre illimité d'occurrences.

### 3.4 Interface

Page `/admin/calendar` :

- **Nouvel événement** : modale de création (titre, journée entière ou horaires,
  rappel, lieu, notes, récurrence, visibilité, compte/contact optionnel).
- **Clic sur un événement** : modale d’édition préremplie, suppression possible
  par la croix du calendrier ou depuis la vue Jour.
- Événements des autres utilisateurs affichés en lecture seule avec le badge
  « Partagé ».
- **S’abonner** : modale avec l’adresse WebCal, copie en un clic et instructions
  Google Agenda / Outlook / Apple Calendrier.
- **Exporter (.ics)** : téléchargement ponctuel.
- **Régénérer le lien** : confirmation explicite, puis nouvelle URL.

L’export de la liste des activités utilise le même service et inclut désormais
les relances.

## 4. Configuration (`config/crm.php`)

```php
'calendar' => [
    'duration_minutes' => 60,
    'default_reminder_minutes' => 15,
    'include_follow_ups' => true,
    'follow_up_reminder_hour' => 9,
    'feed_ttl_minutes' => 300,
    'feed_past_months' => 12,
    'max_occurrences' => 500,
],
```

## 5. Sécurité

- L’URL du flux est un secret porteur : ne jamais la journaliser ni la partager.
- La régénération invalide l’ancien lien.
- Les comptes désactivés perdent l’accès au flux.
- Les événements personnels sont privés par défaut : un administrateur ne voit
  pas ceux des autres.
- Un événement partagé est visible en lecture seule par les destinataires ;
  seul le propriétaire peut le modifier ou le supprimer.
- Les fichiers et données restent scopés par les policies existantes.
- Le flux ne doit pas être mis en cache par le service worker ou un proxy partagé.

## 6. Tests

- `tests/Feature/CalendarFeedTest.php` : contenu, en-têtes, 304, jeton invalide,
  compte inactif, régénération, scoping commercial/admin, échappement et repli,
  présence des actions Filament.
- `tests/Feature/CalendarEventTest.php` : confidentialité, partage (tous ou
  sélection), enrichissement ICS, `RRULE`, journée entière, création/édition/
  suppression depuis le calendrier, lecture seule des événements partagés.

## 7. Suites possibles

- Synchronisation bidirectionnelle Google Agenda / Microsoft 365 via OAuth.
- Choix des types d’activités exportés et calendriers multiples par utilisateur.
- Flux d’équipe par carnet avec droits dédiés.
- Récurrence avancée (RRULE complète, exceptions) et invitations avec RSVP.
