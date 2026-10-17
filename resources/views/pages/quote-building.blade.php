<x-layouts.app title="Devis bâtiment & charpente" description="Décrivez votre projet de bâtiment ou de charpente métallique C2ME : implantation, enveloppe, calendrier et plans.">

    <x-ui.page-hero
        eyebrow="Devis — Bâtiment & charpente"
        title="Votre projet de bâtiment"
        lead="Six étapes pour qualifier votre chantier. Plus le brief est précis, plus notre proposition le sera."
    >
        <x-slot:actions>
            <x-ui.button href="tel:+33251381676" variant="volt" size="lg">02 51 38 16 76</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <section class="py-16 lg:py-20">
        <div class="container-site grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-mist-200 bg-white p-6 sm:p-10">
                    <livewire:building-quote />
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Ce que nous étudions</p>
                    <ul class="mt-4 space-y-3 text-sm leading-relaxed text-ink-700/70">
                        <li class="flex gap-2"><span class="text-brand-600">•</span> La faisabilité de la structure</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> Le transport jusqu'au chantier</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> Les conditions de levage et de montage</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> Le calendrier de fabrication</li>
                    </ul>
                </div>

                <div class="rounded-2xl bg-ink-900 p-6 text-white">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-volt-400">Du relevé au montage</p>
                    <p class="mt-3 text-sm leading-relaxed text-mist-300/80">
                        Nous couvrons l'ensemble de la chaîne : dessin et ingénierie,
                        fabrication en atelier, traitement anticorrosion, livraison
                        et pose par nos équipes.
                    </p>
                </div>

                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Vos documents restent confidentiels</p>
                    <p class="mt-3 text-sm leading-relaxed text-ink-700/70">
                        Les plans déposés sont stockés sur un espace privé, réservé
                        à notre bureau d'études, et supprimés automatiquement au-delà
                        de trois mois.
                    </p>
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
