---
title: Boîtes e-mail
icon: heroicon-o-at-symbol
order: 2
---

# Boîtes e-mail

La page **Système › Boîtes e-mail** configure les comptes IMAP/SMTP relevés par
le CRM. Elle est réservée aux administrateurs.

## Ajouter une boîte

1. Menu **Système › Boîtes e-mail**, puis **Nouvelle**.
2. Renseigner le **libellé** et l'**adresse e-mail**.
3. Choisir l'**utilisateur** propriétaire.
4. Cocher **Boîte partagée** si l'adresse est générique (`contact@`,
   `devis@`…).
5. Renseigner la réception **IMAP** : serveur, port, chiffrement,
   identifiant, mot de passe.
6. Renseigner l'envoi **SMTP** : serveur, port, chiffrement, identifiant.
7. Laisser **Active** cochée et enregistrer.

La synchronisation s'exécute automatiquement en tâche planifiée. La date de
**dernière synchronisation** est affichée sur la fiche.

## Boîte personnelle ou partagée

| Type | Visibilité | Notifications |
| --- | --- | --- |
| **Personnelle** | Propriétaire uniquement | Propriétaire uniquement |
| **Partagée** | Administrateurs et commerciaux actifs | Tous les utilisateurs actifs |

Le premier import d'une boîte est volontairement silencieux : aucun historique
n'est notifié.

## Vérifier qu'une boîte fonctionne

- La fiche affiche la dernière synchronisation réussie.
- La **Boîte de réception** montre les messages importés.
- Un envoi de test depuis un compte du CRM confirme le SMTP.
- En cas d'échec de connexion, vérifier le chiffrement (`SSL` / `TLS`), le port
  et l'autorisation « applications moins sécurisées » côté hébergeur.

## Sécurité

- Utiliser si possible un mot de passe d'application dédié au CRM.
- Ne jamais réutiliser le mot de passe personnel de la boîte.
- Désactiver une boîte au lieu de la supprimer en cas de doute : les messages
  déjà importés restent dans le CRM.

## Rétention

Les messages sont purgés après la durée de conservation configurée
(`CRM_EMAIL_RETENTION_MONTHS`, 36 mois par défaut). Voir la page **RGPD et
conservation**.
