---
title: Calendrier
icon: heroicon-o-calendar-days
order: 4
---

# Calendrier

La page **CRM › Calendrier** rassemble les activités planifiées, les relances de
comptes et les événements personnels. Chaque utilisateur peut s'abonner à son
propre flux calendrier.

## Ce que le calendrier affiche

- les **activités** planifiées et non terminées ;
- les **relances** de comptes dont on est propriétaire ;
- les **événements personnels** créés depuis la page.

## Créer un événement personnel

1. Cliquer sur **Nouvel événement**.
2. Renseigner titre, date, heure, durée, lieu et description.
3. Choisir le **partage** : privé (par défaut), tous les utilisateurs, ou une
   sélection de collègues en lecture seule.
4. Pour un événement récurrent, choisir une répétition **hebdomadaire** ou
   **mensuelle** simple.
5. Enregistrer.

Un clic sur un événement l'ouvre en édition ; la suppression est disponible
depuis la modale.

## S'abonner depuis Outlook, Google Calendar, Apple…

1. Cliquer sur **S'abonner**.
2. Copier l'URL du flux personnel (elle commence par `webcal://`).
3. Dans le logiciel de calendrier, ajouter un abonnement à partir de cette URL.

Le flux est privé : l'URL contient un jeton qui vaut mot de passe. Ne jamais la
partager ni la publier.

### Actions disponibles

| Action | Effet |
| --- | --- |
| **S'abonner** | Affiche l'URL du flux ICS personnel. |
| **Exporter** | Télécharge le flux `.ics` une fois. |
| **Régénérer** | Invalide l'ancienne URL et en crée une nouvelle. |

Utiliser **Régénérer** en cas de fuite de l'URL : les abonnements existants
cessent de fonctionner et doivent être reconfigurés.

## Contenu du flux

Le flux contient les activités planifiées non terminées, les relances de
comptes et les événements personnels, avec rappel, lieu et lien vers la fiche
CRM. Il n'est jamais mis en cache par le service worker ni par un proxy partagé.

## Bonnes pratiques

- Créer les rendez-vous clients comme **activités** (type Rendez-vous) plutôt que
  comme événements personnels : ils restent ainsi rattachés au compte.
- Réserver les événements personnels aux indisponibilités et aux rappels
  internes.
- Vérifier l'abonnement après une régénération de jeton.
