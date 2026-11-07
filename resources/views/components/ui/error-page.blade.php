@props([
    'title',
    'lead',
    'eyebrow' => 'C2ME',
    'image' => null,
    'links' => [],
])

@php
    $company = config('site.company');
@endphp

<x-layouts.app :title="$title" :description="$lead">
    <section class="relative isolate overflow-hidden bg-ink-900 text-white">
        <div class="pointer-events-none absolute inset-0">
            @if ($image)
                <img
                    src="{{ $image }}"
                    alt=""
                    class="h-full w-full object-cover"
                    loading="eager"
                    fetchpriority="high"
                >
                <div class="absolute inset-0 bg-gradient-to-r from-ink-950/90 via-ink-950/70 to-ink-900/35"></div>
            @endif

            <div
                class="absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px); background-size: 56px 56px;"
            ></div>
            <div class="absolute -right-40 -top-32 h-96 w-96 rounded-full bg-brand-500/25 blur-3xl"></div>
        </div>

        <div class="container-site relative py-20 lg:py-28">
            <div class="max-w-3xl">
                <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.2em] text-volt-400">
                    <span class="inline-block h-1 w-10 rounded-full bg-volt-500"></span>
                    {{ $eyebrow }}
                </p>

                <h1 class="mt-5 font-display text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl">
                    {{ $title }}
                </h1>

                <p class="mt-6 max-w-2xl text-lg leading-relaxed text-mist-100/90">
                    {{ $lead }}
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-4">
                    @isset($actions)
                        {{ $actions }}
                    @else
                        <x-ui.button :href="route('home')" variant="volt" size="lg">
                            Retour à l’accueil
                        </x-ui.button>
                        <x-ui.button :href="route('contact')" variant="ghost-light" size="lg">
                            Nous contacter
                        </x-ui.button>
                    @endisset
                </div>

                <p class="mt-8 text-sm text-mist-200/80">
                    Besoin d’aide ?
                    <a href="tel:{{ $company['phone_link'] }}" class="font-semibold text-white transition hover:text-volt-400">
                        {{ $company['phone'] }}
                    </a>
                    <span class="mx-1 text-mist-200/40" aria-hidden="true">·</span>
                    <a href="mailto:{{ $company['email'] }}" class="font-semibold text-white transition hover:text-volt-400">
                        {{ $company['email'] }}
                    </a>
                </p>
            </div>
        </div>
    </section>

    @if (count($links))
        <section class="border-t border-mist-200 bg-mist-50 py-16 lg:py-20">
            <div class="container-site">
                <p class="eyebrow">Pour continuer</p>

                <div class="mt-6 grid gap-6 sm:grid-cols-3">
                    @foreach ($links as $link)
                        <a
                            href="{{ $link['href'] }}"
                            class="group flex flex-col rounded-2xl border border-mist-200 bg-white p-6 transition hover:border-brand-400 hover:shadow-sm"
                        >
                            <span class="font-display text-lg font-extrabold text-ink-900 transition group-hover:text-brand-600">
                                {{ $link['label'] }}
                            </span>

                            @if (! empty($link['description']))
                                <span class="mt-3 text-sm leading-relaxed text-ink-700/70">
                                    {{ $link['description'] }}
                                </span>
                            @endif

                            <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-brand-600">
                                Ouvrir
                                <svg class="h-4 w-4 transition group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M3 10a1 1 0 0 1 1-1h9.586L10.293 5.707a1 1 0 0 1 1.414-1.414l4.999 4.999a1 1 0 0 1 0 1.416l-4.999 4.999a1 1 0 0 1-1.414-1.414L13.586 11H4a1 1 0 0 1-1-1Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>
