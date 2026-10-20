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
