<div wire:poll.30s>
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
