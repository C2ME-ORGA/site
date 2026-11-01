{{--
    Mention RGPD affichée sous les formulaires publics. La base légale des
    demandes de contact et de devis est l’intérêt légitime / les mesures
    précontractuelles : aucune case à cocher n’est donc demandée, mais
    l’information sur la finalité et les droits reste obligatoire.
--}}
<p {{ $attributes->merge(['class' => 'text-xs leading-relaxed text-ink-700/70']) }}>
    Les informations transmises servent uniquement à traiter votre demande et à y répondre.
    Elles ne sont ni vendues ni cédées. Vous disposez d’un droit d’accès, de rectification,
    d’effacement et d’opposition : consultez la
    <a
        href="{{ route('legal.privacy') }}"
        class="font-semibold text-brand-700 underline underline-offset-2 hover:text-brand-800"
    >politique de confidentialité</a>.
</p>
