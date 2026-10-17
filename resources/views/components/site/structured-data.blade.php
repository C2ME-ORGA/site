@props(['site'])

@php
    $company = $site['company'];
    $schema = $company['schema'];

    $toAddress = fn (array $location): array => [
        '@type' => 'PostalAddress',
        'streetAddress' => trim($location['line1'].', '.$location['line2']),
        'postalCode' => $location['postal_code'],
        'addressLocality' => $location['city'],
        'addressRegion' => $location['region'],
        'addressCountry' => $location['country_code'],
    ];

    $toGeo = fn (array $location): array => [
        '@type' => 'GeoCoordinates',
        'latitude' => $location['coordinates']['lat'],
        'longitude' => $location['coordinates']['lng'],
    ];

    $locations = collect($company['locations']);
    $headquarters = $locations->firstWhere('key', 'siege');

    // Identifiant partagé : les prestations et le second site pointent vers lui.
    $businessId = route('home').'#c2me';
    $areaServed = ['@type' => 'Country', 'name' => $schema['area_served']];

    $structured = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        '@id' => $businessId,
        'name' => $company['name'],
        'description' => $company['description'],
        'url' => route('home'),
        'logo' => asset($company['logo']['wide']),
        'image' => asset($company['logo']['wide']),
        'telephone' => $company['phone_link'],
        'email' => $company['email'],
        'address' => $toAddress($headquarters),
        'geo' => $toGeo($headquarters),
        'areaServed' => $areaServed,

        'openingHoursSpecification' => collect($schema['opening_hours'])
            ->map(fn (array $hours): array => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $hours['day_of_week'],
                'opens' => $hours['opens'],
                'closes' => $hours['closes'],
            ])
            ->all(),

        // L'atelier : même société, autre adresse physique.
        'department' => $locations
            ->reject(fn (array $location): bool => $location['key'] === 'siege')
            ->map(fn (array $location): array => [
                '@type' => 'LocalBusiness',
                '@id' => $businessId.'-'.$location['key'],
                'name' => $company['name'].' — '.$location['label'],
                'telephone' => $company['phone_link'],
                'address' => $toAddress($location),
                'geo' => $toGeo($location),
                'parentOrganization' => ['@id' => $businessId],
            ])
            ->values()
            ->all(),

        'hasOfferCatalog' => [
            '@type' => 'OfferCatalog',
            'name' => 'Prestations '.$company['name'],
            'itemListElement' => collect($site['services'])
                ->map(fn (array $service, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => [
                        '@type' => 'Service',
                        'name' => $service['name'],
                        'description' => $service['description'],
                        'url' => route($service['route']),
                        'provider' => ['@id' => $businessId],
                        'areaServed' => $areaServed,
                    ],
                ])
                ->values()
                ->all(),
        ],
    ];
@endphp

{{-- Invisible du visiteur : renseigne Google sur l'entreprise, ses deux sites et ses prestations. --}}
<script type="application/ld+json">
    {!! json_encode(
        $structured,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRETTY_PRINT
    ) !!}
</script>
