@php
    $site = config('site');
    $editor = $site['legal']['editor'];
    $host = $site['legal']['host'];

    /**
     * Sommaire : id d'ancre => libellé. Une entrée par section de la page,
     * dans l'ordre de lecture.
     *
     * @var array<string, string> $anchors
     */
    $anchors = [
        'editeur' => 'Éditeur du site',
        'publication' => 'Direction de la publication',
        'hebergeur' => 'Hébergeur',
        'propriete-intellectuelle' => 'Propriété intellectuelle',
        'responsabilite' => 'Responsabilité',
        'droit-applicable' => 'Droit applicable',
    ];
@endphp

<x-layouts.app
    title="Mentions légales"
    description="Mentions légales du site c2me.fr : éditeur, direction de la publication, hébergeur et propriété intellectuelle."
>

    <x-ui.page-hero
        eyebrow="Informations légales"
        title="Mentions légales"
        lead="Identification de l’éditeur, du directeur de la publication et de l’hébergeur."
    />

    <section class="py-16 lg:py-20">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            {{-- Sommaire --}}
            <nav class="lg:col-span-3" aria-label="Sommaire des mentions légales">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Sommaire</p>

                <ol class="mt-4 space-y-1 border-l border-mist-200">
                    @foreach ($anchors as $id => $label)
                        <li>
                            <a
                                href="#{{ $id }}"
                                class="-ml-px block border-l border-transparent py-1.5 pl-4 text-sm leading-snug text-ink-700/70 transition hover:border-brand-500 hover:text-ink-900"
                            >{{ $label }}</a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <div class="space-y-14 lg:col-span-9 lg:max-w-3xl">
                {{-- ÉDITEUR --}}
                <x-ui.legal-section id="editeur" title="Éditeur du site">
                    <p>
                        Le présent site est édité par
                        <strong class="font-semibold text-ink-900">{{ $editor['legal_name'] }}</strong>,
                        société à responsabilité limitée au capital de {{ $editor['capital'] }}, dont le siège
                        social est situé {{ $editor['address'] }}.
                    </p>

                    <dl class="divide-y divide-mist-200 border-y border-mist-200">
                        <div class="flex items-baseline justify-between gap-6 py-3.5">
                            <dt class="shrink-0 text-sm font-bold uppercase tracking-[0.1em] text-ink-700/70">SIREN</dt>
                            <dd class="text-right text-sm font-semibold tabular-nums text-ink-900">{{ $editor['siren'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6 py-3.5">
                            <dt class="shrink-0 text-sm font-bold uppercase tracking-[0.1em] text-ink-700/70">SIRET du siège</dt>
                            <dd class="text-right text-sm font-semibold tabular-nums text-ink-900">{{ $editor['siret'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6 py-3.5">
                            <dt class="shrink-0 text-sm font-bold uppercase tracking-[0.1em] text-ink-700/70">RCS</dt>
                            <dd class="text-right text-sm font-semibold text-ink-900">RCS {{ $editor['rcs'] }} {{ $editor['siren'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6 py-3.5">
                            <dt class="shrink-0 text-sm font-bold uppercase tracking-[0.1em] text-ink-700/70">TVA intracom.</dt>
                            <dd class="text-right text-sm font-semibold tabular-nums text-ink-900">{{ $editor['vat'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6 py-3.5">
                            <dt class="shrink-0 text-sm font-bold uppercase tracking-[0.1em] text-ink-700/70">Activité (APE)</dt>
                            <dd class="text-right text-sm font-semibold text-ink-900">
                                {{ $editor['ape'] }} — {{ $editor['ape_label'] }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6 py-3.5">
                            <dt class="shrink-0 text-sm font-bold uppercase tracking-[0.1em] text-ink-700/70">Immatriculation</dt>
                            <dd class="text-right text-sm font-semibold text-ink-900">Le {{ $editor['created_on'] }}</dd>
                        </div>
                    </dl>

                    <p>
                        Téléphone :
                        <a href="tel:+33251381676" class="font-semibold text-brand-600 hover:text-brand-700">
                            {{ $editor['phone'] }}
                        </a>
                        —
                        <a href="mailto:{{ $editor['email'] }}" class="font-semibold text-brand-600 hover:text-brand-700">
                            {{ $editor['email'] }}
                        </a>
                    </p>
                </x-ui.legal-section>

                {{-- DIRECTION DE LA PUBLICATION --}}
                <x-ui.legal-section id="publication" title="Direction de la publication">
                    <p>
                        Le directeur de la publication du site est
                        <strong class="font-semibold text-ink-900">{{ $editor['director'] }}</strong>,
                        en sa qualité de {{ $editor['director_role'] }} de {{ $editor['legal_name'] }}.
                    </p>
                </x-ui.legal-section>

                {{-- HÉBERGEUR --}}
                <x-ui.legal-section id="hebergeur" title="Hébergeur">
                    <p>
                        Le site est hébergé par
                        <strong class="font-semibold text-ink-900">{{ $host['name'] }}</strong>,
                        société par actions simplifiée, dont le siège est situé {{ $host['address'] }}.
                    </p>
                    <p>
                        Téléphone :
                        <a href="tel:+33972101007" class="font-semibold text-brand-600 hover:text-brand-700">
                            {{ $host['phone'] }}
                        </a>
                    </p>
                </x-ui.legal-section>

                {{-- PROPRIÉTÉ INTELLECTUELLE --}}
                <x-ui.legal-section id="propriete-intellectuelle" title="Propriété intellectuelle">
                    <p>
                        L’ensemble des éléments composant le site c2me.fr — textes, photographies, logos, charte
                        graphique, structure et code — est protégé par le droit d’auteur et, le cas échéant, par le
                        droit des marques. Ces éléments demeurent la propriété exclusive de
                        {{ $editor['legal_name'] }} ou de ses partenaires ayant concédé une licence.
                    </p>
                    <p>
                        Toute reproduction, représentation, modification ou exploitation, totale ou partielle, sans
                        autorisation écrite préalable de {{ $editor['legal_name'] }} est interdite. La création de
                        liens hypertextes vers le site est libre pour un usage non commercial, sous réserve de ne pas
                        créer de confusion sur l’origine du contenu.
                    </p>
                </x-ui.legal-section>

                {{-- RESPONSABILITÉ --}}
                <x-ui.legal-section id="responsabilite" title="Responsabilité">
                    <p>
                        {{ $editor['legal_name'] }} s’efforce d’assurer l’exactitude et la mise à jour des
                        informations diffusées sur ce site. Les contenus sont fournis à titre indicatif et ne
                        constituent ni une offre contractuelle ni un engagement technique : chaque projet fait
                        l’objet d’une étude et d’un devis propres.
                    </p>
                    <p>
                        {{ $editor['legal_name'] }} ne saurait être tenue responsable des interruptions, erreurs ou
                        omissions affectant le site, ni de l’usage qui pourrait être fait des informations
                        disponibles. Les liens vers des sites tiers sont fournis à titre pratique ;
                        {{ $editor['legal_name'] }} n’exerce aucun contrôle sur leurs contenus.
                    </p>
                </x-ui.legal-section>

                {{-- DROIT APPLICABLE --}}
                <x-ui.legal-section id="droit-applicable" title="Droit applicable">
                    <p>
                        Les présentes mentions légales sont soumises au droit français. En cas de différend, et à
                        défaut d’accord amiable, la compétence revient aux tribunaux du ressort du siège social de
                        {{ $editor['legal_name'] }}.
                    </p>
                    <p class="text-sm text-ink-700/70">
                        Dernière mise à jour : <time datetime="2026-10-17">17 octobre 2026</time>.
                    </p>
                </x-ui.legal-section>
            </div>
        </div>
    </section>
</x-layouts.app>
