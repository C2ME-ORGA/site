---
title: Photos et pièces jointes
icon: heroicon-o-camera
order: 2
---

# Photos et pièces jointes

Le mode terrain permet de joindre des photos prises sur place à une activité.
Elles sont stockées sur l'appareil en attendant la synchronisation.

## Prendre une photo

1. Ouvrir l'activité concernée.
2. Cliquer sur **Ajouter une photo**.
3. Prendre la photo ou en choisir une dans la galerie.
4. Renseigner un commentaire si utile.
5. Enregistrer : l'image est mise en file avec l'activité.

## Limites

- **5 photos** maximum par activité ;
- **5 Mo** maximum par photo après compression ;
- compression automatique en **JPEG**, 1600 px de côté maximum.

La compression est faite par le navigateur avant le stockage : une photo
d'origine lourde ne bloque pas l'envoi.

## Envoi et stockage

- L'envoi est **différé** et **idempotent** : renvoyer une photo ne crée pas de
  doublon.
- Les fichiers sont stockés sur un disque **privé**, jamais dans `public/`.
- La lecture passe par une route authentifiée et contrôlée par les
  autorisations : un lien direct sans session ne fonctionne pas.

## Consultation

Les photos sont visibles :

- dans la fiche de l'activité du CRM, via la galerie ;
- depuis la fiche contact ou compte, dans l'historique des activités.

## Suppression et rétention

Les fichiers sont supprimés avec l'activité et lors de la purge de rétention.
Une photo supprimée n'est pas récupérable.

## Bonnes pratiques

- Cadrer l'ouvrage ou le document, pas l'écran d'un ordinateur.
- Éviter les photos de personnes identifiables sans accord.
- Ne pas utiliser cette fonction comme archivage : les photos sont liées à une
  activité commerciale, pas à un dossier technique.
