<x-layouts.app title="Demander un devis" description="Choisissez votre parcours de demande de devis C2ME : découpe laser de pièces, ou projet de bâtiment et charpente métallique.">

    <x-ui.page-hero
        eyebrow="Devis"
        title="Demander un devis"
        lead="Deux parcours, selon votre besoin. Décrivez votre projet en quelques étapes et joignez vos plans : nous vous répondons sur mesure."
    >
        <x-slot:actions>
            <x-ui.button href="tel:+33251381676" variant="volt" size="lg">02 51 38 16 76</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <section class="py-16 lg:py-20">
        <div class="container-site">
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach (\App\Enums\QuoteKind::cases() as $kind)
                    @php
                        $routeName = match ($kind) {
                            \App\Enums\QuoteKind::Laser => 'quote.laser',
                            \App\Enums\QuoteKind::Building => 'quote.building',
                        };

                        $steps = match ($kind) {
                            \App\Enums\QuoteKind::Laser => 4,
                            \App\Enums\QuoteKind::Building => 6,
                        };
                    @endphp

                    <a
                        href="{{ route($routeName) }}"
                        class="group flex flex-col rounded-2xl border border-mist-200 bg-white p-8 transition hover:border-brand-400 hover:shadow-[0_18px_40px_-24px_rgba(15,161,161,0.55)]"
                    >
                        <span
                            class="inline-grid h-12 w-12 place-items-center rounded-full text-white @if ($kind === \App\Enums\QuoteKind::Laser) bg-brand-500 @else bg-ink-900 @endif"
                        >
                            @if ($kind === \App\Enums\QuoteKind::Laser)
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            @else
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h1.5c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                            @endif
                        </span>

                        <h2 class="mt-6 font-display text-2xl font-extrabold text-ink-900">
                            {{ $kind->getLabel() }}
                        </h2>

                        <p class="mt-3 flex-1 text-sm leading-relaxed text-ink-700/70">
                            {{ $kind->description() }}
                        </p>

                        <ul class="mt-6 space-y-2 text-sm text-ink-700/70">
                            @if ($kind === \App\Enums\QuoteKind::Laser)
                                <li class="flex gap-2"><span class="text-brand-600">•</span> Acier, inox et aluminium</li>
                                <li class="flex gap-2"><span class="text-brand-600">•</span> Plans DXF, DWG ou PDF joint</li>
                            @else
                                <li class="flex gap-2"><span class="text-brand-600">•</span> Industriel, agricole ou commercial</li>
                                <li class="flex gap-2"><span class="text-brand-600">•</span> Du dessin au montage sur site</li>
                            @endif
                            <li class="flex gap-2"><span class="text-brand-600">•</span> {{ $steps }} étapes guidées</li>
                        </ul>

                        <span class="mt-8 inline-flex items-center gap-2 text-sm font-bold text-brand-600 transition group-hover:gap-3">
                            Commencer
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.19L10.7 6.03a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.24-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Pas encore de plan ?</p>
                    <p class="mt-3 text-sm leading-relaxed text-ink-700/70">
                        Décrivez votre besoin avec des cotes approximatives.
                        Nous vous indiquerons les documents utiles à l'étude.
                    </p>
                </div>

                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Vos plans sont confidentiels</p>
                    <p class="mt-3 text-sm leading-relaxed text-ink-700/70">
                        Les fichiers déposés sont stockés sur un espace privé,
                        réservé à notre bureau d'études, et supprimés au-delà de trois mois.
                    </p>
                </div>

                <div class="rounded-2xl bg-ink-900 p-6 text-white">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-volt-400">Une question avant de commencer ?</p>
                    <p class="mt-3 text-sm leading-relaxed text-mist-300/80">
                        Appelez le
                        <a href="tel:+33251381676" class="font-bold text-volt-400 hover:underline">02 51 38 16 76</a>
                        : nous orientons gratuitement votre demande.
                    </p>
                </div>
            </div>

            <p class="mt-10 text-center text-sm text-ink-700/70">
                Vous préférez écrire directement ?
                <a href="{{ route('contact') }}" class="font-bold text-brand-600 hover:underline">Passez par la page contact</a>.
            </p>
        </div>
    </section>

</x-layouts.app>
