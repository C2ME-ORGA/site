@php
    $site ??= config('site');

    $photos = $project->images
        ->map(fn ($image): array => [
            'src' => $image->url(),
            'thumb' => $image->thumbUrl(),
            'alt' => $image->alt ?: 'Réalisation C2ME — '.$project->title,
            'caption' => $image->alt ?: $project->title,
        ])
        ->values()
        ->all();
@endphp

<x-layouts.app
    :title="$project->title.' — Réalisations'"
    :description="$project->excerpt(160)"
>

    <section class="border-b border-mist-200 bg-mist-50">
        <div class="container-site py-10 lg:py-14">
            <nav aria-label="Fil d’Ariane" class="text-xs font-semibold text-ink-700/70">
                <ol class="flex flex-wrap items-center gap-2">
                    <li><a href="{{ route('home') }}" class="transition hover:text-brand-600">Accueil</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('projects') }}" class="transition hover:text-brand-600">Réalisations</a></li>
                    <li aria-hidden="true">/</li>
                    <li><span aria-current="page" class="text-ink-900">{{ $project->title }}</span></li>
                </ol>
            </nav>

            <div class="mt-6 grid items-start gap-10 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    @if ($project->category)
                        <p class="eyebrow">{{ $project->category->name }}</p>
                    @endif

                    <h1 class="mt-3 font-display text-3xl font-extrabold leading-tight tracking-tight text-ink-900 sm:text-4xl">
                        {{ $project->title }}
                    </h1>

                    <dl class="mt-5 flex flex-wrap gap-x-8 gap-y-3 text-sm">
                        @if ($project->client)
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-brand-600">Maître d’ouvrage</dt>
                                <dd class="mt-1 font-semibold text-ink-900">{{ $project->client }}</dd>
                            </div>
                        @endif

                        @if ($project->location)
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-brand-600">Commune</dt>
                                <dd class="mt-1 font-semibold text-ink-900">{{ $project->location }}</dd>
                            </div>
                        @endif

                        @if ($project->year)
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-[0.12em] text-brand-600">Livraison</dt>
                                <dd class="mt-1 font-semibold text-ink-900">{{ $project->year }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if (filled($project->services))
                        <ul class="mt-6 flex flex-wrap gap-2">
                            @foreach ($project->services as $service)
                                <li class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
                                    {{ $service }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (filled($project->description))
                        <div class="mt-6 max-w-2xl text-base leading-relaxed text-ink-700/80">
                            {!! nl2br(e($project->description)) !!}
                        </div>
                    @endif
                </div>

                <aside class="lg:col-span-4">
                    <div class="rounded-2xl border border-mist-200 bg-white p-6">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Un projet similaire ?</p>
                        <p class="mt-3 text-sm leading-relaxed text-ink-700/70">
                            Décrivez-nous votre besoin : nous étudions votre demande et vous
                            répondons par un devis adapté.
                        </p>
                        <div class="mt-5 flex flex-col gap-3">
                            <x-ui.button :href="route('quote')" variant="primary">Demander un devis</x-ui.button>
                            <x-ui.button :href="route('contact')" variant="outline">Nous contacter</x-ui.button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <section class="py-16 lg:py-20">
        <div class="container-site">
            <div class="max-w-2xl">
                <p class="eyebrow">Photos du chantier</p>
                <h2 class="mt-3 font-display text-2xl font-extrabold tracking-tight text-ink-900 sm:text-3xl">
                    {{ $project->title }} en images
                </h2>
            </div>

            <div class="mt-8">
                @if ($photos !== [])
                    <x-projects.photo-grid :photos="$photos" />
                @else
                    <p class="rounded-2xl border border-dashed border-mist-300 bg-mist-50 p-8 text-center text-sm text-ink-700/70">
                        Les photos de ce chantier seront bientôt disponibles.
                    </p>
                @endif
            </div>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="border-t border-mist-200 bg-mist-50 py-16 lg:py-20">
            <div class="container-site">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="eyebrow">À découvrir aussi</p>
                        <h2 class="mt-3 font-display text-2xl font-extrabold tracking-tight text-ink-900 sm:text-3xl">
                            Autres chantiers
                        </h2>
                    </div>
                    <a href="{{ route('projects') }}" class="text-sm font-bold text-brand-600 transition hover:text-brand-700">
                        Toutes les réalisations
                    </a>
                </div>

                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $item)
                        <x-projects.card :project="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <x-ui.page-cta
        title="Votre projet pourrait être le prochain"
        :text="'Décrivez-nous votre besoin : nous étudions votre demande et vous répondons par un devis adapté.'"
        primary-label="Demander un devis"
        :primary-href="route('quote')"
        secondary-label="Nous contacter"
        :secondary-href="route('contact')"
    />

</x-layouts.app>
