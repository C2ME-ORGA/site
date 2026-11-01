---
title: Surveillance de sécurité
icon: heroicon-o-shield-exclamation
order: 7
---

# Surveillance de sécurité

Le CRM intègre une surveillance de sécurité réservée aux administrateurs.
Elle détecte les fichiers modifiés, les signatures de malwares, les tentatives
de connexion suspectes et permet un verrouillage d'urgence du site.

## Accès

Le menu **Système** contient cinq entrées :

- **Sécurité** : tableau de bord (état des services, statistiques, alertes
  récentes, actions).
- **Alertes de sécurité** : liste des alertes et leur traitement.
- **Intégrité des fichiers** : empreintes SHA-256 des fichiers surveillés.
- **Détections de malwares** : fichiers correspondant à une signature connue.
- **Journal d’activité** : connexions réussies et échouées.

Un commercial ne voit aucune de ces entrées et reçoit une erreur d'accès s'il
ouvre l'URL directement.

## Fréquence des contrôles

Le scan complet tourne **toutes les 15 minutes**. Il compare l'empreinte de
chaque fichier surveillé (`app`, `config`, `routes`, `resources`, `public`,
`database`, `bootstrap`) avec celle enregistrée lors du dernier passage.

Sont volontairement exclus : `vendor`, `node_modules`, `storage`, les caches,
le build Vite, les assets publiés, les images publiques et les seeders de
démonstration.

## Alertes

Une alerte porte une sévérité : faible, moyenne, élevée ou critique. Elle est
visible dans le CRM et, selon la configuration, envoyée par e-mail à
`admin@c2me.fr`.

Le système limite le nombre d'e-mails à **10 par heure** pour éviter une
avalanche en cas d'incident.

### Traiter une alerte

1. Ouvrir **Système › Alertes de sécurité**.
2. Lire le détail : type, fichier ou IP concerné, date.
3. Corriger si nécessaire (restaurer un fichier, changer un mot de passe).
4. Passer l'alerte en **Acquittée** ou **Résolue**.

Les écrans sont en **lecture seule** : on ne crée pas d'alerte à la main.

## Intégrité des fichiers

Chaque fichier surveillé est enregistré avec son empreinte, sa taille, sa date
de modification et un statut : propre, modifié, supprimé ou nouveau.

Un fichier **modifié** ou **supprimé** hors déploiement doit être vérifié
immédiatement. Un fichier **nouveau** peut être légitime (déploiement) ou
suspect.

### Créer le baseline (obligatoire après installation)

Tant qu'aucun baseline n'existe, tous les fichiers sont signalés comme
nouveaux. Sur le serveur :

```bash
php artisan watchdog:baseline
```

À relancer après chaque déploiement volontaire, pour éviter des centaines
d'alertes de fichiers modifiés.

> Le baseline et le scan planifié sont désactivés sur les postes Windows :
> le paquet y calcule des chemins absolus qui faussent la détection des
> suppressions. Les créer et les exécuter sur le serveur Linux.

## Détections de malwares

Le scan recherche des signatures connues : `eval`, `system`, `exec`,
`base64_decode`, `curl_exec`, accès web shell, etc. Chaque détection est
classée par risque.

La **quarantaine automatique est désactivée** : aucun fichier n'est déplacé
sans intervention, pour éviter de casser l'application sur un faux positif.

## Journal d'activité

Les connexions sont enregistrées automatiquement :

- **connexion réussie** : niveau faible ;
- **connexion échouée** : niveau moyen ;
- **plus de 5 échecs depuis la même IP en une heure** : alerte critique de
  force brute.

Le journal conserve 30 jours de données par défaut.

## Verrouillage d'urgence

Le bouton **Emergency Lockdown** du tableau de bord coupe l'accès au site :

- passage en maintenance ;
- fermeture des sessions (sauf la vôtre) ;
- sauvegarde d'urgence ;
- notification des administrateurs.

Un lien d'accès d'urgence est généré. Le verrouillage se lève manuellement ou
automatiquement après **24 heures**.

> À utiliser uniquement en cas d'intrusion avérée. Toutes les visites du site
> public sont bloquées pendant l'opération.

## Bonnes pratiques

- Créer le baseline après chaque déploiement.
- Vérifier les alertes **critiques** le jour même.
- Ne pas désactiver la surveillance pour supprimer une alerte gênante.
- Tester le verrouillage d'urgence une fois, hors production, pour connaître
  la procédure.
- Conserver `admin@c2me.fr` comme destinataire des alertes.
