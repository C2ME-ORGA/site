<?php

use App\Http\Controllers\EmailAttachmentController;
use App\Http\Controllers\FieldModeController;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

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

Route::view('/mentions-legales', 'pages.legal.mentions-legales')
    ->name('legal.mentions');

Route::view('/confidentialite', 'pages.legal.confidentialite')
    ->name('legal.privacy');

/*
|--------------------------------------------------------------------------
| Mode terrain
|--------------------------------------------------------------------------
|
| Page autonome (sans Livewire) mise en cache par le service worker : elle
| reste consultable hors réseau et synchronise les modifications en attente
| dès le retour de la connexion.
|
*/

Route::middleware('auth')->group(function (): void {
    Route::view('/terrain', 'field.index')->name('field.index');
    Route::get('/terrain/snapshot', [FieldModeController::class, 'snapshot'])->name('field.snapshot');
    Route::post('/terrain/sync', [FieldModeController::class, 'sync'])->name('field.sync');

    Route::get('/emails/attachments/{attachment}', [EmailAttachmentController::class, 'download'])
        ->name('emails.attachments.download');
});

/*
|--------------------------------------------------------------------------
| Plan de site
|--------------------------------------------------------------------------
|
| Construit depuis la table des routes plutôt qu'entretenu à la main : toute
| page ajoutée apparaît automatiquement, et un fichier ne peut pas devenir
| périmé. Les routes du back-office et celles du runtime Livewire sont
| écartées par leur préfixe, et celles qui attendent un paramètre sont
| ignorées faute d'être des pages autonomes.
|
*/

Route::get('/sitemap.xml', function (): Response {
    $urls = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteInstance $route): bool => in_array('GET', $route->methods(), true))
        ->filter(fn (RouteInstance $route): bool => filled($route->getName()))
        ->filter(fn (RouteInstance $route): bool => $route->parameterNames() === [])
        ->reject(fn (RouteInstance $route): bool => str_starts_with($route->uri(), 'admin')
            || str_starts_with($route->uri(), 'livewire')
            || str_starts_with((string) $route->getName(), 'filament.'))
        ->map(fn (RouteInstance $route): string => e(url($route->uri())))
        ->sort()
        ->values();

    $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
        .$urls->map(fn (string $url): string => "    <url><loc>{$url}</loc></url>\n")->join('')
        .'</urlset>';

    return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('sitemap');
