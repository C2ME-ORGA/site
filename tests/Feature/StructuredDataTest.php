<?php

/**
 * Extrait le JSON-LD brut, tel qu'écrit dans la page.
 */
function rawStructuredJsonFrom(string $html): string
{
    $matched = preg_match(
        '/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s',
        $html,
        $matches
    );

    if ($matched !== 1) {
        throw new RuntimeException('Aucun bloc JSON-LD trouvé dans la page.');
    }

    return $matches[1];
}

/**
 * Extrait le bloc JSON-LD de la page et le décode.
 *
 * @return array<string, mixed>
 */
function structuredDataFrom(string $html): array
{
    $data = json_decode(rawStructuredJsonFrom($html), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON-LD invalide : '.json_last_error_msg());
    }

    return $data;
}

test('la page d accueil déclare les données structurées de l entreprise', function (): void {
    $data = structuredDataFrom($this->get(route('home'))->assertOk()->getContent());

    expect($data)
        ->toMatchArray([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => config('site.company.name'),
            'telephone' => config('site.company.phone_link'),
            'email' => config('site.company.email'),
        ])
        ->and($data['address'])->toMatchArray([
            '@type' => 'PostalAddress',
            'addressLocality' => 'Montaigu-Vendée',
            'addressCountry' => 'FR',
        ]);
});

test('le siège social est l adresse principale déclarée', function (): void {
    $data = structuredDataFrom($this->get(route('home'))->getContent());

    $siege = collect(config('site.company.locations'))->firstWhere('key', 'siege');

    expect($data['address']['streetAddress'])
        ->toBe($siege['line1'].', '.$siege['line2'])
        ->and($data['geo'])->toMatchArray([
            'latitude' => $siege['coordinates']['lat'],
            'longitude' => $siege['coordinates']['lng'],
        ]);
});

test('l atelier est déclaré comme implantation secondaire', function (): void {
    $data = structuredDataFrom($this->get(route('home'))->getContent());

    $atelier = collect(config('site.company.locations'))->firstWhere('key', 'atelier');

    expect($data['department'])->toHaveCount(1);

    $department = $data['department'][0];

    expect($department)
        ->toMatchArray([
            '@type' => 'LocalBusiness',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $atelier['line1'].', '.$atelier['line2'],
                'postalCode' => $atelier['postal_code'],
                'addressLocality' => $atelier['city'],
                'addressRegion' => $atelier['region'],
                'addressCountry' => $atelier['country_code'],
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $atelier['coordinates']['lat'],
                'longitude' => $atelier['coordinates']['lng'],
            ],
        ])
        ->and($department['parentOrganization']['@id'])
        ->toBe($data['@id']);
});

test('les trois prestations figurent au catalogue', function (): void {
    $data = structuredDataFrom($this->get(route('home'))->getContent());

    $services = $data['hasOfferCatalog']['itemListElement'];

    expect($services)
        ->toHaveCount(count(config('site.services')))
        ->and(collect($services)->pluck('item.name')->all())
        ->toBe(collect(config('site.services'))->pluck('name')->all())
        ->and($services[0]['@type'])->toBe('ListItem')
        ->and($services[0]['item']['@type'])->toBe('Service')
        ->and($services[0]['item']['url'])->toBe(route('services.laser'));
});

test('les horaires structurés suivent la configuration', function (): void {
    $data = structuredDataFrom($this->get(route('home'))->getContent());

    $expected = collect(config('site.company.schema.opening_hours'))
        ->map(fn (array $hours): array => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => $hours['day_of_week'],
            'opens' => $hours['opens'],
            'closes' => $hours['closes'],
        ])
        ->all();

    expect($data['openingHoursSpecification'])->toBe($expected);
});

test('le balisage est identique sur toutes les pages publiques', function (): void {
    foreach (['home', 'company', 'projects', 'contact', 'quote', 'services.laser'] as $routeName) {
        $data = structuredDataFrom($this->get(route($routeName))->assertOk()->getContent());

        expect($data['@id'])->toBe(route('home').'#c2me')
            ->and($data['url'])->toBe(route('home'));
    }
});

test('le JSON-LD ne peut pas refermer sa propre balise script', function (): void {
    $json = rawStructuredJsonFrom($this->get(route('home'))->getContent());

    // JSON_HEX_TAG convertit < et > en entités Unicode : même si une valeur
    // contenait une balise, elle ne pourrait pas interrompre le bloc.
    expect($json)->not->toContain('<');
    expect($json)->not->toContain('>');
});
