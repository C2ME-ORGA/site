@if (filled($html))
    <div
        wire:ignore
        data-email-html="{{ $html }}"
        x-data="{
            init() {
                const host = this.$refs.host

                if (! host.shadowRoot) {
                    host.attachShadow({ mode: 'open' }).innerHTML = this.$el.dataset.emailHtml
                }
            },
        }"
        style="border: 1px solid rgba(128, 128, 128, .25); border-radius: .5rem; overflow: hidden; background: #fff;"
    >
        {{-- Le Shadow DOM isole le CSS du message sans affecter l'interface d'administration. --}}
        <div x-ref="host"></div>
    </div>
@elseif (filled($text))
    <div style="font-size: .875rem; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere;">
        {{ $text }}
    </div>
@else
    <p class="fi-in-placeholder">
        (message vide)
    </p>
@endif
