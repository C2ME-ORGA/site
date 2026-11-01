---
title: Journaux techniques
icon: heroicon-o-wrench-screwdriver
order: 4
---

# Journaux techniques

La page **Système › Visionneuse de logs** donne accès aux fichiers de log de
l'application. Elle est réservée aux administrateurs.

## Consulter un journal

1. Menu **Système › Visionneuse de logs**.
2. Choisir une date dans la liste (un fichier par jour en local).
3. Lire le contenu, filtrer par niveau (erreur, avertissement, information).
4. Télécharger le fichier si nécessaire.

Le fuseau d'affichage est `Europe/Paris`.

## À quoi cela sert

- Vérifier qu'une tâche planifiée s'est bien exécutée.
- Diagnostiquer un envoi d'e-mail en échec.
- Comprendre une erreur signalée par un utilisateur en retrouvant l'horodatage.

## Sécurité

- La suppression des journaux est **désactivée** : aucun droit d'effacement
  n'est accordé depuis l'interface.
- Les journaux peuvent contenir des données personnelles : ne jamais les
  transmettre à un tiers sans vérification.
- Ne pas coller le contenu d'un journal dans un outil externe sans avoir masqué
  les adresses e-mail et les identifiants.

## Différence avec le journal d'audit

| Journal technique | Journal d'audit |
| --- | --- |
| Erreurs et exécution de l'application | Modifications métier sensibles |
| Fichiers quotidiens | Table en base de données |
| Destiné au support technique | Destiné au contrôle et à la traçabilité |

Voir aussi **Journal d’audit**.
