@if ($entry['kind'] === 'event')
    @if ($entry['editable'])
        <div
            class="c2me-cal__event c2me-cal__event--personal"
            title="{{ $entry['time'].' - '.$entry['label'].' : '.$entry['subject'] }}"
        >
            <button
                type="button"
                class="c2me-cal__event-main"
                wire:click="editCalendarEvent({{ $entry['id'] }})"
            >
                <span class="c2me-cal__event-time">{{ $entry['time'] }}</span>
                {{ $entry['subject'] }}
            </button>

            <button
                type="button"
                class="c2me-cal__event-delete"
                wire:click="deleteCalendarEvent({{ $entry['id'] }})"
                wire:confirm="Supprimer cet événement ?"
                title="Supprimer l’événement"
            >
                ×
            </button>
        </div>
    @else
        <div
            class="c2me-cal__event c2me-cal__event--personal c2me-cal__event--shared"
            title="{{ $entry['time'].' - '.$entry['label'].' : '.$entry['subject'] }}"
        >
            <span class="c2me-cal__event-time">{{ $entry['time'] }}</span>
            {{ $entry['subject'] }}
            <span class="c2me-cal__tag">Partagé</span>
        </div>
    @endif
@else
    <div
        @class(['c2me-cal__event', 'c2me-cal__event--late' => $entry['overdue']])
        title="{{ $entry['time'].' - '.$entry['label'].' : '.$entry['subject'] }}"
    >
        <span class="c2me-cal__event-time">{{ $entry['time'] }}</span>
        {{ $entry['subject'] }}
    </div>
@endif
