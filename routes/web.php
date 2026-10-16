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

Route::view('/devis', 'pages.quote', [
    'title' => 'Demander un devis',
    'eyebrow' => 'Devis',
    'heading' => 'Demander un devis',
    'message' => 'Le formulaire de demande de devis avec envoi de plans (PDF, DXF, DWG) sera disponible prochainement.',
])->name('quote');

Route::view('/mentions-legales', 'pages.placeholder', [
    'title' => 'Mentions légales',
    'heading' => 'Mentions légales',
])->name('legal.mentions');

Route::view('/confidentialite', 'pages.placeholder', [
    'title' => 'Politique de confidentialité',
    'heading' => 'Politique de confidentialité',
])->name('legal.privacy');
