<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site vitrine C2ME
|--------------------------------------------------------------------------
*/

Route::view('/', 'home')->name('home');

/*
|--------------------------------------------------------------------------
| Services
|--------------------------------------------------------------------------
*/

Route::view('/decoupe-laser', 'services.decoupe-laser')->name('services.laser');
Route::view('/dessin-ingenierie', 'services.dessin-ingenierie')->name('services.design');
Route::view('/charpente-metallique', 'services.charpente-metallique')->name('services.frames');

/*
|--------------------------------------------------------------------------
| Entreprise & réalisations
|--------------------------------------------------------------------------
*/

Route::view('/entreprise', 'pages.company')->name('company');
Route::view('/realisations', 'pages.projects')->name('projects');

/*
|--------------------------------------------------------------------------
| Contact
|--------------------------------------------------------------------------
*/

Route::view('/contact', 'pages.contact')->name('contact');

/*
|--------------------------------------------------------------------------
| Devis & pages légales
|--------------------------------------------------------------------------
*/

Route::view('/devis', 'pages.quote')->name('quote');

Route::view('/devis/decoupe-laser', 'pages.quote-laser', [
    'title' => 'Devis découpe laser',
    'eyebrow' => 'Devis — Découpe laser',
    'heading' => 'Votre projet de découpe laser',
    'message' => 'Décrivez votre besoin en quatre étapes et joignez vos plans : '
        .'nous vous répondons par un devis, de la pièce unitaire à la série.',
])->name('quote.laser');

Route::view('/devis/batiment-charpente', 'pages.quote-building', [
    'title' => 'Devis bâtiment & charpente',
    'eyebrow' => 'Devis — Bâtiment & charpente',
    'heading' => 'Votre projet de bâtiment',
    'message' => 'Décrivez votre chantier en six étapes : nature du bâtiment, implantation, '
        .'enveloppe et calendrier. Notre bureau d’études établit une proposition sur mesure.',
])->name('quote.building');

Route::view('/mentions-legales', 'pages.placeholder', [
    'title' => 'Mentions légales',
    'heading' => 'Mentions légales',
])->name('legal.mentions');

Route::view('/confidentialite', 'pages.placeholder', [
    'title' => 'Politique de confidentialité',
    'heading' => 'Politique de confidentialité',
])->name('legal.privacy');
