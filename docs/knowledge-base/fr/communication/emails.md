---
title: Boîte de réception
icon: heroicon-o-inbox
order: 1
---

# Boîte de réception

Le CRM relève les boîtes e-mail IMAP configurées et affiche les messages dans
**CRM › Boîte de réception**. Les réponses sont envoyées par SMTP et conservées
dans l'historique.

## Lire un message

La fiche d'un message affiche l'expéditeur, les destinataires, l'objet, le corps
et les **pièces jointes**. Les sujets encodés (MIME) sont décodés pour rester
lisibles.

Un message peut être rattaché à un **compte**, un **contact**, une **affaire**
et un **devis** : ce rattachement alimente l'historique des fiches.

## Composer un message

1. Cliquer sur **Nouveau message**.
2. Choisir l'**expéditeur** parmi les boîtes auxquelles on a accès.
3. Renseigner destinataires, copie, objet et message.
4. Joindre des pièces jointes si besoin.
5. Envoyer.

Le message est enregistré comme message sortant, avec la signature de
l'utilisateur insérée automatiquement.

## Répondre

L'action **Répondre** :

- reprend l'objet avec le préfixe `Re:` ;
- renseigne `In-Reply-To` et `References` pour que les clients e-mail
  reconstituent le fil ;
- cite le message d'origine au-dessus duquel on écrit ;
- place la signature de l'utilisateur.

> Ne pas répondre avec le lien `mailto:` d'un client de messagerie : la réponse
> perdrait le fil et ne serait pas archivée dans le CRM.

## Notifications

Les nouveaux messages non lus déclenchent une notification instantanée (toast,
cloche et badge de la boîte) pour les destinataires concernés :

- le propriétaire pour une boîte personnelle ;
- les administrateurs et commerciaux actifs pour une boîte partagée.

Le premier import d'une boîte est silencieux afin de ne pas notifier tout
l'historique.

## Filtres

- non lus, avec ou sans pièce jointe ;
- par boîte, par période ;
- par compte ou par affaire rattachés.

## Bonnes pratiques

- Rattacher systématiquement les messages entrants à un compte : la recherche
  par fiche devient beaucoup plus efficace.
- Utiliser une boîte partagée pour les adresses génériques (`contact@`), une
  boîte personnelle pour les échanges individuels.
- Archiver les messages traités plutôt que les supprimer.
