@php
    $site ??= config('site');

    $projectCount = $projects->count();
    $photoCount = $projects->sum(fn ($project) => $project->images->count());
@endphp

<x-layouts.app title="Réalisations" description="Découvrez les réalisations de C2ME : charpentes et ossatures métalliques, bâtiments industriels et agricoles, en Vendée et sur toute la France.">

    <x-ui.page-hero
        eyebrow="Réalisations"
        title="Nos réalisations"
        lead="Charpentes, ossatures et bâtiments métalliques : un aperçu des chantiers menés par C2ME en Vendée et sur l’ensemble du territoire."
        :specs="[
            ['label' => 'Chantiers', 'value' => (string) $projectCount],
            ['label' => 'Photos', 'value' => (string) $photoCount],
            ['label' => 'Départements', 'value' => (string) $categories->count()],
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
                    Filtrez par département
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-ink-700/70">
                    Sélectionnez une famille d’ouvrages pour affiner la sélection,
                    ou parcourez l’ensemble des réalisations.
                </p>
            </div>

            @if ($categories->isNotEmpty())
                <nav class="mt-8 flex flex-wrap gap-2" aria-label="Filtrer les réalisations par département">
                    <a
                        href="{{ route('projects') }}"
                        @class([
                            'rounded-full px-4 py-2 text-sm font-bold transition',
                            'bg-ink-900 text-white' => $selected === '',
                            'border border-mist-300 bg-white text-ink-900 hover:border-brand-400 hover:text-brand-600' => $selected !== '',
                        ])
                        @if ($selected === '') aria-current="page" @endif
                    >
                        Tous les chantiers
                    </a>

                    @foreach ($categories as $category)
                        <a
                            href="{{ route('projects', ['departement' => $category->slug]) }}"
                            @class([
                                'rounded-full px-4 py-2 text-sm font-bold transition',
                                'bg-ink-900 text-white' => $selected === $category->slug,
                                'border border-mist-300 bg-white text-ink-900 hover:border-brand-400 hover:text-brand-600' => $selected !== $category->slug,
                            ])
                            @if ($selected === $category->slug) aria-current="page" @endif
                        >
                            {{ $category->name }} · {{ $category->projects_count }}
                        </a>
                    @endforeach
                </nav>
            @endif

            <div class="mt-12">
                @if ($projects->isEmpty())
                    <div class="rounded-2xl border border-dashed border-mist-300 bg-mist-50 p-12 text-center">
                        <h3 class="font-display text-xl font-extrabold text-ink-900">
                            Aucun chantier dans cette sélection
                        </h3>
                        <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-ink-700/70">
                            Les photos de nos réalisations seront bientôt disponibles.
                            En attendant, contactez-nous pour connaître nos réalisations
                            et échanger sur votre projet.
                        </p>
                        <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                            @if ($selected !== '')
                                <x-ui.button :href="route('projects')" variant="outline">Voir tous les chantiers</x-ui.button>
                            @endif
                            <x-ui.button :href="route('quote')" variant="primary">Demander un devis</x-ui.button>
                            <x-ui.button :href="route('contact')" variant="outline">Nous contacter</x-ui.button>
                        </div>
                    </div>
                @else
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($projects as $project)
                            <x-projects.card :project="$project" />
                        @endforeach
                    </div>
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
