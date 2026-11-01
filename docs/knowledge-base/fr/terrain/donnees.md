---
title: Synchronisation et données locales
icon: heroicon-o-arrow-path-rounded-square
order: 4
---

# Synchronisation et données locales

Le mode terrain fonctionne hors réseau : les modifications sont mises en file et
envoyées dès que la connexion revient. L'onglet **Données** permet de contrôler
cette file.

## Comment la synchronisation fonctionne

Chaque opération (création d'activité, modification de coordonnées, photo) est
enregistrée localement puis envoyée par lots :

- un **identifiant unique** rend chaque envoi idempotent ;
- la **date de dernière modification serveur** permet de détecter les conflits ;
- les opérations en erreur restent visibles et rejouables individuellement.

## Reprise automatique

En cas d'échec, la synchronisation réessaie après **5 s**, **30 s**, **2 min**
puis **10 min**. Le bandeau d'état indique l'avancement.

## Conflits

Un conflit apparaît lorsque la fiche a été modifiée côté serveur entre-temps. Les
conflits ne sont **jamais résolus silencieusement** : une fenêtre propose trois
choix.

| Choix | Effet |
| --- | --- |
| **Garder la version locale** | La modification de l'appareil écrase la version serveur. |
| **Prendre la version serveur** | La modification locale est abandonnée. |
| **Fusionner champ par champ** | Chaque champ divergent est choisi un par un. |

## Session expirée

Si la session expire pendant une période hors ligne, un bandeau de reconnexion
s'affiche. **Le snapshot et la file sont conservés** : après reconnexion, la
synchronisation reprend sans perte.

## Onglet Données

- **Espace utilisé** par le stockage local.
- **File d'opérations** : en attente, en conflit, en erreur.
- **Exporter la file** en JSON (sauvegarde avant intervention technique).
- **Vider le cache** avec une confirmation renforcée.

> Vider le cache efface les comptes, contacts et opérations stockés sur
> l'appareil. Exporter la file au préalable si des opérations sont en attente.

## Version du cache

L'onglet **Données** affiche la version du cache de l'application. Après une mise
à jour, la version change : si ce n'est pas le cas, recharger la page pour
récupérer la nouvelle version.

## Bonnes pratiques

- Laisser l'application ouverte quelques secondes après le retour du réseau.
- Consulter la file avant de vider le cache ou de changer d'appareil.
- Signaler un conflit récurrent plutôt que de forcer systématiquement la version
  locale.
