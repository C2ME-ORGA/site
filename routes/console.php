<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Purge des demandes de devis
|--------------------------------------------------------------------------
|
| Les plans joints peuvent contenir des données clients : ils sont
| supprimés du disque au-delà de la durée de conservation.
|
*/

Schedule::command('quotes:purge')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Purge des demandes de contact
|--------------------------------------------------------------------------
|
| Décalée après la purge des devis pour ne pas faire concourir les deux
| suppressions au même moment.
|
*/

Schedule::command('contacts:purge')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Expiration des devis
|--------------------------------------------------------------------------
|
| Un devis envoyé dont la date de validité est dépassée bascule en
| "Expiré" : l'affaire suit et les administrateurs sont notifiés.
|
*/

Schedule::command('quotes:expire')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Synchronisation des boîtes e-mail
|--------------------------------------------------------------------------
|
| Relève les messages entrants (IMAP) des boîtes actives et les rattache
| aux comptes et contacts du CRM.
|
*/

Schedule::command('mailboxes:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Conservation des données (RGPD)
|--------------------------------------------------------------------------
|
| Les durées sont définies dans config/crm.php : prospects et e-mails sur
| 36 mois, activités sur 60 mois. Les prospects sont anonymisés (et non
| supprimés) pour conserver l'historique commercial sans données
| personnelles ; les e-mails et leurs pièces jointes sont supprimés.
|
*/

Schedule::command('crm:anonymize-prospects')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('crm:purge-activities')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('crm:purge-emails')
    ->dailyAt('04:15')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Relances automatiques
|--------------------------------------------------------------------------
|
| La veille à 17:00 et le jour J à 08:00, un digest est envoyé au
| propriétaire du compte et aux administrateurs. Les horaires sont
| configurables dans config/crm.php.
|
*/

Schedule::command('crm:send-follow-up-reminders --stage=day-before')
    ->dailyAt((string) config('crm.follow_ups.day_before_time', '17:00'))
    ->when(fn (): bool => (bool) config('crm.follow_ups.enabled', true))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('crm:send-follow-up-reminders --stage=due-day')
    ->dailyAt((string) config('crm.follow_ups.due_day_time', '08:00'))
    ->when(fn (): bool => (bool) config('crm.follow_ups.enabled', true))
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Surveillance de sécurité (intégrité des fichiers et malwares)
|--------------------------------------------------------------------------
|
| `watchdog:scan` hache les fichiers surveillés et recherche des signatures
| de malwares. La planification toutes les minutes du paquet est désactivée
| dans config/filament-watchdog.php ; elle est remplacée ici par un passage
| toutes les 15 minutes, avec `--force` puisque `monitoring.enabled` est à
| false.
|
| Le scan est désactivé sur Windows : le paquet y calcule des chemins
| absolus qui faussent la détection des fichiers supprimés. Le baseline doit
| être créé sur le serveur après le déploiement (`php artisan watchdog:baseline`).
|
*/

Schedule::command('watchdog:scan --force')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn (): bool => PHP_OS_FAMILY !== 'Windows');

/*
|--------------------------------------------------------------------------
| Sauvegarde de la base de données
|--------------------------------------------------------------------------
|
| `backup:run --only-db` crée une archive chiffrée dans
| storage/app/backups, puis `backup:clean` applique la rétention et
| `backup:monitor` alerte si la dernière sauvegarde est trop ancienne.
| Le nettoyage passe avant la sauvegarde pour ne jamais manquer de place.
|
| Les fichiers (plans, photos, pièces jointes) sont sauvegardés
| séparément : voir docs/sauvegarde-restauration.md.
|
*/

Schedule::command('backup:clean')
    ->dailyAt('01:15')
    ->when(fn (): bool => (bool) config('backup.enabled', true))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:run --only-db')
    ->dailyAt('01:30')
    ->when(fn (): bool => (bool) config('backup.enabled', true))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('backup:monitor')
    ->dailyAt('02:00')
    ->when(fn (): bool => (bool) config('backup.enabled', true))
    ->withoutOverlapping()
    ->onOneServer();
