---
title: Utiliser le terrain
icon: heroicon-o-device-phone-mobile
order: 1
---

# Utiliser le mode terrain

Le **mode terrain** (`/terrain`) est une page autonome, pensée pour le téléphone
et utilisable **hors réseau**. Elle permet de consulter les comptes, de saisir
des activités et d'enregistrer des photos sur place.

## Installation sur le téléphone

1. Ouvrir `/terrain` dans le navigateur du téléphone.
2. Accepter l'invite d'installation (ou « Ajouter à l'écran d'accueil »).
3. Se connecter une première fois pour télécharger les données.

L'invite d'installation peut être reportée de 14 jours. La version du cache est
affichée dans l'onglet **Données**.

## Les trois onglets

| Onglet | Contenu |
| --- | --- |
| **Comptes** | Comptes et contacts du portefeuille, avec recherche, filtres et tri. |
| **Tournée** | Sélection des visites du jour et itinéraire. |
| **Données** | Espace utilisé, file de synchronisation, export, vidage du cache. |

Sur ordinateur, les trois panneaux restent visibles côte à côte.

## Travailler hors ligne

- Les comptes, contacts et activités sont stockés dans le navigateur
  (IndexedDB) : consultation et saisie fonctionnent sans réseau.
- Les modifications partent dans une **file d'attente** et sont envoyées
  automatiquement au retour de la connexion.
- Les créations portent un identifiant unique : renvoyer une opération ne crée
  jamais de doublon.

### Reprise automatique

En cas d'échec réseau, la synchronisation réessaie après **5 s**, **30 s**,
**2 min** puis **10 min**. Les erreurs restent visibles par opération.

### Session expirée

Si la session expire pendant une période hors ligne, un bandeau de reconnexion
s'affiche. Le snapshot et la file d'attente sont **conservés** : rien n'est
perdu après reconnexion.

## Créer une activité depuis le terrain

1. Ouvrir un compte.
2. Cliquer sur **Nouvelle activité**.
3. Choisir le type (appel, visite, note, tâche), l'échéance et le contact.
4. Saisir le compte rendu.
5. Enregistrer : l'activité est mise en file si le réseau est absent.

## Modifier un compte ou un contact

Les coordonnées modifiables hors ligne sont le téléphone, l'e-mail, l'adresse et
les notes. Les modifications sont envoyées à la synchronisation.

## Actions rapides

Depuis un contact : **Appeler**, **SMS**, **E-mail**, **Ouvrir dans Plans**.

## Thème et accessibilité

Le mode terrain suit la préférence système. La bascule clair/sombre est
mémorisée sur l'appareil. Les cibles tactiles font au moins 44 px et la
navigation est accessible au clavier.
