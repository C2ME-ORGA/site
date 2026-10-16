@php
    $site = config('site');
@endphp

<x-layouts.app :title="$title ?? null" :description="$message ?? null">

    <section class="border-b border-mist-200 bg-mist-100">
        <div class="container-site py-16 lg:py-20">
            <p class="eyebrow">{{ $eyebrow ?? 'C2ME' }}</p>
            <h1 class="mt-4 max-w-3xl font-display text-4xl font-extrabold tracking-tight text-ink-900 sm:text-5xl">
                {{ $heading ?? $title }}
            </h1>
            @isset($message)
                <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-700/70">{{ $message }}</p>
            @endisset
        </div>
    </section>

    <section class="py-20 lg:py-24">
        <div class="container-site">
            <div class="mx-auto max-w-2xl rounded-2xl border border-dashed border-mist-300 bg-white p-10 text-center">
                <span class="inline-grid h-12 w-12 place-items-center rounded-full bg-brand-500/10 text-brand-600">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M4.93 4.93a10 10 0 1 1 14.14 14.14A10 10 0 0 1 4.93 4.93Z" />
                    </svg>
                </span>
                <h2 class="mt-5 font-display text-xl font-extrabold text-ink-900">Page en cours de construction</h2>
                <p class="mt-3 text-sm leading-relaxed text-ink-700/70">
                    Le contenu détaillé de cette rubrique sera ajouté lors des prochaines étapes
                    du développement. En attendant, contactez-nous directement pour toute demande.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    <x-ui.button :href="route('quote')" variant="primary">Demander un devis</x-ui.button>
                    <x-ui.button :href="route('home')" variant="outline">Retour à l'accueil</x-ui.button>
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
