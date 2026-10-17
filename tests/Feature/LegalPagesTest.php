<?php

test('la page des mentions légales affiche l’identification de l’éditeur', function (): void {
    $response = $this->get('/mentions-legales');

    $response->assertOk();
    $response->assertSee('951 577 253 00010');
    $response->assertSee('Corentin Doizy');
    $response->assertSee('OVH SAS');
    $response->assertDontSee('Page en cours de construction');
});

test('la politique de confidentialité affiche les durées de conservation configurées', function (): void {
    config([
        'site.quote.retention_months' => 5,
        'site.contact.retention_months' => 7,
    ]);

    $response = $this->get('/confidentialite');

    $response->assertOk();
    $response->assertSee('5 mois');
    $response->assertSee('7 mois');
    $response->assertDontSee('Page en cours de construction');
});
