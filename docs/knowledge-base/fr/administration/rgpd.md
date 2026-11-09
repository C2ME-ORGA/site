---
title: RGPD et conservation
icon: heroicon-o-lock-closed
order: 6
---

# RGPD et conservation

Le CRM conserve des données personnelles (contacts, e-mails, activités). Cette
page résume les règles appliquées et les gestes à connaître.

## Durées de conservation

| Donnée | Durée par défaut | Variable |
| --- | --- | --- |
| Prospects inactifs | 36 mois | `CRM_RETENTION_PROSPECTS_MONTHS` |
| Activités | 60 mois | `CRM_RETENTION_ACTIVITIES_MONTHS` |
| E-mails | 36 mois | `CRM_EMAIL_RETENTION_MONTHS` |

Les purges sont exécutées automatiquement par des commandes planifiées. Les
durées doivent être cohérentes avec le registre des traitements
(`docs/rgpd-registre.md`).

## Demande d'accès ou d'effacement

Deux actions réservées aux administrateurs, depuis la fiche contact :

### Exporter les données (RGPD)

Produit un fichier JSON contenant le contact, son compte, les affaires, devis,
activités et e-mails liés. L'export est journalisé : on sait **qui** a exporté
**quoi** et **quand**, mais pas le contenu exporté.

### Anonymiser

Efface le nom, les coordonnées et les notes du contact, tout en conservant
l'historique commercial (affaires, devis, activités). L'opération est
journalisée sans recopier les valeurs effacées.

> L'anonymisation est irréversible. Vérifier qu'il n'existe pas d'autre contact
> pour la même personne avant de valider.

## Ce que le CRM ne fait pas

- Il ne remplace pas le registre des traitements : celui-ci reste la référence
  légale.
- Il ne supprime pas les données des sauvegardes : une archive peut contenir une
  donnée déjà anonymisée en base. La rétention des sauvegardes s'applique.
- Il ne gère pas les consentements marketing.

## Bonnes pratiques

- Ne saisir que les données nécessaires au suivi commercial.
- Éviter les notes libres contenant des données sensibles (santé, opinions,
  données bancaires).
- Répondre aux demandes d'exercice des droits dans le délai légal d'un mois.
- Documenter chaque demande traitée.
- Ne jamais transmettre un export RGPD par un canal non maîtrisé.

## Documents de référence

- `docs/rgpd-registre.md` : registre des traitements et procédure des droits.
- `docs/accessibilite.md` : audit d'accessibilité.
- `docs/sauvegarde-restauration.md` : sauvegarde et restauration.
