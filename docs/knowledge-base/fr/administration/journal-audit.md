---
title: Journal d’audit
icon: heroicon-o-shield-check
order: 3
---

# Journal d’audit

La page **Système › Journal d’audit** trace les modifications sensibles du CRM.
Elle est réservée aux administrateurs et strictement en lecture seule.

## Ce qui est journalisé

- **Statuts** : devis, affaires, comptes et boîtes e-mail.
- **Étapes et montants** des affaires.
- **Rôles et activation** des utilisateurs.
- **Suppressions et restaurations** (comptes, contacts, affaires, devis,
  activités, e-mails, boîtes, étiquettes, utilisateurs).
- **Envois d'e-mails** : destinataires, copie et objet.
- **Actions RGPD** : export et anonymisation d'un contact.

Une suppression définitive ne produit qu'une seule entrée, même lorsqu'elle
cascade sur plusieurs objets liés.

## Ce qui n'est pas journalisé

- Les créations.
- Les modifications non sensibles (orthographe d'un nom, note libre).
- Les exécutions en ligne de commande : elles n'ont ni utilisateur ni adresse IP.

## Consulter le journal

Chaque ligne contient l'auteur, l'événement, l'élément concerné, la date,
l'adresse IP et les changements. Le bouton **Détail** ouvre une modale
avant / après.

Filtres disponibles : événement, type d'élément, auteur et période.

## Confidentialité

Les valeurs effacées par une anonymisation ne sont **jamais** recopiées dans le
journal. Un export RGPD n'inscrit que le fait de l'export, pas son contenu.

## Bonnes pratiques

- Consulter le journal après une opération inhabituelle (suppression massive,
  changement de rôle).
- Utiliser la période pour reconstituer une chronologie.
- Conserver le journal selon la politique de conservation interne (12 mois
  recommandés).
