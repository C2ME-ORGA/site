<?php

test('le plan de site publie toutes les pages publiques', function (): void {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    foreach ([
        '/',
        '/decoupe-laser',
        '/dessin-ingenierie',
        '/charpente-metallique',
        '/entreprise',
        '/realisations',
        '/contact',
        '/devis',
        '/devis/decoupe-laser',
        '/devis/batiment-charpente',
        '/mentions-legales',
        '/confidentialite',
    ] as $path) {
        $response->assertSee(url($path));
    }
});

test('le plan de site est un document XML bien formé', function (): void {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect(simplexml_load_string(testResponseContent($response)))->not->toBeFalse();
});

test('le plan de site n’expose pas les routes du back-office', function (): void {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertDontSee('/admin');
    $response->assertDontSee('livewire');
});
