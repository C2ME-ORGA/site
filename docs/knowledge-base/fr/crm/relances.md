---
title: Relances
icon: heroicon-o-arrow-path
order: 6
---

# Relances

La page **CRM › Relances** liste les comptes à recontacter et les activités en
attente, avec un badge de navigation indiquant le nombre d'éléments à traiter.

## Ce que la page affiche

- les comptes dont la **prochaine relance** est arrivée à échéance ;
- les activités de type **Relance** planifiées ;
- le dernier contact connu, le téléphone et le contact principal.

## Traiter une relance

Trois actions sont disponibles sur chaque ligne :

- **Marquer comme relancé** : enregistre la relance et repousse l'échéance.
- **Reporter** : choisit une nouvelle date d'échéance.
- **Ouvrir** : accède directement au compte ou à l'activité.

Les mêmes actions existent en masse, après sélection de plusieurs lignes.

## Rappels automatiques

Les rappels sont envoyés :

- **la veille à 17:00** pour préparer le lendemain ;
- **le jour même à 08:00** pour la journée en cours.

Ils arrivent sous forme de notification dans Filament et, si configuré, par
e-mail. Un rappel en échec peut être rejoué ; un rappel déjà envoyé n'est jamais
dupliqué.

## Filtres

- **Période** : aujourd'hui, cette semaine, en retard.
- **Carnet**, **statut** et **propriétaire**.
- Les comptes **inactifs** sont masqués par défaut ; les administrateurs peuvent
  les afficher avec un filtre dédié.

## Bonnes pratiques

- Renseigner la prochaine relance immédiatement après un appel, pas en fin de
  journée.
- Ne pas créer une relance pour un compte inactif : le rappel serait sans suite.
- Utiliser les actions groupées pour les tournées de relance téléphonique.

## Différence avec les activités

Une relance est une **activité de type Relance** : elle apparaît donc aussi dans
la fiche du compte et dans le calendrier. La page Relances n'est qu'une vue
filtrée, centrée sur l'action à mener.
