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
