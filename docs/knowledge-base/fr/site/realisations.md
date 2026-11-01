---
title: Réalisations et départements
icon: heroicon-o-photo
order: 1
---

# Réalisations et départements

La section **Site** du CRM alimente la page publique `/realisations`. Tout ce qui
est publié ici apparaît sur le site : il n'y a plus de galerie statique à mettre
à jour à la main.

## Départements

Un département regroupe des chantiers : découpe laser, charpente métallique,
etc.

1. Menu **Site › Départements**, puis **Nouveau**.
2. Renseigner le nom, le slug et, si besoin, une description.
3. Définir l'ordre d'affichage.

Le slug sert d'URL publique (`/realisations/{slug}`) : le modifier change
l'adresse de la page.

## Créer un chantier

1. Menu **Site › Réalisations**, puis **Nouveau**.
2. Renseigner le **département**, le **titre**, le **maître d'ouvrage**, la
   **commune** et l'**année de livraison**.
3. Ajouter les **prestations** (liste libre) et une description.
4. Déposer les **photos**.
5. Cocher **Publié sur le site** et régler l'**ordre d'affichage**.
6. Enregistrer.

Un chantier non publié renvoie une erreur 404 sur le site : il reste visible dans
le CRM et peut être préparé à l'avance.

## Photos

- Dépôt multiple, **5 Mo** par image, formats **JPEG, PNG, WebP**.
- Redimensionnement à **1600 px** de côté maximum à l'import.
- Génération automatique d'une **vignette WebP 720 px**.
- Renseigner le **texte alternatif** : il est lu par les lecteurs d'écran et
  utilisé par les moteurs de recherche.

Les fichiers sont stockés sur le disque public (`storage/app/public/projects`),
jamais déposés directement dans `public/`.

## Réordonnancement

L'ordre d'affichage se règle sur le chantier et sur chaque photo. Il détermine
l'ordre sur `/realisations` et dans la galerie de la fiche.

## Sitemap

Les chantiers publiés sont ajoutés automatiquement au sitemap. Publier un
chantier le rend donc indexable ; le dépublier le retire.

## Bonnes pratiques

- Un chantier = une réalisation identifiable, pas une catégorie.
- Toujours renseigner le texte alternatif des photos.
- Vérifier le rendu sur mobile après l'ajout d'une série de photos.
