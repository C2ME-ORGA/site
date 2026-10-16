@php
    $site = config('site');
    $service = collect($site['services'])->firstWhere('slug', 'charpente-metallique');
@endphp

<x-layouts.app :title="$service['name']" :description="$service['description']">

    <x-ui.page-hero
        :eyebrow="'Service '.$service['number']"
        :title="$service['name']"
        :lead="$service['lead']"
        :specs="$service['specs']"
    >
        <x-slot:actions>
            <x-ui.button :href="route('quote')" variant="volt" size="lg">
                Demander un devis
            </x-ui.button>
            <x-ui.button :href="route('contact')" variant="ghost-light" size="lg">
                Nous contacter
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    {{-- ATELIER & CHANTIER --}}
    <section class="py-16 lg:py-20">
        <div class="container-site">
            <div class="max-w-2xl">
                <p class="eyebrow">Notre savoir-faire</p>
                <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900">
                    De l'atelier au chantier
                </h2>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                @foreach ($service['blocks'] as $block)
                    <x-ui.block-card :block="$block" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- MOYENS DE FABRICATION --}}
    <section class="bg-ink-900 py-12 text-white">
        <div class="container-site flex flex-col items-start gap-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-volt-400">Nos moyens de fabrication</p>
            <ul class="flex flex-wrap items-center gap-3 sm:gap-6">
                @foreach ($service['tools'] as $tool)
                    <li class="rounded-lg border border-white/15 bg-white/5 px-4 py-2 font-display text-base font-extrabold text-white sm:text-lg">
                        {{ $tool }}
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- PRESTATION & ZONE --}}
    <section class="bg-mist-100 py-16 lg:py-20">
        <div class="container-site grid gap-6 md:grid-cols-2">
            @foreach ($service['extras'] as $extra)
                <div class="rounded-2xl border-l-4 border-brand-500 bg-white p-7 sm:p-8">
                    <h2 class="font-display text-xl font-extrabold text-ink-900 sm:text-2xl">{{ $extra['title'] }}</h2>
                    <p class="mt-3 text-base leading-relaxed text-ink-700/70">{{ $extra['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <x-ui.page-cta :title="$service['cta']['title']" :text="$service['cta']['text']" />

</x-layouts.app>
