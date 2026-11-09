---
title: Bien démarrer
icon: heroicon-o-rocket-launch
order: 1
---

# Bien démarrer avec le CRM C2ME

Le CRM C2ME centralise les comptes, les contacts, les affaires, les devis, les
activités et les échanges d'e-mails de l'entreprise. Il regroupe également le
suivi des demandes reçues depuis le site vitrine et la gestion des réalisations
publiées en ligne.

## Se connecter

1. Ouvrir `https://c2me.test/admin` (ou l'adresse de production).
2. Saisir son adresse e-mail et son mot de passe.
3. En cas d'oubli, demander la réinitialisation à un administrateur : le mot de
   passe n'est jamais visible dans l'application.

Le même compte donne accès à la base documentaire, à l'adresse
`https://c2me.test/aide`.

## Les deux rôles

| Rôle | Accès |
| --- | --- |
| **Administrateur** | Tous les comptes, devis, activités et e-mails. Gère les utilisateurs, les boîtes e-mail, les journaux et le journal d'audit. |
| **Commercial** | Uniquement les comptes dont il est propriétaire, leurs contacts, affaires, devis, activités et e-mails. |

Un compte désactivé ne peut plus se connecter, même avec un mot de passe
valide.

## Organisation du menu

Le menu latéral est découpé en cinq ensembles :

- **CRM** : carnets de comptes, devis, activités et relances, étiquettes,
  recherche globale, calendrier et mode terrain.
- **Demandes** : demandes de contact et demandes de devis envoyées depuis le
  site vitrine.
- **Site** : départements et réalisations affichés sur le site public.
- **Système** : utilisateurs, boîtes e-mail, visionneuse de logs et journal
  d'audit.
- Le bouton **Base documentaire**, en bas du menu, ouvre cette aide.

## Les trois carnets

Les comptes sont répartis dans trois listes distinctes :

1. **Carnet 1 — Prescripteurs** : architectes, bureaux d'études, maîtres
   d'œuvre.
2. **Carnet 2 — Clients industriels** : industriels et donneurs d'ordre.
3. **Carnet 3 — Entreprises du bâtiment** : constructeurs et entreprises
   générales.

Un compte appartient à un seul carnet. Le carnet détermine les champs
spécifiques affichés dans la fiche et les colonnes proposées à l'export.

## Le parcours type

1. Une demande arrive depuis le site : elle apparaît dans **Demandes**.
2. La demande est convertie en compte et en affaire.
3. L'affaire progresse par étapes jusqu'au devis, puis à la signature.
4. Les échanges et visites sont enregistrés en **activités**.
5. Les relances sont planifiées et suivies dans **Relances**.
6. Le devis est imprimé ou envoyé par e-mail depuis la fiche.

> Le mode terrain (`/terrain`) permet de consulter les comptes et de saisir des
> activités depuis un téléphone, y compris sans réseau. Voir la page
> **Mode terrain**.

## Astuces

- La **recherche globale** (en haut de l'écran) retrouve comptes, contacts,
  affaires et devis, même en changeant de carnet.
- Chaque fiche dispose d'un bouton d'impression A4.
- Les listes proposent un export CSV respectant les filtres actifs.
