<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
        Les comptes inactifs sont masqués par défaut. Cette page regroupe les relances dues aujourd’hui ou déjà en retard.
    </div>

    {{ $this->table }}
</x-filament-panels::page>
