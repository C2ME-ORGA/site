---
title: Sauvegarde et restauration
icon: heroicon-o-server-stack
order: 5
---

# Sauvegarde et restauration

La base de données est sauvegardée automatiquement chaque nuit. Ce document
résume le fonctionnement ; la procédure complète est dans
`docs/sauvegarde-restauration.md`.

## Fonctionnement

| Heure | Commande | Rôle |
| --- | --- | --- |
| 01:15 | `backup:clean` | Applique la rétention. |
| 01:30 | `backup:run --only-db` | Crée l'archive de la base. |
| 02:00 | `backup:monitor` | Alerte si la dernière sauvegarde est trop ancienne. |

Les archives sont nommées `c2me-AAAA-MM-JJ-HH-MM-SS.zip` et stockées dans
`storage/app/backups`, hors du dossier public et ignorées par Git.

## Chiffrement

- Si `BACKUP_ARCHIVE_PASSWORD` est défini, l'archive est chiffrée en **AES-256**.
- En production, cette variable est **obligatoire**.
- En local, une archive non chiffrée est acceptable.

## Rétention

7 jours, 30 jours, 12 semaines, 6 mois et 2 ans, avec un plafond de 2 Go.

## Surveiller

- Les alertes e-mail se limitent aux échecs et aux sauvegardes trop anciennes
  (`BACKUP_NOTIFICATION_EMAIL`).
- La commande `php artisan backup:monitor` vérifie la fraîcheur.
- Les planifications peuvent être suspendues avec `BACKUP_ENABLED=false`.

## Restaurer

1. Récupérer l'archive et son mot de passe.
2. Extraire le dump `db-dumps/mysql-{base}.sql`.
3. Restaurer sur une base vide, jamais directement en production sans
   sauvegarde préalable.
4. Vérifier les connexions, les fichiers stockés et les tâches planifiées.

Les fichiers (`storage/app/private`, `storage/app/public`) sont sauvegardés
séparément par `tar` + `gzip`.

## Bonnes pratiques

- Copier les archives hors site (`rclone`, `scp`) et vérifier le `sha256`.
- Faire un exercice de restauration **chaque trimestre**.
- Ne jamais committer une archive ni la placer dans `public/`.
