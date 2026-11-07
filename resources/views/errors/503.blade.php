<x-ui.error-page
    eyebrow="Maintenance en cours"
    title="Le site est momentanément indisponible"
    lead="Nous effectuons une opération de maintenance. Le service sera rétabli très prochainement : merci de votre patience."
    :image="asset('images/realisations/Albi/photo-01.webp')"
>
    <x-slot:actions>
        <x-ui.button :href="request()->fullUrl()" variant="volt" size="lg">
            Réessayer
        </x-ui.button>
        <x-ui.button :href="'tel:'.config('site.company.phone_link')" variant="ghost-light" size="lg">
            Nous téléphoner
        </x-ui.button>
    </x-slot:actions>
</x-ui.error-page>
