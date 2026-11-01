---
title: Notifications
icon: heroicon-o-bell-alert
order: 3
---

# Notifications

Le CRM notifie : les nouveaux e-mails entrants non lus, les rappels de relance et
les événements du calendrier.

## Notifications d'e-mail

À chaque nouvel e-mail entrant non lu :

- un **toast** s'affiche dans l'interface ;
- la **cloche** de notifications s'incrémente ;
- le badge de la boîte de réception est mis à jour.

La diffusion est en temps réel. Si le WebSocket n'est pas disponible, un
interrogation de secours toutes les **30 secondes** prend le relais : les
notifications arrivent avec un léger retard, sans perte.

### Qui est notifié

- **Boîte personnelle** : uniquement le propriétaire.
- **Boîte partagée** : tous les administrateurs et commerciaux actifs.

Le **premier import** d'une boîte ne génère aucune notification, afin d'éviter de
signaler tout l'historique.

## Rappels de relance

Les relances sont signalées la veille à **17:00** et le jour même à **08:00**,
dans l'application et par e-mail si le canal est activé. Le suivi des deux
canaux est indépendant : un e-mail en échec n'empêche pas la notification
interne.

## Consulter et marquer comme lu

- La cloche liste les notifications récentes.
- Cliquer sur une notification ouvre l'élément concerné.
- Les notifications lues restent consultables quelques jours.

## Désactiver

La diffusion temps réel des e-mails peut être désactivée par la variable
d'environnement `CRM_EMAIL_NOTIFICATIONS` (voir la documentation technique).
Cette désactivation ne supprime ni les messages ni les rappels.

## Dépannage

| Symptôme | Cause probable |
| --- | --- |
| Aucune notification temps réel | WebSocket non démarré ; le repli par interrogation reste actif. |
| Pas de notification après import | Premier import volontairement silencieux. |
| Aucun toast, cloche vide | Boîte inactive ou notifications désactivées. |
