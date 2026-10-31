@php
    $site = config('site');
    $company = $site['company'];
@endphp

<x-layouts.app :title="$company['tagline']">

    {{-- HERO --}}
    <section class="relative overflow-hidden bg-ink-900 text-white">
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.08]"
            style="background-image: linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px); background-size: 56px 56px;"
        ></div>
        <div class="pointer-events-none absolute -right-24 top-1/2 h-96 w-96 -translate-y-1/2 rounded-full bg-brand-500/25 blur-3xl"></div>

        <div class="container-site relative grid items-center gap-14 py-20 lg:grid-cols-12 lg:py-28">
            <div class="lg:col-span-7">
                <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.2em] text-volt-400">
                    <span class="inline-block h-1 w-10 rounded-full bg-volt-500"></span>
                    {{ $company['tagline'] }}
                </p>

                <h1 class="mt-6 font-display text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
                    La construction métallique,
                    <span class="text-volt-500">de l'étude au montage.</span>
                </h1>

                <p class="mt-6 max-w-xl text-lg leading-relaxed text-mist-300/90">
                    Découpe laser, bureau d'études et charpente métallique réunis au sein
                    d'un même atelier. Un interlocuteur unique, de la pièce unitaire à la série.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-4">
                    <x-ui.button :href="route('quote')" variant="volt" size="lg">
                        Demander un devis
                    </x-ui.button>
                    <x-ui.button :href="route('services.laser')" variant="ghost-light" size="lg">
                        Découvrir nos services
                    </x-ui.button>
                </div>

                <ul class="mt-10 flex flex-wrap items-center gap-x-8 gap-y-3 text-sm text-mist-300/80">
                    <li class="flex items-center gap-2">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-volt-500"></span>
                        {{ $company['zone'] }}
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-volt-500"></span>
                        {{ $company['locations'][0]['city'] }} — {{ $company['locations'][0]['region'] }}
                    </li>
                </ul>
            </div>

            {{-- Carte capacités (façon fiche technique) --}}
            <div class="lg:col-span-5">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-volt-400">Atelier — Découpe laser</p>
                    <dl class="mt-6 grid grid-cols-3 gap-4">
                        @foreach ($site['services'][0]['specs'] as $spec)
                            <div>
                                <dt class="text-[10px] font-bold uppercase tracking-[0.12em] text-mist-300/60">{{ $spec['label'] }}</dt>
                                <dd class="mt-1 font-display text-lg font-extrabold text-white">{{ $spec['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-6 space-y-3 border-t border-white/10 pt-6 text-sm">
                        @foreach ([
                            ['Acier', "jusqu'à 30 mm"],
                            ['Inox', "jusqu'à 20 mm"],
                            ['Aluminium', "jusqu'à 10 mm"],
                        ] as [$matiere, $epaisseur])
                            <div class="flex items-center justify-between gap-4">
                                <span class="font-semibold text-mist-300/80">{{ $matiere }}</span>
                                <span class="font-display font-extrabold text-volt-400">{{ $epaisseur }}</span>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-6 text-xs leading-relaxed text-mist-300/60">
                        Matière et découpe comprises. De la pièce unitaire à la série.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- POINTS CLÉS --}}
    <section class="border-b border-mist-200 bg-mist-100">
        <div class="container-site grid gap-8 py-12 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($site['highlights'] as $highlight)
                <div class="flex gap-4">
                    <span class="mt-1 grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-500/10 text-brand-600">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-ink-900">{{ $highlight['title'] }}</h2>
                        <p class="mt-1 text-sm leading-relaxed text-ink-700/70">{{ $highlight['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- SERVICES --}}
    <section id="services" class="py-20 lg:py-28">
        <div class="container-site">
            <div class="max-w-2xl">
                <p class="eyebrow">Nos services</p>
                <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">
                    Trois savoir-faire, un seul partenaire
                </h2>
                <p class="mt-4 text-lg leading-relaxed text-ink-700/70">
                    De l'étude de structure à la pose sur chantier, en passant par la découpe
                    laser, C2ME maîtrise l'ensemble de la chaîne.
                </p>
            </div>

            <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($site['services'] as $service)
                    <x-ui.service-card :service="$service" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- CHIFFRES --}}
    <section class="bg-ink-900 py-16 text-white">
        <div class="container-site">
            <dl class="grid grid-cols-2 gap-x-8 gap-y-10 lg:grid-cols-4">
                @foreach ($site['stats'] as $stat)
                    <div class="border-l-2 border-volt-500 pl-5">
                        <dt class="font-display text-2xl font-extrabold tracking-tight text-white sm:text-3xl">{{ $stat['value'] }}</dt>
                        <dd class="mt-1 text-xs font-semibold uppercase tracking-[0.1em] text-mist-300/70">{{ $stat['label'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-mist-100 py-20 lg:py-24">
        <div class="container-site">
            <div class="overflow-hidden rounded-3xl bg-brand-600 px-8 py-14 text-center text-white sm:px-16">
                <h2 class="mx-auto max-w-2xl font-display text-3xl font-extrabold tracking-tight sm:text-4xl">
                    Un projet de construction métallique ou de découpe ?
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-white/90">
                    Transmettez-nous vos plans et vos besoins : nous étudions votre demande
                    et vous répondons par un devis adapté.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    <x-ui.button :href="route('quote')" variant="dark" size="lg">
                        Demander un devis
                    </x-ui.button>
                    <x-ui.button :href="route('contact')" variant="ghost-light" size="lg">
                        Nous contacter
                    </x-ui.button>
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
