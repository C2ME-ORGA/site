@props(['site'])

@php
    $company = $site['company'];
@endphp

<header
    x-data="{ open: false, services: false }"
    class="sticky top-0 z-50 border-b border-mist-200 bg-white/95 backdrop-blur"
>
    {{-- Bandeau utilitaire --}}
    <div class="hidden bg-ink-950 text-mist-100 lg:block">
        <div class="container-site flex h-10 items-center justify-between text-xs">
            <p class="flex items-center gap-2 text-mist-300">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-volt-500"></span>
                {{ $company['zone'] }} · {{ $company['locations'][0]['city'] }} — {{ $company['locations'][0]['region'] }}
            </p>
            <div class="flex items-center gap-6">
                <a href="tel:{{ $company['phone_link'] }}" class="flex items-center gap-2 transition hover:text-volt-400">
                    {{ $company['phone'] }}
                </a>
                <a href="tel:{{ $company['mobile_link'] }}" class="flex items-center gap-2 transition hover:text-volt-400">
                    {{ $company['mobile'] }}
                </a>
                <a href="mailto:{{ $company['email'] }}" class="transition hover:text-volt-400">
                    {{ $company['email'] }}
                </a>
            </div>
        </div>
    </div>

    {{-- Barre principale --}}
    <div class="container-site flex h-20 items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="flex items-center" aria-label="Accueil {{ $company['name'] }} — {{ $company['baseline'] }}">
            <img
                src="{{ asset($company['logo']['wide']) }}"
                alt="{{ $company['name'] }} — {{ $company['baseline'] }}"
                class="h-14 w-auto"
            >
        </a>

        {{-- Navigation desktop --}}
        <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigation principale">
            <a
                href="{{ route('home') }}"
                @class([
                    'rounded-md px-3 py-2 text-sm font-semibold transition hover:text-brand-600',
                    'text-brand-600' => request()->routeIs('home'),
                    'text-ink-800' => ! request()->routeIs('home'),
                ])
            >
                Accueil
            </a>

            {{-- Menu déroulant services --}}
            <div class="relative" @mouseenter="services = true" @mouseleave="services = false">
                <button
                    type="button"
                    @click="services = ! services"
                    class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-semibold text-ink-800 transition hover:text-brand-600"
                    :aria-expanded="services"
                >
                    Nos services
                    <svg class="h-4 w-4 transition" :class="services && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-cloak
                    x-show="services"
                    x-transition.origin.top
                    class="absolute left-0 top-full w-80 overflow-hidden rounded-xl border border-mist-200 bg-white p-2 shadow-xl"
                >
                    @foreach ($site['services'] as $service)
                        <a
                            href="{{ route($service['route']) }}"
                            class="flex gap-3 rounded-lg p-3 transition hover:bg-mist-100"
                        >
                            <span class="font-display text-sm font-extrabold text-volt-600">{{ $service['number'] }}</span>
                            <span>
                                <span class="block text-sm font-bold text-ink-900">{{ $service['name'] }}</span>
                                <span class="mt-0.5 block text-xs text-ink-700/70">{{ $service['short'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            @foreach ($site['navigation'] as $item)
                @continue($item['route'] === 'home')
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'rounded-md px-3 py-2 text-sm font-semibold transition hover:text-brand-600',
                        'text-brand-600' => request()->routeIs($item['route']),
                        'text-ink-800' => ! request()->routeIs($item['route']),
                    ])
                >
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('quote') }}"
                class="hidden items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-brand-600 lg:inline-flex"
            >
                Demander un devis
            </a>

            {{-- Bouton menu mobile --}}
            <button
                type="button"
                @click="open = ! open"
                class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-mist-200 text-ink-900 lg:hidden"
                :aria-expanded="open"
                aria-label="Ouvrir le menu"
            >
                <svg x-show="! open" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-cloak x-show="open" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Menu mobile --}}
    <div x-cloak x-show="open" x-transition class="border-t border-mist-200 bg-white lg:hidden">
        <nav class="container-site flex flex-col gap-1 py-4">
            <a href="{{ route('home') }}" class="rounded-lg px-3 py-3 text-base font-semibold hover:bg-mist-100">Accueil</a>

            <p class="px-3 pt-3 text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Nos services</p>
            @foreach ($site['services'] as $service)
                <a href="{{ route($service['route']) }}" class="rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-mist-100">
                    <span class="me-2 font-display text-volt-600">{{ $service['number'] }}</span>{{ $service['name'] }}
                </a>
            @endforeach

            @foreach ($site['navigation'] as $item)
                @continue($item['route'] === 'home')
                <a href="{{ route($item['route']) }}" class="rounded-lg px-3 py-3 text-base font-semibold hover:bg-mist-100">
                    {{ $item['label'] }}
                </a>
            @endforeach

            <a href="{{ route('quote') }}" class="mt-3 inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-base font-bold text-white">
                Demander un devis
            </a>
        </nav>
    </div>
</header>
