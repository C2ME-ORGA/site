---
title: Comptes
icon: heroicon-o-building-office-2
order: 1
---

# Comptes

Un compte représente une société : prescripteur, client industriel ou
entreprise du bâtiment. C'est le point d'entrée de toute relation commerciale.

## Créer un compte

1. Ouvrir le carnet correspondant : **Carnet 1 — Prescripteurs**,
   **Carnet 2 — Clients industriels** ou **Carnet 3 — Entreprises du
   bâtiment**.
2. Cliquer sur **Nouveau**.
3. Renseigner les onglets **Identité**, **Coordonnées**, **Qualification**,
   **Suivi** puis **Informations spécifiques** au carnet.
4. Enregistrer.

Le formulaire est découpé en onglets pour rester lisible sur petit écran.

### Champs principaux

| Champ | Usage |
| --- | --- |
| **Nom commercial** | Nom affiché partout dans le CRM. Obligatoire. |
| **Code client** | Référence interne, utile pour les rapprochements comptables. |
| **Raison sociale**, **SIRET**, **TVA** | Informations légales reprises sur les documents. |
| **Catégorie** | Sous-famille métier du carnet. |
| **Statut** | Prospect, Client, Partenaire ou Inactif. |
| **Potentiel** | Estimation du potentiel commercial. |
| **Source** | Origine du contact (site, salon, recommandation…). |
| **Commercial référent** | Propriétaire du compte : lui seul (et les administrateurs) voit la fiche. |
| **Étiquettes** | Marqueurs transverses, partagés entre les carnets. |
| **Prochaine relance** | Date reprise par la page **Relances** et le calendrier. |
| **Notes** | Contexte libre, non imprimé sur les documents. |

## La fiche compte

La fiche regroupe quatre onglets de travail :

- **Contacts** : interlocuteurs rattachés au compte.
- **Affaires** : opportunités commerciales.
- **Devis** : devis émis pour ce compte.
- **Activités** : appels, visites, notes et tâches.

L'en-tête affiche les badges de statut, le carnet et les actions rapides.

### Actions disponibles

- **Modifier** : met à jour la fiche.
- **Imprimer la fiche** : génère une version A4 (coordonnées, suivi, contacts,
  affaires, devis, activités).
- **Exporter les données (RGPD)** : produit un JSON complet du compte et de son
  historique. Réservé aux administrateurs.
- **Supprimer** : place le compte en corbeille ; la suppression définitive est
  journalisée.

## Scoping et confidentialité

Un commercial ne voit que les comptes dont il est le référent. Les
administrateurs voient l'ensemble des carnets. Ce filtrage s'applique aussi aux
exports CSV, aux impressions et aux liens directs : ouvrir l'URL d'un compte
dont on n'est pas propriétaire renvoie une erreur d'autorisation.

## Filtrer et trier

Chaque liste propose :

- la recherche par nom, ville ou code client ;
- le filtre par statut, catégorie, étiquette et commercial ;
- le filtre **relance à venir** et **en retard** ;
- le tri par nom, ville ou date de relance.

Les filtres actifs sont conservés lors de l'export CSV.

## Bonnes pratiques

> Renseigner systématiquement le **commercial référent** et la **prochaine
> relance** : ce sont les deux champs qui alimentent les rappels automatiques.

- Ne pas dupliquer un compte entre deux carnets : choisir le carnet qui
  correspond au type de donneur d'ordre.
- Utiliser les étiquettes pour les campagnes ponctuelles plutôt que de créer des
  catégories.
- Passer le statut à **Inactif** plutôt que supprimer un compte sans activité
  depuis longtemps.
