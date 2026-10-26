{{--
    Cible du widget « nouveautés » Olvy.

    L'espace c2meapp est configuré avec `targetElement: "#olvy-whats-new"` :
    Olvy cherche cet élément au chargement, y accroche l'ouverture du modal et
    y affiche le compteur de notes non lues. S'il est absent du DOM, le script
    journalise « [Olvy] - couldn't setup the target element ».
--}}
<x-filament::icon-button
    id="olvy-whats-new"
    color="gray"
    icon="heroicon-o-megaphone"
    label="Nouveautés"
    tag="button"
    type="button"
/>
