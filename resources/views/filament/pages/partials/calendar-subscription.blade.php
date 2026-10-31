<div style="display: grid; gap: 1rem; font-size: 0.875rem;">
    <div x-data="{ copied: false }" style="display: grid; gap: 0.5rem;">
        <label style="font-weight: 600;" for="calendar-feed-url">Adresse d’abonnement</label>

        <x-filament::input.wrapper>
            <x-filament::input
                id="calendar-feed-url"
                type="text"
                readonly
                value="{{ $webcalUrl }}"
                x-ref="feed"
            />
        </x-filament::input.wrapper>

        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
            <x-filament::button
                type="button"
                size="sm"
                color="gray"
                x-on:click="if (navigator.clipboard) { navigator.clipboard.writeText($refs.feed.value) } else { $refs.feed.select() }; copied = true; setTimeout(() => copied = false, 2000)"
            >
                <span x-text="copied ? 'Lien copié' : 'Copier le lien'">Copier le lien</span>
            </x-filament::button>
        </div>

        <p style="font-size: 0.8rem; opacity: 0.75;">
            Version HTTPS : <code>{{ $feedUrl }}</code>
        </p>
    </div>

    <div style="display: grid; gap: 0.35rem;">
        <p><strong>Google Agenda</strong> : Autres agendas → S’abonner à un agenda → À partir d’une URL.</p>
        <p><strong>Outlook</strong> : Ajouter un calendrier → S’abonner à partir du web.</p>
        <p><strong>Apple Calendrier</strong> : Fichier → Nouvel abonnement à un calendrier.</p>
    </div>

    <p style="font-size: 0.8rem; opacity: 0.75;">
        Ce lien est strictement personnel : toute personne qui le possède peut consulter
        l’agenda. Utilisez « Régénérer le lien » s’il a été partagé par erreur.
    </p>
</div>
