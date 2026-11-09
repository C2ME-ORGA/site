<?php

test('une adresse inconnue affiche la page 404 de la charte', function (): void {
    $this->get('/cette-page-nexiste-pas')
        ->assertNotFound()
        ->assertSee('Page introuvable')
        ->assertSee('Cette page n’existe pas')
        ->assertSee('Aller au contenu principal')
        ->assertSee('Nos réalisations')
        ->assertDontSee('Erreur 404');
});

test('les vues 500 et 503 reprennent le gabarit public', function (string $view, string $title, string $eyebrow): void {
    /** @var view-string $view */
    $html = view($view)->render();

    expect($html)
        ->toContain($title)
        ->toContain($eyebrow)
        ->toContain('bg-ink-900')
        ->toContain('object-cover')
        ->toContain('Aller au contenu principal');
})->with([
    'erreur serveur' => ['errors.500', 'Une erreur inattendue est survenue', 'Incident technique'],
    'maintenance' => ['errors.503', 'Le site est momentanément indisponible', 'Maintenance en cours'],
]);

test('la page 503 propose de réessayer et de téléphoner', function (): void {
    $html = view('errors.503')->render();

    expect($html)
        ->toContain('Réessayer')
        ->toContain('Nous téléphoner')
        ->toContain('tel:+33251381676');
});
