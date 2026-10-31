@php
    $site = config('site');
    $company = $site['company'];
@endphp

<x-layouts.app title="Entreprise" :description="$company['description']">

    <x-ui.page-hero
        eyebrow="L’entreprise"
        title="Une construction métallique maîtrisée de bout en bout"
        :lead="$company['description']"
        :specs="$site['stats']"
    >
        <x-slot:actions>
            <x-ui.button :href="route('quote')" variant="volt" size="lg">
                Demander un devis
            </x-ui.button>
            <x-ui.button :href="route('projects')" variant="ghost-light" size="lg">
                Voir nos réalisations
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    {{-- QUI SOMMES-NOUS --}}
    <section class="py-20 lg:py-24">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="eyebrow">Qui sommes-nous</p>
                <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">
                    La Guyonnière, Vendée
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-ink-700/70">
                    {{ $company['tagline'] }}
                </p>

                <dl class="mt-8 divide-y divide-mist-200 border-y border-mist-200">
                    @foreach ($company['locations'] as $location)
                        <div class="flex items-baseline justify-between gap-6 py-3.5">
                            <dt class="shrink-0 text-sm font-bold uppercase tracking-[0.1em] text-ink-700/70">
                                {{ $location['label'] }}
                            </dt>
                            <dd class="text-right text-sm font-semibold text-ink-900">
                                {{ $location['line1'] }}, {{ $location['line2'] }}<br>
                                {{ $location['postal_code'] }} {{ $location['city'] }}
                            </dd>
                        </div>
                    @endforeach
                    @foreach ([
                        ['label' => 'Téléphone', 'value' => $company['phone']],
                        ['label' => 'Mobile', 'value' => $company['mobile']],
                        ['label' => 'E-mail', 'value' => $company['email']],
                        ['label' => 'Zone d’intervention', 'value' => $company['zone']],
                    ] as $row)
                        <div class="flex items-baseline justify-between gap-6 py-3.5">
                            <dt class="text-sm font-bold uppercase tracking-[0.1em] text-ink-700/70">{{ $row['label'] }}</dt>
                            <dd class="text-right text-sm font-semibold text-ink-900">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="lg:col-span-7">
                <div class="grid gap-6 sm:grid-cols-2">
                    @foreach ([
                        ['title' => 'Bureau d’études', 'text' => 'Modélisation 2D/3D, plans d’exécution et nomenclatures, en métallique comme en béton armé.'],
                        ['title' => 'Atelier de fabrication', 'text' => 'Découpe laser fibre 8 kW, banc de perçage, sciage et soudure sur un même site.'],
                        ['title' => 'Montage sur chantier', 'text' => 'Pose de charpentes et d’ossatures, levage et raccordement.'],
                        ['title' => 'Un interlocuteur unique', 'text' => 'Étude, fabrication et montage assurés en interne, pour plus de réactivité.'],
                    ] as $pillar)
                        <div class="rounded-2xl border border-mist-200 bg-mist-50 p-7">
                            <span class="grid h-10 w-10 place-items-center rounded-lg bg-brand-500 text-white">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <h3 class="mt-5 font-display text-xl font-extrabold text-ink-900">{{ $pillar['title'] }}</h3>
                            <p class="mt-3 text-sm leading-relaxed text-ink-700/70">{{ $pillar['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- CHIFFRES CLÉS --}}
    <section class="bg-ink-900 py-16 text-white">
        <div class="container-site">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-volt-400">Chiffres clés</p>
            <dl class="mt-8 grid grid-cols-2 gap-x-8 gap-y-10 lg:grid-cols-4">
                @foreach ($site['stats'] as $stat)
                    <div class="border-l-2 border-volt-500 pl-5">
                        <dt class="font-display text-2xl font-extrabold tracking-tight text-white sm:text-3xl">{{ $stat['value'] }}</dt>
                        <dd class="mt-1 text-xs font-semibold uppercase tracking-[0.1em] text-mist-300/70">{{ $stat['label'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- SERVICES --}}
    <section class="py-20 lg:py-24">
        <div class="container-site">
            <div class="max-w-2xl">
                <p class="eyebrow">Nos services</p>
                <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">
                    Trois savoir-faire complémentaires
                </h2>
            </div>

            <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($site['services'] as $service)
                    <x-ui.service-card :service="$service" />
                @endforeach
            </div>
        </div>
    </section>

    <x-ui.page-cta
        title="Construisons ensemble"
        :text="'Parlons de votre projet : nous étudions votre demande et vous répondons par un devis adapté.'"
        primary-label="Demander un devis"
        :primary-href="route('quote')"
        secondary-label="Nous contacter"
        :secondary-href="route('contact')"
    />

</x-layouts.app>
