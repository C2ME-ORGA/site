<?php

test('la page d’accueil conserve le fond dégradé sans vidéo', function (): void {
    config()->set('site.home.hero.video', null);
    config()->set('site.home.hero.poster', null);

    $this->get('/')
        ->assertOk()
        ->assertDontSeeHtml('<video')
        ->assertSee('La construction métallique,');
});

test('la page d’accueil affiche une vidéo de fond décorative quand elle est configurée', function (): void {
    $relative = 'videos/hero-test.mp4';
    $absolute = public_path($relative);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }

    file_put_contents($absolute, 'contenu de test');

    try {
        config()->set('site.home.hero.video', $relative);
        config()->set('site.home.hero.poster', 'images/logo-wide.png');

        $response = $this->get('/')->assertOk();

        $response
            ->assertSeeHtml('<video')
            ->assertSeeHtml('autoplay')
            ->assertSeeHtml('muted')
            ->assertSeeHtml('loop')
            ->assertSeeHtml('playsinline')
            ->assertSeeHtml('motion-reduce:hidden')
            ->assertSee(asset($relative))
            ->assertSeeHtml('type="video/mp4"')
            ->assertSeeHtml('poster="'.asset('images/logo-wide.png').'"');
    } finally {
        unlink($absolute);
    }
});

test('une vidéo absente du dossier public est ignorée', function (): void {
    config()->set('site.home.hero.video', 'videos/inexistante.mp4');
    config()->set('site.home.hero.poster', null);

    $this->get('/')
        ->assertOk()
        ->assertDontSeeHtml('<video');
});
