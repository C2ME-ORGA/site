# CRM — politique de conservation (RGPD)

Les durées sont configurables via `.env` (valeurs par défaut entre parenthèses) :

| Donnée | Configuration | Durée |
| --- | --- | --- |
| Prospects inactifs | `CRM_RETENTION_PROSPECTS_MONTHS` (36) | 36 mois |
| Activités | `CRM_RETENTION_ACTIVITIES_MONTHS` (60) | 60 mois |
| Messages et pièces jointes | `CRM_EMAIL_RETENTION_MONTHS` (36) | 36 mois |

## Commandes planifiées

Toutes les nuits, après les purges des demandes du site :

- `crm:anonymize-prospects` (03:45) : anonymise les comptes au statut
  prospect ou inactif sans devis ni affaire, dont la dernière prise de
  contact (ou la dernière modification) dépasse la durée de conservation.
  Les coordonnées du compte et de ses contacts sont effacées, le compte
  passe « Inactif » et conserve son identifiant pour l'historique.
- `crm:purge-activities` (04:00) : supprime les activités dont la date
  d'occurrence (ou de dernière modification si elle est absente) dépasse
  la durée de conservation.
- `crm:purge-emails` (04:15) : supprime les messages et leurs pièces
  jointes stockées sur le disque `local`.

## Notes

- Un prospect ayant au moins un devis ou une affaire n'est jamais
  anonymisé automatiquement : la relation commerciale justifie la
  conservation.
- Les pièces jointes des e-mails entrants sont stockées hors du dossier
  public (`storage/app/private`) et ne sont téléchargeables que par un
  utilisateur autorisé à voir le message.
- Les demandes de devis et de contact du site ont leurs propres purges
  (`quotes:purge`, `contacts:purge`) définies dans `config/site.php`.
- La suppression d'un compte reste manuelle (soft delete) afin de
  préserver l'historique ; l'anonymisation automatique couvre les
  prospects sans activité commerciale.
