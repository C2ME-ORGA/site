<div
    wire:poll.30s
    @if ($channel)
        x-data="{
            init() {
                const refresh = () => this.$wire.$refresh();
                const listen = () => window.Echo
                    .private(@js($channel))
                    .listen('.database-notifications.sent', refresh);

                if (window.Echo) {
                    listen();
                } else {
                    window.addEventListener('EchoLoaded', listen, { once: true });
                }
            },
        }"
    @endif
>
    <x-filament::icon-button
        :badge="$unread"
        badge-color="warning"
        color="gray"
        :href="$url"
        icon="heroicon-o-inbox"
        label="Boîte de réception"
        tag="a"
    />
</div>
