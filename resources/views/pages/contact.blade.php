@php
    $site = config('site');
    $company = $site['company'];

    // Carte centrée sur l'adresse exacte, avec un zoom à l'échelle du hameau
    $lat = $company['address']['coordinates']['lat'];
    $lng = $company['address']['coordinates']['lng'];
    $delta = 0.008; // ~900 m en latitude

    $mapEmbed = 'https://www.openstreetmap.org/export/embed.html?'
        .'bbox='.implode(',', [
            round($lng - $delta * 1.6, 6),
            round($lat - $delta, 6),
            round($lng + $delta * 1.6, 6),
            round($lat + $delta, 6),
        ]).'&layer=mapnik&marker='.$lat.','.$lng;

    $mapLink = 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng
        .'#map=16/'.$lat.'/'.$lng;
@endphp

<x-layouts.app title="Contact" description="Contactez C2ME à Montaigu-Vendée : 02 51 38 16 76, 06 77 95 39 88. Découpe laser, bureau d'études et charpente métallique.">

    <x-ui.page-hero
        eyebrow="Nous contacter"
        title="Parlons de votre projet"
        lead="Une question, un délai ou un besoin de devis ? Notre équipe vous répond directement."
    >
        <x.ui.button href="tel:+33251381676" variant="volt" size="lg">02 51 38 16 76</x.ui.button>
        <x.ui.button href="mailto:contact@c2me.fr" variant="ghost-light" size="lg">Nous écrire</x.ui.button>
    </x-ui.page-hero>

    <section class="py-16 lg:py-20">
        <div class="container-site grid gap-8 lg:grid-cols-3">
            {{-- Coordonnées --}}
            <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Adresse</p>
                    <address class="mt-3 text-sm not-italic leading-relaxed text-ink-800">
                        C2ME<br>
                        {{ $company['address']['line1'] }}<br>
                        {{ $company['address']['postal_code'] }} {{ $company['address']['city'] }}<br>
                        {{ $company['address']['region'] }}
                    </address>
                </div>

                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Téléphone</p>
                    <div class="mt-3 space-y-1 text-sm">
                        <a href="tel:{{ $company['phone_link'] }}" class="block font-bold text-ink-900 transition hover:text-brand-600">
                            {{ $company['phone'] }} <span class="font-normal text-ink-700/60">(standard)</span>
                        </a>
                        <a href="tel:{{ $company['mobile_link'] }}" class="block font-bold text-ink-900 transition hover:text-brand-600">
                            {{ $company['mobile'] }} <span class="font-normal text-ink-700/60">(mobile)</span>
                        </a>
                    </div>
                    <p class="mt-5 text-xs font-bold uppercase tracking-[0.16em] text-brand-600">E-mail</p>
                    <a href="mailto:{{ $company['email'] }}" class="mt-2 block text-sm font-bold text-ink-900 transition hover:text-brand-600">
                        {{ $company['email'] }}
                    </a>
                </div>

                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Horaires</p>
                    <dl class="mt-3 space-y-2 text-sm">
                        @foreach ($company['hours'] as $hour)
                            <div class="flex items-baseline justify-between gap-4">
                                <dt class="text-ink-700/70">{{ $hour['label'] }}</dt>
                                <dd class="font-semibold text-ink-900">{{ $hour['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>

            {{-- Carte --}}
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-mist-200 bg-white">
                    <iframe
                        src="{{ $mapEmbed }}"
                        class="h-[420px] w-full border-0"
                        loading="lazy"
                        title="Carte — {{ $company['address']['city'] }}"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>

                <a
                    href="{{ $mapLink }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-brand-600 transition hover:text-brand-700"
                >
                    Ouvrir l'itinéraire dans OpenStreetMap
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.22 14.28a1 1 0 0 1 0-1.42l4.5-4.5a1 1 0 0 1 1.42 0l4.5 4.5a1 1 0 1 1-1.42 1.42L10 11.41l-3.79 3.87a1 1 0 0 1-1.42 0Z" clip-rule="evenodd" />
                        <path fill-rule="evenodd" d="M5.22 8.28a1 1 0 0 1 0-1.42l4.5-4.5a1 1 0 0 1 1.42 0l4.5 4.5a1 1 0 1 1-1.42 1.42L10 5.41 6.21 9.28a1 1 0 0 1-1.42 0Z" clip-rule="evenodd" />
                    </svg>
                </a>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-ink-900 p-6 text-white">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-volt-400">Zone d'intervention</p>
                        <p class="mt-1 font-display text-lg font-extrabold">{{ $company['zone'] }}</p>
                    </div>
                    <x.ui.button :href="route('quote')" variant="volt">Demander un devis</x.ui.button>
                </div>
            </div>
        </div>
    </section>

    <section class="border-t border-mist-200 bg-mist-50 py-16 lg:py-20">
        <div class="container-site grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-1">
                <p class="eyebrow">Écrivez-nous</p>
                <h2 class="mt-3 font-display text-3xl font-extrabold leading-tight text-ink-900">
                    Décrivez votre besoin
                </h2>
                <p class="mt-4 text-sm leading-relaxed text-ink-700/70">
                    Plus votre message est précis — quantités, matières, épaisseurs, délais —
                    plus notre réponse sera rapide et exploitable.
                </p>

                <div class="mt-8 rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Vous avez des plans ?</p>
                    <p class="mt-3 text-sm leading-relaxed text-ink-700/70">
                        Transmettez-les directement par e-mail, ou parlez-nous-en ici :
                        nous vous indiquons la marche à suivre.
                    </p>
                    <a
                        href="mailto:{{ $company['email'] }}"
                        class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-brand-600 transition hover:text-brand-700"
                    >
                        {{ $company['email'] }}
                    </a>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-mist-200 bg-white p-6 sm:p-8">
                    <livewire:contact-form />
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
