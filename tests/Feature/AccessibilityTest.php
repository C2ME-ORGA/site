<?php

use Livewire\Livewire;

test('le gabarit public expose un lien d’évitement et un repère principal', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Aller au contenu principal')
        ->assertSeeHtml('href="#main"')
        ->assertSeeHtml('id="main"')
        ->assertSeeHtml('lang="fr"');
});

test('l’en-tête fournit un nom accessible unique et un menu contrôlé', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSeeHtml('aria-label="Accueil C2ME')
        ->assertSeeHtml('aria-controls="services-menu"')
        ->assertSeeHtml('aria-controls="mobile-menu"')
        ->assertSeeHtml(':aria-label="open ? \'Fermer le menu\' : \'Ouvrir le menu\'"');
});

test('les pages légales ont des tableaux accessibles', function (): void {
    $this->get('/confidentialite')
        ->assertOk()
        ->assertSeeHtml('<th scope="col"');
});

test('les formulaires publics annoncent les champs obligatoires', function (): void {
    $this->get('/contact')
        ->assertOk()
        ->assertSeeHtml('aria-required="true"')
        ->assertSee('sont obligatoires');

    $this->get('/devis/decoupe-laser')
        ->assertOk()
        ->assertSeeHtml('aria-required="true"');

    $this->get('/devis/batiment-charpente')
        ->assertOk()
        ->assertSeeHtml('aria-required="true"');
});

test('chaque formulaire public affiche la mention RGPD', function (): void {
    foreach (['/contact', '/devis/decoupe-laser', '/devis/batiment-charpente'] as $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertSee('Les informations transmises servent uniquement à traiter votre demande')
            ->assertSeeHtml(route('legal.privacy'));
    }
});

test('le formulaire de contact signale les erreurs aux lecteurs d’écran', function (): void {
    Livewire::test('contact-form')
        ->set('name', '')
        ->set('email', 'adresse-invalide')
        ->set('requestType', '')
        ->set('message', '')
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'requestType', 'message'])
        ->assertSeeHtml('role="alert"')
        ->assertSeeHtml('aria-invalid="true"')
        ->assertSeeHtml('aria-describedby="contact-name-error"')
        ->assertSeeHtml('id="contact-name-error"')
        ->assertSee('Le champ nom est obligatoire.');
});

test('le formulaire de devis reprend le résumé d’erreurs', function (): void {
    Livewire::test('laser-quote')
        ->set('material', '')
        ->call('nextStep')
        ->assertHasErrors(['material'])
        ->assertSeeHtml('role="alert"')
        ->assertSeeHtml('aria-describedby="laser-material-error"');
});

test('la page contact rend les cartes avec des titres de section', function (): void {
    $this->get('/contact')
        ->assertOk()
        ->assertSeeHtml('>Implantations</h2>')
        ->assertSeeHtml('>Horaires</h2>');
});
