# RGPD — registre des traitements et procédure des droits

Ce document tient lieu de registre des activités de traitement (article 30 du
RGPD) pour le site et le CRM C2ME. Il complète `docs/crm-rgpd.md` (durées de
conservation) et `docs/sauvegarde-restauration.md` (archives).

## Responsable du traitement

| | |
| --- | --- |
| Responsable | C2ME, SARL au capital de 95 000 € |
| Siège | La Guyonnière La Bézochère, 85600 Montaigu-Vendée |
| SIRET | 951 577 253 00010 |
| Contact données personnelles | contact@c2me.fr |
| Représentant légal | Corentin Doizy, gérant |

Aucun délégué à la protection des données (DPO) n’est désigné : les
traitements ne relèvent pas des cas de l’article 37 (pas de suivi régulier et
systématique à grande échelle, pas de données sensibles à grande échelle).

## Registre des traitements

### 1. Demandes de contact et de devis (site public)

| | |
| --- | --- |
| Finalité | Répondre aux demandes envoyées depuis le site |
| Base légale | Intérêt légitime pour le contact (art. 6.1.f) ; mesures précontractuelles pour le devis (art. 6.1.b) |
| Données | Nom, société, e-mail, téléphone, type de demande, message ; pour un devis : caractéristiques du projet, commune de livraison, plans joints (PDF, DXF, DWG) ; adresse IP, page d’origine et agent utilisateur |
| Personnes concernées | Prospects et clients professionnels |
| Destinataires | Équipe commerciale et bureau d’études C2ME |
| Durée | Devis et plans : 3 mois (`config/site.php`, `quotes:purge` à 03:00). Contacts : 12 mois (`contacts:purge` à 03:15) |
| Sécurité | Plans sur disque privé (`storage/app/private/quote-requests`), jamais servis en direct ; limitation à 3 envois par heure et par IP ; pot de miel anti-robot |

### 2. Prospection et suivi commercial (CRM)

| | |
| --- | --- |
| Finalité | Gestion de la relation commerciale, relances, historique des échanges |
| Base légale | Intérêt légitime (art. 6.1.f) ; exécution du contrat pour les clients (art. 6.1.b) |
| Données | Comptes (société, SIRET, adresse, contacts), contacts (nom, fonction, e-mail, téléphone, notes), activités, affaires, étiquettes |
| Personnes concernées | Contacts professionnels des prospects et clients |
| Destinataires | Utilisateurs du CRM (administrateurs et commerciaux), chacun limité à son périmètre |
| Durée | Prospects inactifs : 36 mois (`CRM_RETENTION_PROSPECTS_MONTHS`, `crm:anonymize-prospects`) ; activités : 60 mois (`CRM_RETENTION_ACTIVITIES_MONTHS`, `crm:purge-activities`) |
| Sécurité | Policies par rôle et par propriétaire, journal d’audit des modifications sensibles, suppression logique |

### 3. Messagerie professionnelle

| | |
| --- | --- |
| Finalité | Centraliser les échanges e-mail liés aux comptes et aux affaires |
| Base légale | Intérêt légitime (art. 6.1.f) |
| Données | Expéditeur, destinataires, objet, corps du message, pièces jointes, identifiants IMAP |
| Personnes concernées | Contacts professionnels et salariés |
| Destinataires | Utilisateurs du CRM autorisés sur la boîte |
| Durée | 36 mois (`CRM_EMAIL_RETENTION_MONTHS`, `crm:purge-emails`) |
| Sécurité | Identifiants IMAP en `.env`, pièces jointes sur disque privé, téléchargement via route authentifiée |

### 4. Devis, affaires et facturation

| | |
| --- | --- |
| Finalité | Établir, envoyer et archiver les devis ; suivre les affaires |
| Base légale | Mesures précontractuelles et exécution du contrat (art. 6.1.b) ; obligation légale pour les pièces comptables (art. 6.1.c) |
| Données | Coordonnées du contact et du compte, lignes de devis, montants, statuts, PDF générés |
| Durée | Durée de la relation commerciale ; pièces comptables conservées 10 ans (obligation légale) |
| Sécurité | PDF sur disque privé, accès contrôlé par policy, journalisation des envois et changements de statut |

### 5. Comptes utilisateurs et journal d’audit

| | |
| --- | --- |
| Finalité | Authentifier les utilisateurs du back-office et tracer les opérations sensibles |
| Base légale | Intérêt légitime (art. 6.1.f) — sécurité du traitement |
| Données | Nom, e-mail, rôle, téléphone, fonction, signature, horodatages de connexion ; journal : auteur, événement, élément concerné, adresse IP |
| Personnes concernées | Salariés et prestataires disposant d’un accès |
| Durée | Compte : pendant la relation de travail, puis désactivation ; journal : aucune purge automatique, à limiter à 12 mois en production |
| Sécurité | Mots de passe hachés, comptes désactivables, aucun écran public d’inscription, journal en ajout seul |

### 6. Calendrier et flux ICS

| | |
| --- | --- |
| Finalité | Exporter les activités, relances et événements personnels vers un agenda externe |
| Base légale | Intérêt légitime (art. 6.1.f) |
| Données | Activités planifiées, relances, événements personnels (titre, lieu, description, participants) |
| Destinataires | L’utilisateur et les destinataires d’un événement partagé (lecture seule) |
| Durée | Tant que l’utilisateur conserve son abonnement ; jeton régénérable à tout moment |
| Sécurité | Jeton porteur secret par utilisateur (`users.calendar_token`), non journalisé, flux exclu des caches et du service worker |

### 7. Mode terrain (photos d’activités)

| | |
| --- | --- |
| Finalité | Documenter les interventions sur le terrain, y compris hors connexion |
| Base légale | Intérêt légitime (art. 6.1.f) |
| Données | Photos JPEG compressées, rattachées à une activité ; cache local du navigateur (IndexedDB) |
| Durée | Suppression avec l’activité et lors de la purge de rétention (60 mois) |
| Sécurité | Disque privé (`storage/app/private/crm-activity-attachments`), lecture via route authentifiée et policy, 5 photos et 5 Mo maximum par activité |

### 8. Sauvegardes

| | |
| --- | --- |
| Finalité | Restaurer le service après un incident |
| Base légale | Intérêt légitime (art. 6.1.f) |
| Données | Copie intégrale de la base (donc de tous les traitements ci-dessus) |
| Durée | 7 jours intégraux, puis 30 jours, 12 semaines, 6 mois, 2 ans ; plafond 2 Go |
| Sécurité | Archive chiffrée AES-256 (`BACKUP_ARCHIVE_PASSWORD`), disque privé, copie hors site, accès restreint |

## Sous-traitants et destinataires

| Prestataire | Rôle | Données | Localisation |
| --- | --- | --- | --- |
| OVH SAS | Hébergement du site et de la base | Toutes les données du service | France (UE) |
| Fournisseur SMTP | Envoi des e-mails transactionnels | Adresses e-mail, contenu des notifications | À préciser selon le contrat |
| Bunny Fonts | Polices auto-hébergées via CDN | Adresse IP, agent utilisateur (journaux techniques) | UE |
| logo.dev | Logos des entreprises dans le CRM | Nom de domaine de l’entreprise | À vérifier (peut être hors UE) |
| OpenStreetMap | Fond de carte de la page contact (iframe) | Adresse IP | UE (Wikimedia) |
| Destinataire de sauvegarde (`rclone`/`scp`) | Copie hors site | Archives chiffrées | À préciser |

Les prestataires agissant comme sous-traitants doivent fournir un accord de
traitement (article 28). Pour `logo.dev`, vérifier l’existence d’un transfert
hors UE et, à défaut, désactiver le service (`LOGO_DEV_PUBLISHABLE_KEY` vide).

## Cookies et traceurs

Un seul cookie est déposé : le cookie de session Laravel (`c2me_session`),
strictement nécessaire à l’authentification et à la protection CSRF. Il est
exempté de consentement. Aucun outil de mesure d’audience, de publicité ou de
réseau social n’est chargé.

## Droits des personnes

| Droit | Mise en œuvre |
| --- | --- |
| Accès et portabilité | Bouton « Exporter les données (RGPD) » sur la fiche contact : export JSON des données du contact, du compte, des affaires, devis, activités et e-mails |
| Rectification | Modification directe dans le CRM |
| Effacement | Bouton « Anonymiser » sur la fiche contact (nom, coordonnées et notes effacés, historique conservé sans identifiant) ; suppression des archives sur demande |
| Opposition et prospection | Champ « Ne pas démarcher » ; l’anonymisation l’active automatiquement |
| Limitation | Désactivation du compte ou gel de la fiche sur demande |

Procédure : toute demande reçue à `contact@c2me.fr` est enregistrée, vérifiée
(en cas de doute sur l’identité, demander un justificatif), traitée dans un
délai d’un mois (prolongeable de deux mois pour les demandes complexes), puis
consignée dans le journal d’audit (export ou anonymisation).

## Violation de données

En cas de violation (accès non autorisé, perte d’archive, fuite de fichier) :

1. qualifier la violation et le risque pour les personnes ;
2. contenir l’incident (révoquer les accès, régénérer les jetons et mots de
   passe, isoler le serveur) ;
3. notifier la CNIL sous 72 heures si le risque est avéré ;
4. informer les personnes concernées si le risque est élevé ;
5. consigner l’incident et les mesures prises.

## Analyse d’impact (AIPD)

Aucune analyse d’impact n’est requise : pas de traitement à grande échelle, pas
de données sensibles (santé, opinions, biométrie), pas de profilage produisant
des effets juridiques, pas de surveillance systématique d’une zone accessible
au public.

## Points à finaliser avant la mise en production

- [ ] Vérifier le contrat et la localisation du fournisseur SMTP.
- [ ] Vérifier `logo.dev` (transfert hors UE) ou désactiver le service.
- [ ] Ajouter une purge du journal d’audit (12 mois) ou justifier sa conservation.
- [ ] Activer l’authentification à deux facteurs pour les comptes administrateurs.
- [ ] Signer les accords de sous-traitance (article 28) avec l’hébergeur et le prestataire SMTP.
- [ ] Tester l’export et l’anonymisation sur une copie de la base.
