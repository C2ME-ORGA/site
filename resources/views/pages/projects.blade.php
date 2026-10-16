@php
    $site = config('site');

    $projects = collect(config('site.projects'))
        ->map(function (string $name, string $slug) {
            $directory = public_path('images/realisations/'.$slug);

            if (! is_dir($directory)) {
                return null;
            }

            $files = collect(scandir($directory))
                ->filter(fn (string $file) => (bool) preg_match('/^photo-\d+\.webp$/', $file))
                ->sort()
                ->values();

            $photos = $files
                ->map(fn (string $file) => [
                    'src' => asset('images/realisations/'.$slug.'/'.$file),
                    'thumb' => asset('images/realisations/'.$slug.'/'.str_replace('.webp', '-thumb.webp', $file)),
                    'name' => $name,
                    'alt' => 'Réalisation C2ME — '.$name,
                ])
                ->all();

            if ($photos === []) {
                return null;
            }

            return [
                'slug' => $slug,
                'name' => $name,
                'photos' => $photos,
            ];
        })
        ->filter()
        ->values();

    $total = $projects->sum(fn (array $project) => count($project['photos']));
@endphp

<x-layouts.app title="Réalisations" description="Découvrez les réalisations de C2ME : charpentes et ossatures métalliques, bâtiments industriels et agricoles, en Vendée et sur toute la France.">

    <x-ui.page-hero
        eyebrow="Réalisations"
        title="Nos réalisations"
        lead="Charpentes, ossatures et bâtiments métalliques : un aperçu des chantiers menés par C2ME en Vendée et sur l’ensemble du territoire."
        :specs="[
            ['label' => 'Chantiers', 'value' => (string) count($projects)],
            ['label' => 'Photos', 'value' => (string) $total],
            ['label' => 'Zone', 'value' => $site['company']['zone']],
        ]"
    >
        <x-slot:actions>
            <x-ui.button :href="route('quote')" variant="volt" size="lg">
                Demander un devis
            </x-ui.button>
            <x-ui.button :href="route('services.frames')" variant="ghost-light" size="lg">
                Voir la charpente métallique
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <section class="py-20 lg:py-24">
        <div class="container-site">
            <div class="max-w-2xl">
                <p class="eyebrow">Chantiers</p>
                <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">
                    Filtrez par chantier
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-ink-700/70">
                    Sélectionnez un chantier pour voir ses photos, ou parcourez
                    l’ensemble des réalisations.
                </p>
            </div>

            <div class="mt-12">
                @if ($projects->isEmpty())
                    <div class="rounded-2xl border border-dashed border-mist-300 bg-mist-50 p-12 text-center">
                        <h3 class="font-display text-xl font-extrabold text-ink-900">
                            Photos en cours de préparation
                        </h3>
                        <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-ink-700/70">
                            Les photos de nos réalisations seront bientôt disponibles.
                            En attendant, contactez-nous pour connaître nos réalisations
                            et échanger sur votre projet.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                            <x-ui.button :href="route('quote')" variant="primary">Demander un devis</x-ui.button>
                            <x-ui.button :href="route('contact')" variant="outline">Nous contacter</x-ui.button>
                        </div>
                    </div>
                @else
                    <x-projects.gallery :projects="$projects" />
                @endif
            </div>
        </div>
    </section>

    <x-ui.page-cta
        title="Votre projet pourrait être le prochain"
        :text="'Décrivez-nous votre besoin : nous étudions votre demande et vous répondons par un devis adapté.'"
        primary-label="Demander un devis"
        :primary-href="route('quote')"
        secondary-label="Nous contacter"
        :secondary-href="route('contact')"
    />

</x-layouts.app>
