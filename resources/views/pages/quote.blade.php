<x-layouts.app title="Demander un devis" description="Demandez un devis à C2ME : découpe laser, études et charpente métallique en Vendée et sur toute la France.">

    <x-ui.page-hero
        eyebrow="Devis"
        title="Demander un devis"
        lead="Décrivez votre projet : nous vous répondons avec une proposition adaptée."
    >
        <x-slot:actions>
            <x-ui.button href="tel:+33251381676" variant="volt" size="lg">02 51 38 16 76</x-ui.button>
            <x-ui.button href="mailto:contact@c2me.fr" variant="ghost-light" size="lg">Nous écrire</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <section class="py-16 lg:py-20">
        <div class="container-site grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-dashed border-mist-300 bg-mist-50 p-10 text-center">
                    <span class="inline-grid h-12 w-12 place-items-center rounded-full bg-brand-500/10 text-brand-600">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M4.93 4.93a10 10 0 1 1 14.14 14.14A10 10 0 0 1 4.93 4.93Z" />
                        </svg>
                    </span>
                    <h2 class="mt-5 font-display text-xl font-extrabold text-ink-900">
                        Formulaire en cours de développement
                    </h2>
                    <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-ink-700/70">
                        Le formulaire de demande de devis, avec envoi de vos plans
                        (PDF, DXF, DWG), sera disponible prochainement.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                        <x-ui.button href="tel:+33251381676" variant="primary">Appeler le 02 51 38 16 76</x-ui.button>
                        <x-ui.button href="mailto:contact@c2me.fr" variant="outline">contact@c2me.fr</x-ui.button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="rounded-2xl border border-mist-200 bg-white p-6">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Préparer votre demande</p>
                    <ul class="mt-4 space-y-3 text-sm leading-relaxed text-ink-700/70">
                        <li class="flex gap-2"><span class="text-brand-600">•</span> La nature du projet (pièce, charpente, étude)</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> Les quantités ou tonnage visé</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> La matière et l'épaisseur</li>
                        <li class="flex gap-2"><span class="text-brand-600">•</span> Votre délai souhaité</li>
                    </ul>
                </div>

                <div class="rounded-2xl bg-ink-900 p-6 text-white">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-volt-400">Pièces urgentes</p>
                    <p class="mt-3 text-sm leading-relaxed text-mist-300/80">
                        Un besoin urgent ? Appelez-nous directement : nous étudions
                        la faisabilité selon les disponibilités de l'atelier.
                    </p>
                </div>
            </div>
        </div>
    </section>

</x-layouts.app>
