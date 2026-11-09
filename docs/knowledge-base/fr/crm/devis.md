---
title: Devis
icon: heroicon-o-document-currency-euro
order: 4
---

# Devis

Le module **Devis** gère les propositions chiffrées : saisie des lignes, calcul
des totaux, statuts et documents.

## Créer un devis

1. Menu **CRM › Devis**, puis **Nouveau**.
2. Choisir le **compte**, éventuellement le **contact** et l'**affaire**.
3. Renseigner l'**objet**, la **date d'émission** et la **date de validité**.
4. Ajouter les lignes dans la section **Lignes du devis**.
5. Vérifier les totaux, puis enregistrer.

## Totaux calculés automatiquement

Le **Total HT** et le **Total TTC** sont en lecture seule :

- chaque ligne vaut `quantité × prix unitaire HT`, remise déduite ;
- le total HT est la somme des lignes ;
- le total TTC applique le **taux de TVA** au total HT.

Les totaux se recalculent à l'ajout, à la modification ou à la suppression d'une
ligne, et au changement du taux de TVA. Ils sont consolidés à l'enregistrement.
Un devis peut être créé sans ligne : les totaux restent à 0.

## Statuts et transitions

| Statut | Signification |
| --- | --- |
| **Nouveau** | Devis en cours de rédaction. |
| **En cours d'étude** | Vérification technique ou chiffrage. |
| **Devis envoyé** | Transmis au client. |
| **Accepté** | Signé par le client. |
| **Refusé** | Proposition déclinée. |
| **Classé** | Sans suite. |

Les transitions sont proposées sous forme d'actions sur la fiche :

- **Marquer envoyé**
- **Accepter le devis**
- **Refuser le devis**
- **Annuler le devis**

Chaque changement de statut est journalisé dans le journal d'audit.

## Validité et relances

La **date de validité** alimente deux alertes du tableau de bord : les devis à
relancer et les devis expirés. Un devis arrivé à échéance sans réponse doit être
relancé ou classé.

## Documents

- Le champ **PDF** conserve le document officiel transmis au client.
- L'action **Imprimer** produit une fiche A4 du devis.
- L'envoi par e-mail se fait depuis la boîte de réception, avec la signature de
  l'utilisateur.

## Filtres utiles

- Par statut : retrouver les devis envoyés en attente de réponse.
- Par commercial : préparer un point individuel.
- Par période d'émission ou de validité.

L'export CSV reprend les filtres et la recherche actifs.
