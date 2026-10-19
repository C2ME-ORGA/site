<x-filament-panels::page>
    <div class="c2me-cal">
        <div class="c2me-cal__toolbar">
            <div class="c2me-cal__views">
                @foreach (['month' => 'Mois', 'week' => 'Semaine', 'day' => 'Jour'] as $mode => $label)
                    <a
                        href="{{ $this->viewUrl($mode) }}"
                        @class(['c2me-cal__view', 'c2me-cal__view--active' => $this->mode === $mode])
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="c2me-cal__nav">
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-chevron-left"
                    href="{{ $this->previousUrl() }}"
                    tag="a"
                >
                    Précédent
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    href="{{ $this->todayUrl() }}"
                    tag="a"
                >
                    Aujourd’hui
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    icon="heroicon-o-chevron-right"
                    icon-position="after"
                    href="{{ $this->nextUrl() }}"
                    tag="a"
                >
                    Suivant
                </x-filament::button>
            </div>
        </div>

        @if ($this->mode === 'month')
            <div class="c2me-cal__grid">
                <div class="c2me-cal__dow">Lun</div>
                <div class="c2me-cal__dow">Mar</div>
                <div class="c2me-cal__dow">Mer</div>
                <div class="c2me-cal__dow">Jeu</div>
                <div class="c2me-cal__dow">Ven</div>
                <div class="c2me-cal__dow">Sam</div>
                <div class="c2me-cal__dow">Dim</div>

                @foreach ($this->weeks() as $week)
                    @foreach ($week as $day)
                        <div @class(['c2me-cal__day', 'c2me-cal__day--muted' => ! $day['inMonth']])>
                            <span @class(['c2me-cal__num', 'c2me-cal__num--today' => $day['isToday']])>
                                {{ $day['day'] }}
                            </span>

                            <div class="c2me-cal__events">
                                @foreach ($day['activities'] as $activity)
                                    <div @class(['c2me-cal__event', 'c2me-cal__event--late' => $activity['overdue']])
                                        title="{{ $activity['time'].' — '.$activity['label'].' : '.$activity['subject'] }}">
                                        <span class="c2me-cal__event-time">{{ $activity['time'] }}</span>
                                        {{ $activity['subject'] }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        @elseif ($this->mode === 'week')
            @php($weekDays = $this->weekDays())

            <div class="c2me-cal__grid">
                @foreach ($weekDays as $day)
                    <div @class(['c2me-cal__dow', 'c2me-cal__dow--today' => $day['isToday']])>
                        {{ $day['weekday'] }}<br>
                        <span class="c2me-cal__dow-num">{{ $day['day'] }}</span>
                    </div>
                @endforeach

                @foreach ($weekDays as $day)
                    <div class="c2me-cal__day c2me-cal__day--tall">
                        <div class="c2me-cal__events">
                            @forelse ($day['activities'] as $activity)
                                <div @class(['c2me-cal__event', 'c2me-cal__event--late' => $activity['overdue']])
                                    title="{{ $activity['time'].' — '.$activity['label'].' : '.$activity['subject'] }}">
                                    <span class="c2me-cal__event-time">{{ $activity['time'] }}</span>
                                    {{ $activity['subject'] }}
                                </div>
                            @empty
                                <span class="c2me-cal__empty">—</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="c2me-cal__day-view">
                @forelse ($this->dayActivities() as $activity)
                    <div class="c2me-cal__day-row">
                        <div class="c2me-cal__day-time">{{ $activity['time'] }}</div>
                        <div class="c2me-cal__day-body">
                            <div class="c2me-cal__day-subject">
                                {{ $activity['subject'] }}
                                @if ($activity['overdue'])
                                    <span class="c2me-cal__tag c2me-cal__tag--late">En retard</span>
                                @endif
                            </div>
                            <div class="c2me-cal__day-meta">
                                {{ $activity['label'] }}@if ($activity['account']) · {{ $activity['account'] }}@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="c2me-cal__empty-box">Aucune action prévue pour cette journée.</div>
                @endforelse
            </div>
        @endif
    </div>

    <style>
        .c2me-cal__toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .c2me-cal__views {
            display: inline-flex;
            padding: 0.25rem;
            border-radius: 0.6rem;
            background: rgba(107, 114, 128, 0.1);
            gap: 0.25rem;
        }

        .c2me-cal__view {
            padding: 0.35rem 0.9rem;
            border-radius: 0.4rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4b5563;
            text-decoration: none;
        }

        .c2me-cal__view--active {
            background: #0e8577;
            color: #fff;
        }

        .c2me-cal__nav {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .c2me-cal__grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            border: 1px solid rgba(107, 114, 128, 0.2);
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .c2me-cal__dow {
            padding: 0.75rem 0.5rem;
            text-align: center;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            background: rgba(107, 114, 128, 0.08);
            border-bottom: 1px solid rgba(107, 114, 128, 0.2);
        }

        .c2me-cal__dow--today {
            color: #0b6a5e;
        }

        .c2me-cal__dow-num {
            font-size: 0.95rem;
        }

        .c2me-cal__day {
            min-height: 6rem;
            padding: 0.5rem;
            border-right: 1px solid rgba(107, 114, 128, 0.12);
            border-bottom: 1px solid rgba(107, 114, 128, 0.12);
        }

        .c2me-cal__day--tall {
            min-height: 16rem;
        }

        .c2me-cal__day:nth-child(7n) {
            border-right: none;
        }

        .c2me-cal__day--muted {
            background: rgba(107, 114, 128, 0.06);
        }

        .c2me-cal__num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
        }

        .c2me-cal__num--today {
            background: #0e8577;
            color: #fff;
        }

        .c2me-cal__events {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            margin-top: 0.25rem;
        }

        .c2me-cal__event {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.7rem;
            line-height: 1.25rem;
            background: rgba(14, 133, 119, 0.12);
            color: #0b6a5e;
        }

        .c2me-cal__event--late {
            background: rgba(220, 38, 38, 0.12);
            color: #b91c1c;
        }

        .c2me-cal__event-time {
            font-weight: 600;
        }

        .c2me-cal__empty {
            color: #9ca3af;
            font-size: 0.75rem;
        }

        .c2me-cal__day-view {
            border: 1px solid rgba(107, 114, 128, 0.2);
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .c2me-cal__day-row {
            display: flex;
            gap: 1rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(107, 114, 128, 0.12);
        }

        .c2me-cal__day-row:last-child {
            border-bottom: none;
        }

        .c2me-cal__day-time {
            min-width: 3.5rem;
            font-weight: 700;
            color: #0b6a5e;
        }

        .c2me-cal__day-subject {
            font-weight: 600;
            color: #111827;
        }

        .c2me-cal__day-meta {
            margin-top: 0.15rem;
            font-size: 0.8rem;
            color: #6b7280;
        }

        .c2me-cal__tag {
            margin-left: 0.4rem;
            padding: 0.05rem 0.4rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .c2me-cal__tag--late {
            background: rgba(220, 38, 38, 0.12);
            color: #b91c1c;
        }

        .c2me-cal__empty-box {
            padding: 2rem 1rem;
            text-align: center;
            color: #9ca3af;
        }

        .dark .c2me-cal__num,
        .dark .c2me-cal__day-subject {
            color: #e5e7eb;
        }

        .dark .c2me-cal__view {
            color: #d1d5db;
        }

        .dark .c2me-cal__event {
            background: rgba(20, 184, 166, 0.18);
            color: #99f6e4;
        }

        .dark .c2me-cal__event--late {
            background: rgba(248, 113, 113, 0.18);
            color: #fecaca;
        }
    </style>
</x-filament-panels::page>
