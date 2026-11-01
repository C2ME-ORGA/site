---
title: Signature e-mail
icon: heroicon-o-pencil-square
order: 2
---

# Signature e-mail

Chaque utilisateur définit sa propre signature dans **Profil › Signature
e-mail**. Elle est ajoutée automatiquement aux nouveaux messages et aux
réponses.

## Modifier sa signature

1. Cliquer sur son nom en haut à droite, puis **Profil** (ou `/admin/profile`).
2. Éditer le champ **Signature e-mail** dans l'éditeur enrichi.
3. Enregistrer.

L'éditeur accepte la mise en forme (gras, listes, liens) et l'insertion
d'images jusqu'à **5 Mo**, limitées aux formats **JPEG, PNG, GIF et WebP**.

## Où la signature apparaît

- en bas des **nouveaux messages** ;
- **au-dessus du texte cité** dans une réponse ;
- jamais si le champ est vide : le corps du message reste intact.

Les images et les liens relatifs sont convertis en URLs absolues au moment de
l'envoi, pour rester visibles chez le destinataire.

## Sécurité

La signature est nettoyée avant enregistrement et avant envoi : scripts,
gestionnaires d'événements et balises dangereuses sont supprimés. Les images
sont stockées sous un chemin pseudonyme, non déductible de l'identifiant
utilisateur.

## Bonnes pratiques

- Utiliser une signature courte : nom, fonction, ligne directe, site.
- Éviter les images lourdes : la limite est de 5 Mo par image, mais 100 ko
  suffisent pour un logo.
- Mettre à jour la signature lors d'un changement de poste ou de numéro.
