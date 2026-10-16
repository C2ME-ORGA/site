@php
    $site = config('site');
    $service = collect($site['services'])->firstWhere('slug', 'decoupe-laser');
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

    {{-- MATIÈRES DÉCOUPÉES --}}
    <section class="py-16 lg:py-20">
        <div class="container-site">
            <div class="max-w-2xl">
                <p class="eyebrow">Matières &amp; épaisseurs</p>
                <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900">
                    Acier, inox et aluminium
                </h2>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ($service['blocks'] as $block)
                    <x-ui.block-card :block="$block" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- GRILLE TARIFAIRE --}}
    <section class="bg-mist-100 py-16 lg:py-20">
        <div class="container-site">
            <div class="max-w-2xl">
                <p class="eyebrow">Tarifs</p>
                <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900">
                    {{ $service['pricing']['title'] }}
                </h2>
            </div>

            <div class="mt-10 grid gap-8 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <x-ui.pricing-table :pricing="$service['pricing']" />
                </div>

                <div class="flex flex-col gap-5">
                    @foreach ($service['extras'] as $extra)
                        <div class="rounded-2xl border border-mist-200 bg-white p-5">
                            <h3 class="text-sm font-bold text-ink-900">{{ $extra['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-ink-700/70">{{ $extra['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @if (! empty($service['notes']))
                <ul class="mt-8 max-w-4xl space-y-2 text-xs leading-relaxed text-ink-700/70">
                    @foreach ($service['notes'] as $note)
                        <li class="flex gap-2">
                            <span class="mt-1.5 inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-brand-500"></span>
                            <span>{{ $note }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- FAQ --}}
    <section class="py-16 lg:py-20">
        <div class="container-site grid gap-10 lg:grid-cols-3">
            <div>
                <p class="eyebrow">Questions fréquentes</p>
                <h2 class="mt-4 font-display text-3xl font-extrabold tracking-tight text-ink-900">
                    Découpe laser
                </h2>
                <p class="mt-4 text-sm leading-relaxed text-ink-700/70">
                    Les réponses aux questions les plus courantes. Pour tout autre besoin,
                    notre équipe vous répond directement.
                </p>
            </div>

            <div class="flex flex-col gap-3 lg:col-span-2">
                @foreach ([
                    ['q' => 'Quelles épaisseurs pouvez-vous découper ?', 'a' => "Acier jusqu'à 30 mm, inox jusqu'à 20 mm et aluminium jusqu'à 10 mm, sur un format de travail de 4 000 × 2 000 mm."],
                    ['q' => 'Comment sont calculés les tarifs ?', 'a' => 'La facturation se fait à la pièce. Pour les tarifs au kg : prix unitaire HT = poids net de la pièce × tarif applicable, avec une dégressivité selon le tonnage total commandé.'],
                    ['q' => 'Livrez-vous les pièces ?', 'a' => 'Livraison possible, tarif sur devis selon la destination et le volume. Des pièces urgentes peuvent être traitées selon le délai demandé et les disponibilités.'],
                    ['q' => 'Les tarifs affichés sont-ils définitifs ?', 'a' => 'Ils sont donnés à titre indicatif, sous réserve de confirmation par devis. Les pièces fortement ajourées, à nombreux percages, avec pertes matière importantes ou nécessitant une reprise de fichiers sont chiffrées sur devis.'],
                ] as $faq)
                    <details class="group rounded-xl border border-mist-200 bg-white p-5 transition hover:border-brand-300">
                        <summary class="flex cursor-pointer items-center justify-between gap-4 text-sm font-bold text-ink-900">
                            {{ $faq['q'] }}
                            <svg class="h-5 w-5 shrink-0 text-brand-500 transition group-open:rotate-45" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z" />
                            </svg>
                        </summary>
                        <p class="mt-3 text-sm leading-relaxed text-ink-700/70">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <x-ui.page-cta :title="$service['cta']['title']" :text="$service['cta']['text']" />

</x-layouts.app>
