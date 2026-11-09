---
title: Export CSV et impression
icon: heroicon-o-arrow-down-tray
order: 9
---

# Export CSV et impression

Les listes et les fiches peuvent être exportées ou imprimées pour un usage hors
CRM : réunion, archivage, envoi à un partenaire.

## Export CSV des comptes

Depuis les trois listes de carnets, l'action **Exporter** produit un fichier CSV
contenant :

- les colonnes communes (nom, code, ville, statut, potentiel, source, référent,
  prochaine relance, contacts) ;
- les champs spécifiques au carnet ;
- les étiquettes.

L'export respecte la recherche et les filtres actifs, ainsi que le scoping : un
commercial n'exporte que ses comptes.

## Export CSV des affaires

Disponible :

- depuis la liste des comptes, pour les affaires des comptes filtrés ;
- depuis l'onglet **Affaires** d'une fiche compte.

## Format des fichiers

- Encodage **UTF-8 avec BOM** : Excel affiche correctement les accents.
- Séparateur **point-virgule**.
- Dates au format français (`JJ/MM/AAAA`).
- Montants décimaux avec virgule.

> Le BOM et le point-virgule sont volontaires : les modifier casserait l'ouverture
> directe dans Excel en configuration française.

## Impression des fiches

- **Fiche compte A4** : coordonnées, suivi, contacts, affaires, devis et
  activités. Action **Imprimer la fiche** sur la fiche compte.
- **Fiche affaire A4** : affaire, compte, contact, devis liés et activités.
- **Fiche devis A4** : action **Imprimer** sur le devis.

Les impressions sont protégées par la même authentification et les mêmes
autorisations que les fiches : un lien d'impression partagé ne fonctionne pas
sans session.

## Conseils

- Utiliser l'impression navigateur « Enregistrer au format PDF » pour archiver
  une fiche.
- Vérifier les filtres avant un export : le fichier ne contient que la sélection
  affichée.
