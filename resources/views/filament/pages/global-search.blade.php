<x-filament-panels::page>
    @php
        $groups = $this->getResults();
        $term = trim($searchQuery);
        $hasFilters = filled($book) || filled($stage) || filled($status);
        $icons = [
            'Comptes' => 'heroicon-o-building-office-2',
            'Contacts' => 'heroicon-o-user',
            'Affaires' => 'heroicon-o-briefcase',
            'Devis' => 'heroicon-o-document-text',
        ];
    @endphp

    <style>
        .c2me-search-results mark {
            background: #fde68a;
            color: inherit;
            padding: 0 2px;
            border-radius: 2px;
        }

        .c2me-search-result {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: .25rem 1rem;
            padding: .65rem .35rem;
            border-bottom: 1px solid rgba(0, 0, 0, .07);
            text-decoration: none;
            color: inherit;
            outline: none;
        }

        .c2me-search-result:last-child {
            border-bottom: none;
        }

        .c2me-search-result:hover,
        .c2me-search-result:focus {
            background: rgba(14, 133, 119, .06);
        }

        .c2me-search-result__name {
            font-weight: 600;
        }

        .c2me-search-result__meta {
            font-size: .85rem;
            color: #6b7280;
        }

        .c2me-search-filters {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: flex-end;
        }

        .c2me-search-filters__label {
            display: block;
            margin-bottom: .25rem;
            font-size: .75rem;
            font-weight: 600;
            color: #6b7280;
        }
    </style>

    <div style="display: flex; flex-direction: column; gap: .75rem; max-width: 56rem;">
        <div class="c2me-search-filters">
            <div style="flex: 1 1 22rem; min-width: 16rem;">
                <label class="c2me-search-filters__label" for="c2me-search-input">
                    Rechercher
                </label>
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        id="c2me-search-input"
                        x-ref="searchInput"
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Compte, contact, affaire ou devis…"
                        autofocus
                        @keydown.arrow-down.prevent="$refs.results?.querySelector('a')?.focus()"
                        @keydown.enter.prevent="$refs.results?.querySelector('a')?.click()"
                    />
                </x-filament::input.wrapper>
            </div>

            <div style="width: 13rem;">
                <label class="c2me-search-filters__label" for="c2me-search-book">
                    Carnet
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="c2me-search-book" wire:model.live="book">
                        <option value="">Tous les carnets</option>
                        @foreach (\App\Enums\CrmBook::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div style="width: 12rem;">
                <label class="c2me-search-filters__label" for="c2me-search-stage">
                    Statut d'affaire
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="c2me-search-stage" wire:model.live="stage">
                        <option value="">Tous les statuts</option>
                        @foreach (\App\Enums\OpportunityStage::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            <div style="width: 12rem;">
                <label class="c2me-search-filters__label" for="c2me-search-status">
                    Statut de devis
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="c2me-search-status" wire:model.live="status">
                        <option value="">Tous les statuts</option>
                        @foreach (\App\Enums\QuoteDocumentStatus::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->getLabel() }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            @if ($hasFilters)
                <x-filament::button color="gray" size="sm" wire:click="resetFilters">
                    Réinitialiser
                </x-filament::button>
            @endif
        </div>

        <p style="font-size: .8rem; color: #6b7280; margin: 0;">
            Saisissez au moins 2 caractères. La recherche couvre tous les carnets.
            Utilisez ↑ / ↓ pour parcourir les résultats et Entrée pour ouvrir.
        </p>
    </div>

    @if (mb_strlen($term) >= 2)
        @if ($groups === [])
            <x-filament::section heading="Aucun résultat">
                <p style="color: #6b7280; margin: 0;">
                    Aucun compte, contact, affaire ou devis ne correspond à « {{ $term }} ».
                    @if ($hasFilters)
                        Essayez d'élargir les filtres.
                    @endif
                </p>
            </x-filament::section>
        @else
            <div x-ref="results" class="c2me-search-results" style="display: flex; flex-direction: column; gap: 1rem;">
                @foreach ($groups as $label => $group)
                    <x-filament::section>
                        <x-slot name="heading">
                            <span style="display: inline-flex; align-items: center; gap: .5rem;">
                                <x-filament::icon
                                    :icon="$icons[$label] ?? 'heroicon-o-document'"
                                    style="width: 1.1rem; height: 1.1rem;"
                                />
                                {{ $label }}
                                <x-filament::badge color="gray">{{ $group['total'] }}</x-filament::badge>
                            </span>
                        </x-slot>

                        <div>
                            @foreach ($group['items'] as $item)
                                <a
                                    href="{{ $item['url'] }}"
                                    class="c2me-search-result"
                                    @keydown.arrow-down.prevent="$el.nextElementSibling?.focus()"
                                    @keydown.arrow-up.prevent="($el.previousElementSibling ?? $refs.searchInput)?.focus()"
                                >
                                    <span class="c2me-search-result__name">{!! $this->highlight($item['name']) !!}</span>
                                    @if (filled($item['description']))
                                        <span class="c2me-search-result__meta">{!! $this->highlight($item['description']) !!}</span>
                                    @endif
                                </a>
                            @endforeach

                            @if ($group['total'] > 10)
                                <div style="margin-top: .5rem;">
                                    <x-filament::link
                                        tag="button"
                                        wire:click="toggleGroup('{{ $label }}')"
                                    >
                                        {{ $group['expanded']
                                            ? 'Réduire'
                                            : 'Voir les ' . $group['total'] . ' résultats' }}
                                    </x-filament::link>
                                </div>
                            @endif
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
