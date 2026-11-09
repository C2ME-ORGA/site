<?php

namespace App\Filament\Pages;

use App\Models\CalendarEvent;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\User;
use App\Services\IcsCalendar;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class Calendar extends Page implements HasKnowledgeBase
{
    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 17;

    protected static ?string $navigationLabel = 'Calendrier';

    protected static ?string $title = 'Calendrier';

    /**
     * Vue active : « month », « week » ou « day ».
     */
    public string $mode = 'month';

    /**
     * Date d'ancrage (Y-m-d) : sert de repère à la vue affichée.
     */
    public string $date = '';

    public function mount(): void
    {
        $mode = request()->query('view');
        $this->mode = is_string($mode) && in_array($mode, ['month', 'week', 'day'], true)
            ? $mode
            : (string) config('crm.calendar.default_view', 'month');

        if (! in_array($this->mode, ['month', 'week', 'day'], true)) {
            $this->mode = 'month';
        }

        $date = request()->query('date');
        $month = request()->query('month');

        $this->date = $this->normalizeDate(
            is_string($date) ? $date : null,
            is_string($month) ? $month : null,
        );
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('createCalendarEvent')
                ->label('Nouvel événement')
                ->icon('heroicon-o-plus')
                ->schema($this->eventFormSchema())
                ->modalWidth('2xl')
                ->modalSubmitActionLabel('Créer')
                ->action(fn (array $data) => $this->createCalendarEvent($data)),

            Action::make('editCalendarEvent')
                // L'action reste déclarée pour être montable depuis un clic sur
                // un événement, mais son bouton d'en-tête est masqué.
                ->extraAttributes(['style' => 'display: none'])
                ->schema($this->eventFormSchema())
                ->modalWidth('2xl')
                ->modalHeading('Modifier l’événement')
                ->modalSubmitActionLabel('Enregistrer')
                ->fillForm(fn (array $arguments): array => $this->eventFormData((int) $arguments['event']))
                ->action(fn (array $data, array $arguments) => $this->updateCalendarEvent((int) $arguments['event'], $data)),

            Action::make('subscribeCalendar')
                ->label('S’abonner')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('Abonner votre agenda')
                ->modalDescription('Ajoutez cette adresse dans Google Agenda, Outlook ou Apple Calendrier. Le flux est actualisé automatiquement.')
                ->modalContent(fn () => view('filament.pages.partials.calendar-subscription', [
                    'feedUrl' => $this->currentUser()->calendarFeedUrl(),
                    'webcalUrl' => $this->currentUser()->calendarWebcalUrl(),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),

            Action::make('downloadCalendar')
                ->label('Exporter (.ics)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $content = app(IcsCalendar::class)->forUser($this->currentUser());

                    return response()->streamDownload(
                        fn () => print $content,
                        'c2me-agenda-'.now()->format('Y-m-d').'.ics',
                        ['Content-Type' => 'text/calendar; charset=utf-8'],
                    );
                }),

            Action::make('regenerateCalendarToken')
                ->label('Régénérer le lien')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Régénérer le lien d’abonnement ?')
                ->modalDescription('Les agendas déjà abonnés cesseront de se mettre à jour. Vous devrez réabonner chaque agenda avec la nouvelle adresse.')
                ->action(function (): void {
                    $this->currentUser()->regenerateCalendarToken();

                    Notification::make()
                        ->success()
                        ->title('Nouveau lien d’abonnement généré')
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private function eventFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Titre')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Toggle::make('all_day')
                ->label('Journée entière')
                ->live(),

            DatePicker::make('start_date')
                ->label('Début')
                ->visible(fn (Get $get): bool => (bool) $get('all_day'))
                ->required(fn (Get $get): bool => (bool) $get('all_day')),

            DatePicker::make('end_date')
                ->label('Fin')
                ->visible(fn (Get $get): bool => (bool) $get('all_day'))
                ->afterOrEqual('start_date'),

            DateTimePicker::make('starts_at')
                ->label('Début')
                ->visible(fn (Get $get): bool => ! (bool) $get('all_day'))
                ->required(fn (Get $get): bool => ! (bool) $get('all_day'))
                ->seconds(false),

            DateTimePicker::make('ends_at')
                ->label('Fin')
                ->visible(fn (Get $get): bool => ! (bool) $get('all_day'))
                ->required(fn (Get $get): bool => ! (bool) $get('all_day'))
                ->after('starts_at')
                ->seconds(false),

            Select::make('reminder_minutes')
                ->label('Rappel')
                ->options([
                    5 => '5 minutes avant',
                    15 => '15 minutes avant',
                    30 => '30 minutes avant',
                    60 => '1 heure avant',
                    1440 => '1 jour avant',
                ])
                ->placeholder('Aucun rappel'),

            TextInput::make('location')
                ->label('Lieu')
                ->maxLength(255),

            Textarea::make('description')
                ->label('Notes')
                ->rows(3)
                ->maxLength(5000)
                ->columnSpanFull(),

            Select::make('recurrence')
                ->label('Récurrence')
                ->options([
                    'none' => 'Aucune',
                    'weekly' => 'Chaque semaine',
                    'monthly' => 'Chaque mois',
                ])
                ->default('none')
                ->live(),

            DatePicker::make('recurrence_until')
                ->label('Répéter jusqu’au')
                ->visible(fn (Get $get): bool => in_array($get('recurrence'), ['weekly', 'monthly'], true))
                ->afterOrEqual(fn (Get $get): string => (bool) $get('all_day') ? 'start_date' : 'starts_at'),

            Select::make('visibility')
                ->label('Visibilité')
                ->options([
                    'private' => 'Privé (moi uniquement)',
                    'all' => 'Tous les utilisateurs',
                    'selected' => 'Utilisateurs choisis',
                ])
                ->default('private')
                ->live(),

            Select::make('shared_user_ids')
                ->label('Partager avec')
                ->multiple()
                ->searchable()
                ->options(fn (): array => User::query()
                    ->whereKeyNot($this->currentUser()->id)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->visible(fn (Get $get): bool => $get('visibility') === 'selected')
                ->required(fn (Get $get): bool => $get('visibility') === 'selected')
                ->minItems(1)
                ->columnSpanFull(),

            Select::make('crm_account_id')
                ->label('Compte (optionnel)')
                ->searchable()
                ->options(fn (): array => CrmAccount::query()
                    ->ownedBy($this->currentUser())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->live(),

            Select::make('crm_contact_id')
                ->label('Contact (optionnel)')
                ->searchable()
                ->options(fn (Get $get): array => CrmContact::query()
                    ->where('crm_account_id', $get('crm_account_id'))
                    ->orderBy('last_name')
                    ->get()
                    ->mapWithKeys(fn (CrmContact $contact): array => [$contact->id => $contact->fullName()])
                    ->all())
                ->visible(fn (Get $get): bool => filled($get('crm_account_id'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCalendarEvent(array $data): void
    {
        $user = $this->currentUser();
        $validated = $this->validateEventData($data, $user);
        $event = CalendarEvent::query()->create($this->eventAttributes($validated, $user));

        $this->syncEventShares($event, $validated, $user);

        Notification::make()
            ->success()
            ->title('Événement créé')
            ->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCalendarEvent(int $eventId, array $data): void
    {
        $user = $this->currentUser();
        $event = CalendarEvent::query()
            ->where('user_id', $user->id)
            ->findOrFail($eventId);

        $validated = $this->validateEventData($data, $user);

        $event->update($this->eventAttributes($validated, $user));
        $this->syncEventShares($event, $validated, $user);

        Notification::make()
            ->success()
            ->title('Événement mis à jour')
            ->send();
    }

    public function deleteCalendarEvent(int $eventId): void
    {
        $user = $this->currentUser();
        $event = CalendarEvent::query()
            ->where('user_id', $user->id)
            ->findOrFail($eventId);

        $event->delete();

        Notification::make()
            ->success()
            ->title('Événement supprimé')
            ->send();
    }

    public function editCalendarEvent(int $eventId): void
    {
        $user = $this->currentUser();
        abort_unless(
            CalendarEvent::query()
                ->where('user_id', $user->id)
                ->whereKey($eventId)
                ->exists(),
            404,
        );

        $this->mountAction('editCalendarEvent', ['event' => $eventId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventFormData(int $eventId): array
    {
        $user = $this->currentUser();

        $event = CalendarEvent::query()
            ->where('user_id', $user->id)
            ->with('sharedUsers')
            ->findOrFail($eventId);

        return [
            'title' => $event->title,
            'all_day' => $event->all_day,
            'start_date' => $event->starts_at->format('Y-m-d'),
            'end_date' => $event->all_day ? $event->ends_at?->format('Y-m-d') : null,
            'starts_at' => $event->all_day ? null : $event->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $event->all_day ? null : $event->ends_at?->format('Y-m-d H:i:s'),
            'reminder_minutes' => $event->reminder_minutes,
            'location' => $event->location,
            'description' => $event->description,
            'recurrence' => $event->recurrence ?? 'none',
            'recurrence_until' => $event->recurrence_until?->format('Y-m-d'),
            'visibility' => $event->share_with_all
                ? 'all'
                : ($event->sharedUsers->isNotEmpty() ? 'selected' : 'private'),
            'shared_user_ids' => $event->sharedUsers->pluck('id')->all(),
            'crm_account_id' => $event->crm_account_id,
            'crm_contact_id' => $event->crm_contact_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateEventData(array $data, User $user): array
    {
        if (($data['recurrence'] ?? null) === 'none') {
            $data['recurrence'] = null;
        }

        if (($data['recurrence'] ?? null) === null) {
            $data['recurrence_until'] = null;
        }

        $allDay = (bool) ($data['all_day'] ?? false);
        $startField = $allDay ? 'start_date' : 'starts_at';

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'all_day' => ['boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reminder_minutes' => ['nullable', 'integer', 'in:5,15,30,60,1440'],
            'recurrence' => ['nullable', 'string', 'in:weekly,monthly'],
            'recurrence_until' => ['nullable', 'date', 'after_or_equal:'.$startField],
            'visibility' => ['required', 'string', 'in:private,all,selected'],
            'shared_user_ids' => ['array'],
            'shared_user_ids.*' => ['integer', 'exists:users,id'],
            'crm_account_id' => ['nullable', 'integer', 'exists:crm_accounts,id'],
            'crm_contact_id' => ['nullable', 'integer', 'exists:crm_contacts,id'],
        ];

        if ($allDay) {
            $rules['start_date'] = ['required', 'date'];
            $rules['end_date'] = ['nullable', 'date', 'after_or_equal:start_date'];
        } else {
            $rules['starts_at'] = ['required', 'date'];
            $rules['ends_at'] = ['required', 'date', 'after:starts_at'];
        }

        if (($data['visibility'] ?? 'private') === 'selected') {
            $rules['shared_user_ids'] = ['required', 'array', 'min:1'];
        }

        $validated = Validator::make($data, $rules)->validate();

        $accountId = $validated['crm_account_id'] ?? null;

        if ($accountId !== null) {
            abort_unless(
                CrmAccount::query()->ownedBy($user)->whereKey($accountId)->exists(),
                403,
            );
        }

        $contactId = $validated['crm_contact_id'] ?? null;

        if ($contactId !== null) {
            abort_unless(
                CrmContact::query()
                    ->whereKey($contactId)
                    ->where('crm_account_id', $accountId)
                    ->exists(),
                422,
            );
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function eventAttributes(array $validated, User $user): array
    {
        $allDay = (bool) ($validated['all_day'] ?? false);
        $recurrence = $validated['recurrence'] ?? null;

        $start = $allDay
            ? CarbonImmutable::parse((string) $validated['start_date'])->startOfDay()
            : CarbonImmutable::parse((string) $validated['starts_at']);

        $end = $allDay
            ? CarbonImmutable::parse((string) ($validated['end_date'] ?? $validated['start_date']))->endOfDay()
            : CarbonImmutable::parse((string) $validated['ends_at']);

        return [
            'user_id' => $user->id,
            'title' => (string) $validated['title'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'crm_account_id' => $validated['crm_account_id'] ?? null,
            'crm_contact_id' => $validated['crm_contact_id'] ?? null,
            'starts_at' => $start,
            'ends_at' => $end,
            'all_day' => $allDay,
            'reminder_minutes' => $validated['reminder_minutes'] ?? null,
            'recurrence' => $recurrence === '' || $recurrence === 'none' ? null : $recurrence,
            'recurrence_until' => $recurrence === '' || $recurrence === 'none'
                ? null
                : ($validated['recurrence_until'] ?? null),
            'share_with_all' => ($validated['visibility'] ?? 'private') === 'all',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncEventShares(CalendarEvent $event, array $validated, User $user): void
    {
        $selected = ($validated['visibility'] ?? 'private') === 'selected';

        $userIds = $selected
            ? array_values(array_diff(
                array_map('intval', (array) ($validated['shared_user_ids'] ?? [])),
                [$user->id],
            ))
            : [];

        $event->sharedUsers()->sync($userIds);
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $anchor = $this->anchor();

        return match ($this->mode) {
            'week' => 'Semaine du '
                .$anchor->startOfWeek(CarbonImmutable::MONDAY)->format('j')
                .' au '.$this->longDate($anchor->endOfWeek(CarbonImmutable::SUNDAY)),
            'day' => ucfirst($this->longDate($anchor)),
            default => $this->monthLabel($anchor),
        };
    }

    public function previousUrl(): string
    {
        return static::getUrl(['view' => $this->mode, 'date' => $this->shift(-1)]);
    }

    public function nextUrl(): string
    {
        return static::getUrl(['view' => $this->mode, 'date' => $this->shift(1)]);
    }

    public function todayUrl(): string
    {
        return static::getUrl(['view' => $this->mode, 'date' => now()->format('Y-m-d')]);
    }

    public function viewUrl(string $mode): string
    {
        return static::getUrl(['view' => $mode, 'date' => $this->date]);
    }

    public function shift(int $delta): string
    {
        $anchor = $this->anchor();

        $target = match ($this->mode) {
            'week' => $anchor->addWeeks($delta),
            'day' => $anchor->addDays($delta),
            default => $anchor->addMonths($delta),
        };

        return $target->format('Y-m-d');
    }

    /**
     * Grille du mois : semaines de 7 jours, du lundi au dimanche.
     *
     * @return array<int, array<int, array{date: string, day: int, inMonth: bool, isToday: bool, entries: array<int, array<string, mixed>>}>>
     */
    public function weeks(): array
    {
        $start = $this->anchor()->startOfMonth();
        $end = $start->endOfMonth();

        $gridStart = $start->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $end->endOfWeek(CarbonImmutable::SUNDAY);

        $entries = $this->entriesBetween($gridStart, $gridEnd);

        $weeks = [];
        $week = [];
        $cursor = $gridStart;

        while ($cursor <= $gridEnd) {
            $key = $cursor->format('Y-m-d');
            $week[] = [
                'date' => $key,
                'day' => (int) $cursor->format('j'),
                'inMonth' => $cursor->month === $start->month,
                'isToday' => $cursor->isToday(),
                'entries' => $entries[$key] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor = $cursor->addDay();
        }

        return $weeks;
    }

    /**
     * Sept jours de la semaine contenant la date d'ancrage.
     *
     * @return array<int, array{date: string, day: int, weekday: string, isToday: bool, entries: array<int, array<string, mixed>>}>
     */
    public function weekDays(): array
    {
        $start = $this->anchor()->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->endOfWeek(CarbonImmutable::SUNDAY);

        $entries = $this->entriesBetween($start, $end);

        $days = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'date' => $key,
                'day' => (int) $cursor->format('j'),
                'weekday' => $this->weekdayLabel((int) $cursor->format('N')),
                'isToday' => $cursor->isToday(),
                'entries' => $entries[$key] ?? [],
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * Activités de la journée d'ancrage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dayEntries(): array
    {
        $anchor = $this->anchor();
        $key = $anchor->format('Y-m-d');

        return $this->entriesBetween($anchor, $anchor)[$key] ?? [];
    }

    private function anchor(): CarbonImmutable
    {
        $year = (int) substr($this->date, 0, 4);
        $month = (int) substr($this->date, 5, 2);
        $day = (int) substr($this->date, 8, 2);

        return now()->toImmutable()->setDate($year, $month, $day)->startOfDay();
    }

    /**
     * Activités et événements personnels, groupés par jour.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function entriesBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $user = $this->currentUser();

        $activities = CrmActivity::query()
            ->forUser($user)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('account')
            ->orderBy('due_at')
            ->get();

        $events = CalendarEvent::query()
            ->visibleTo($user)
            ->with(['account', 'owner'])
            ->withCount('sharedUsers')
            ->where('starts_at', '<=', $to->endOfDay())
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereNull('recurrence')
                    ->where(fn (Builder $query) => $query
                        ->where('ends_at', '>=', $from->startOfDay())
                        ->orWhere(fn (Builder $query) => $query
                            ->whereNull('ends_at')
                            ->where('starts_at', '>=', $from->startOfDay()))))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNotNull('recurrence')
                    ->where(fn (Builder $query) => $query
                        ->whereNull('recurrence_until')
                        ->orWhere('recurrence_until', '>=', $from->toDateString()))))
            ->orderBy('starts_at')
            ->get();

        $entries = [];

        foreach ($activities as $activity) {
            $dueAt = $activity->due_at;

            if ($dueAt === null) {
                continue;
            }

            $entries[] = [
                'date' => $dueAt->format('Y-m-d'),
                'kind' => 'activity',
                'id' => $activity->id,
                'time' => $dueAt->format('H:i'),
                'time_sort' => $dueAt->format('H:i'),
                'label' => $activity->type->getLabel(),
                'color' => $activity->type->getColor(),
                'subject' => $activity->subject,
                'account' => $activity->account?->name,
                'overdue' => $activity->isOverdue(),
                'all_day' => false,
                'editable' => false,
                'shared' => false,
            ];
        }

        foreach ($events as $event) {
            foreach ($event->occurrencesBetween($from, $to) as $occurrence) {
                if ($event->all_day) {
                    $cursor = $occurrence['start']->lessThan($from) ? $from->startOfDay() : $occurrence['start']->startOfDay();
                    $last = $occurrence['end']->greaterThan($to) ? $to->startOfDay() : $occurrence['end']->startOfDay();

                    while ($cursor->lessThanOrEqualTo($last)) {
                        $entries[] = [
                            ...$this->eventEntry($event, $occurrence['start'], $user),
                            'date' => $cursor->format('Y-m-d'),
                        ];
                        $cursor = $cursor->addDay();
                    }

                    continue;
                }

                $key = $occurrence['start']->format('Y-m-d');

                if ($key >= $from->format('Y-m-d') && $key <= $to->format('Y-m-d')) {
                    $entries[] = [
                        ...$this->eventEntry($event, $occurrence['start'], $user),
                        'date' => $key,
                    ];
                }
            }
        }

        // Tri global unique (jour, journée entière d'abord, puis heure) avant
        // le regroupement, plutôt qu'un tri par jour après coup.
        usort($entries, static fn (array $first, array $second): int => [
            $first['date'],
            $first['all_day'] ? 0 : 1,
            $first['time_sort'],
        ] <=> [
            $second['date'],
            $second['all_day'] ? 0 : 1,
            $second['time_sort'],
        ]);

        $grouped = [];

        foreach ($entries as $entry) {
            $grouped[$entry['date']][] = $entry;
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventEntry(CalendarEvent $event, CarbonImmutable $start, User $user): array
    {
        $isOwner = $event->isOwnedBy($user);

        return [
            'kind' => 'event',
            'id' => $event->id,
            'time' => $event->all_day ? 'Journée' : $start->format('H:i'),
            'time_sort' => $event->all_day ? '00:00' : $start->format('H:i'),
            'label' => 'Événement',
            'color' => null,
            'subject' => $event->title,
            'account' => $event->account?->name,
            'overdue' => false,
            'all_day' => (bool) $event->all_day,
            'editable' => $isOwner,
            'shared' => ! $isOwner || $event->isShared(),
            'owner' => $event->owner?->name,
        ];
    }

    private function normalizeDate(?string $date, ?string $month): string
    {
        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return $month.'-01';
        }

        return now()->format('Y-m-d');
    }

    private function monthLabel(CarbonImmutable $date): string
    {
        return $this->monthName((int) $date->format('n')).' '.$date->format('Y');
    }

    private function longDate(CarbonImmutable $date): string
    {
        return $date->format('j').' '.$this->monthName((int) $date->format('n')).' '.$date->format('Y');
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ][$month];
    }

    private function weekdayLabel(int $isoDay): string
    {
        return [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
        ][$isoDay];
    }

    /**
     * Article d'aide contextuelle ouvert depuis le menu d'aide du panneau.
     *
     * @return array<int, string>
     */
    public static function getDocumentation(): array
    {
        return ['communication.calendrier'];
    }
}
