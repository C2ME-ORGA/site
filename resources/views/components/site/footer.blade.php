@props(['site'])

@php
    $company = $site['company'];
@endphp

<footer class="bg-ink-950 text-mist-300">
    <div class="container-site grid gap-12 py-16 lg:grid-cols-4">
        {{-- Marque --}}
        <div class="lg:col-span-2">
            <div class="flex items-center gap-3">
                <img
                    src="{{ asset($company['logo']['mark']) }}"
                    alt="{{ $company['name'] }}"
                    class="h-12 w-auto"
                >
                <span class="leading-none">
                    <span class="block font-display text-xl font-extrabold tracking-tight text-white">{{ $company['name'] }}</span>
                    <span class="mt-1 block text-[11px] font-semibold uppercase tracking-[0.12em] text-brand-300">
                        {{ $company['sector'] }}
                    </span>
                </span>
            </div>
            <p class="mt-5 font-display text-sm font-bold text-volt-400">
                « {{ $company['baseline'] }} »
            </p>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-mist-300/80">
                {{ $company['description'] }}
            </p>
        </div>

        {{-- Services --}}
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-volt-400">Nos services</h2>
            <ul class="mt-5 space-y-3 text-sm">
                @foreach ($site['services'] as $service)
                    <li>
                        <a href="{{ route($service['route']) }}" class="transition hover:text-white">
                            {{ $service['name'] }}
                        </a>
                    </li>
                @endforeach
                <li><a href="{{ route('quote') }}" class="transition hover:text-white">Demander un devis</a></li>
            </ul>
        </div>

        {{-- Contact --}}
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-volt-400">Contact</h2>
            <address class="mt-5 space-y-4 text-sm not-italic">
                @foreach ($company['locations'] as $location)
                    <p class="leading-relaxed">
                        <span class="block text-xs font-bold uppercase tracking-[0.16em] text-volt-400">
                            {{ $location['label'] }}
                        </span>
                        <span class="mt-1 block text-mist-300/80">
                            {{ $location['line1'] }}<br>
                            {{ $location['line2'] }}<br>
                            {{ $location['postal_code'] }} {{ $location['city'] }}<br>
                            {{ $location['region'] }}
                        </span>
                    </p>
                @endforeach
                <p>
                    <a href="tel:{{ $company['phone_link'] }}" class="block transition hover:text-white">{{ $company['phone'] }}</a>
                    <a href="tel:{{ $company['mobile_link'] }}" class="block transition hover:text-white">{{ $company['mobile'] }}</a>
                    <a href="mailto:{{ $company['email'] }}" class="block transition hover:text-white">{{ $company['email'] }}</a>
                </p>
            </address>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="container-site flex flex-col items-center justify-between gap-4 py-6 text-xs text-mist-300/60 sm:flex-row">
            <p>© {{ date('Y') }} {{ $company['name'] }} — Tous droits réservés.</p>
            <div class="flex items-center gap-6">
                <a href="{{ route('legal.mentions') }}" class="transition hover:text-white">Mentions légales</a>
                <a href="{{ route('legal.privacy') }}" class="transition hover:text-white">Confidentialité</a>
            </div>
        </div>
    </div>
</footer>
