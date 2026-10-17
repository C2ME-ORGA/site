<x-layouts.app title="Devis découpe laser" description="Demandez un devis de découpe laser C2ME : matière, épaisseur, quantité et plans PDF, DXF ou DWG.">

    <x-ui.page-hero
        eyebrow="Devis — Découpe laser"
        title="Votre projet de découpe laser"
        lead="Décrivez votre besoin en quatre étapes. Plus vos informations sont précises, plus notre devis le sera."
    >
        <x-slot:actions>
            <x-ui.button href="tel:+33251381676" variant="volt" size="lg">02 51 38 16 76</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <section class="py-16 lg:py-20">
        <div class="container-site grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-mist-200 bg-white p-6 sm:p-10">
                    <livewire:laser-quote />
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Préparer votre demande</p>
                    <ul class="mt-4 space-y-3 text-sm leading-relaxed text-ink-700/70">
                        <li class="flex gap-2"><span class="text-brand-600">•</span> La matière et l'épaisseur visée</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> Le nombre de pièces et le tonnage</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> Vos fichiers de découpe (DXF, DWG) ou vos plans (PDF)</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> Votre délai et votre mode d'enlèvement</li>
                    </ul>
                </div>

                <div class="rounded-2xl bg-ink-900 p-6 text-white">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-volt-400">Capacités machine</p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-mist-300/70">Technologie</dt>
                            <dd class="font-bold">Fibre 8 kW</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-mist-300/70">Format de travail</dt>
                            <dd class="font-bold">4 000 × 2 000 mm</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-mist-300/70">Acier</dt>
                            <dd class="font-bold">jusqu'à 30 mm</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-mist-300/70">Inox</dt>
                            <dd class="font-bold">jusqu'à 20 mm</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-mist-300/70">Aluminium</dt>
                            <dd class="font-bold">jusqu'à 10 mm</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Vos plans restent confidentiels</p>
                    <p class="mt-3 text-sm leading-relaxed text-ink-700/70">
                        Les fichiers déposés sont stockés sur un espace privé, réservé
                        à notre bureau d'études, et supprimés automatiquement au-delà
                        de trois mois.
                    </p>
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
