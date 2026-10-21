<?php

namespace App\Filament\Pages;

use App\Enums\AccountStatus;
use App\Enums\CrmActivityType;
use App\Enums\CrmBook;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\User;
use App\Support\FilamentAccountUrl;
use App\Support\FollowUpAccounts;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnitEnum;

class FollowUps extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.follow-ups';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Relances';

    protected static ?string $title = 'Relances à traiter';

    protected static ?string $slug = 'relances';

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $count = FollowUpAccounts::due($user)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $user = $user instanceof User ? $user : null;
        $includeInactive = $user?->isAdmin() ?? false;

        return $table
            ->query(FollowUpAccounts::query($user, $includeInactive))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['owner', 'contacts']))
            ->defaultSort('next_follow_up_at')
            ->columns([
                TextColumn::make('name')
                    ->label('Compte')
                    ->description(fn (CrmAccount $record): ?string => $record->city)
                    ->searchable(['name', 'legal_name', 'code', 'siret', 'city'])
                    ->weight('bold')
                    ->url(fn (CrmAccount $record): string => FilamentAccountUrl::view($record))
                    ->sortable(),

                TextColumn::make('book')
                    ->label('Carnet')
                    ->badge()
                    ->formatStateUsing(fn (CrmBook $state): string => $state->shortLabel()),

                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('owner.name')
                    ->label('Propriétaire')
                    ->placeholder('—')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),

                TextColumn::make('next_follow_up_at')
                    ->label('Échéance')
                    ->dateTime('d/m/Y H:i')
                    ->color(fn (CrmAccount $record): string => $record->next_follow_up_at?->isPast() ? 'danger' : 'warning')
                    ->sortable(),

                TextColumn::make('last_contacted_at')
                    ->label('Dernier contact')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->placeholder('—')
                    ->icon('heroicon-o-phone')
                    ->url(fn (CrmAccount $record): ?string => filled($record->phone) ? 'tel:'.$record->phone : null),

                TextColumn::make('primary_contact')
                    ->label('Contact principal')
                    ->state(function (CrmAccount $record): string {
                        $contact = $record->primaryContact();

                        return $contact?->fullName() ?? '—';
                    })
                    ->description(function (CrmAccount $record): ?string {
                        $contact = $record->primaryContact();

                        return $contact?->email ?: $contact?->phone;
                    }),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label('Période')
                    ->options([
                        'due' => 'Toutes les relances dues',
                        'overdue' => 'En retard',
                        'today' => 'Aujourd’hui',
                        'week' => 'Cette semaine',
                    ])
                    ->default('due')
                    ->query(function (Builder $query, array $data): Builder {
                        $period = $data['value'] ?? 'due';

                        return match ($period) {
                            'overdue' => $query->where('next_follow_up_at', '<', now()->startOfDay()),
                            'today' => $query->whereBetween('next_follow_up_at', [now()->startOfDay(), now()->endOfDay()]),
                            'week' => $query->whereBetween('next_follow_up_at', [now()->startOfWeek(), now()->endOfWeek()]),
                            default => $query->where('next_follow_up_at', '<=', now()->endOfDay()),
                        };
                    }),

                SelectFilter::make('book')
                    ->label('Carnet')
                    ->options(CrmBook::options()),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(AccountStatus::options()),

                SelectFilter::make('account_state')
                    ->label('Comptes')
                    ->options([
                        'active' => 'Actifs uniquement',
                        'all' => 'Tous les comptes',
                    ])
                    ->default('active')
                    ->query(fn (Builder $query, array $data): Builder => ($data['value'] ?? 'active') === 'all'
                        ? $query
                        : $query->where('status', '!=', AccountStatus::Inactive->value))
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),

                SelectFilter::make('owner_id')
                    ->label('Propriétaire')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->recordActions([
                Action::make('markFollowedUp')
                    ->label('Marquer comme relancé')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Marquer la relance comme faite')
                    ->modalDescription('L’échéance sera effacée et une activité de relance sera ajoutée au compte.')
                    ->modalSubmitActionLabel('Enregistrer')
                    ->schema([
                        Textarea::make('report')
                            ->label('Compte rendu')
                            ->placeholder('Optionnel')
                            ->rows(3),
                    ])
                    ->visible(fn (CrmAccount $record): bool => $this->canUpdate($record))
                    ->action(function (CrmAccount $record, array $data): void {
                        if (! $this->recordFollowUp($record, $data['report'] ?? null)) {
                            $this->notifyUnauthorized();

                            return;
                        }

                        Notification::make()
                            ->title('Relance enregistrée')
                            ->success()
                            ->send();
                    }),

                Action::make('postpone')
                    ->label('Reporter')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->modalHeading('Reporter la relance')
                    ->modalSubmitActionLabel('Reporter')
                    ->schema([
                        Select::make('preset')
                            ->label('Nouvelle échéance')
                            ->options([
                                '3' => 'Dans 3 jours',
                                '7' => 'Dans 7 jours',
                                '14' => 'Dans 14 jours',
                                'custom' => 'Date personnalisée',
                            ])
                            ->default('7')
                            ->live()
                            ->required(),

                        DateTimePicker::make('next_follow_up_at')
                            ->label('Date personnalisée')
                            ->visible(fn (Get $get): bool => $get('preset') === 'custom')
                            ->required(fn (Get $get): bool => $get('preset') === 'custom')
                            ->minDate(now()->startOfDay()),
                    ])
                    ->visible(fn (CrmAccount $record): bool => $this->canUpdate($record))
                    ->action(function (CrmAccount $record, array $data): void {
                        $next = $this->postponeDate($data);

                        if ($next === null || ! $this->postpone($record, $next)) {
                            $this->notifyUnauthorized();

                            return;
                        }

                        Notification::make()
                            ->title('Relance reportée')
                            ->body('Nouvelle échéance : '.$next->format('d/m/Y H:i'))
                            ->success()
                            ->send();
                    }),

                Action::make('open')
                    ->label('Ouvrir la fiche')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (CrmAccount $record): string => FilamentAccountUrl::view($record)),

                Action::make('edit')
                    ->label('Modifier')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn (CrmAccount $record): string => FilamentAccountUrl::edit($record))
                    ->visible(fn (CrmAccount $record): bool => $this->canUpdate($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markManyFollowedUp')
                        ->label('Marquer comme relancés')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record instanceof CrmAccount && $this->recordFollowUp($record, null)) {
                                    $count++;
                                }
                            }

                            $this->notifyBulkResult($count, 'relance(s) enregistrée(s)');
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('postponeMany')
                        ->label('Reporter')
                        ->icon('heroicon-o-calendar-days')
                        ->color('warning')
                        ->schema([
                            Select::make('preset')
                                ->label('Nouvelle échéance')
                                ->options([
                                    '3' => 'Dans 3 jours',
                                    '7' => 'Dans 7 jours',
                                    '14' => 'Dans 14 jours',
                                    'custom' => 'Date personnalisée',
                                ])
                                ->default('7')
                                ->live()
                                ->required(),

                            DateTimePicker::make('next_follow_up_at')
                                ->label('Date personnalisée')
                                ->visible(fn (Get $get): bool => $get('preset') === 'custom')
                                ->required(fn (Get $get): bool => $get('preset') === 'custom')
                                ->minDate(now()->startOfDay()),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $next = $this->postponeDate($data);

                            if ($next === null) {
                                $this->notifyUnauthorized();

                                return;
                            }

                            $count = 0;

                            foreach ($records as $record) {
                                if ($record instanceof CrmAccount && $this->postpone($record, $next)) {
                                    $count++;
                                }
                            }

                            $this->notifyBulkResult($count, 'relance(s) reportée(s)');
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Aucune relance à traiter')
            ->emptyStateDescription('Les comptes dont l’échéance est due apparaîtront ici.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    private function recordFollowUp(CrmAccount $account, ?string $report): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('update', $account)) {
            return false;
        }

        DB::transaction(function () use ($account, $report, $user): void {
            CrmActivity::query()->create([
                'crm_account_id' => $account->id,
                'user_id' => $user->id,
                'type' => CrmActivityType::FollowUp->value,
                'subject' => 'Relance — '.$account->name,
                'body' => $report,
                'occurred_at' => now(),
                'completed_at' => now(),
            ]);

            $account->forceFill([
                'last_contacted_at' => now(),
                'next_follow_up_at' => null,
            ])->save();
        });

        return true;
    }

    private function postpone(CrmAccount $account, CarbonImmutable $next): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->can('update', $account)) {
            return false;
        }

        $account->update(['next_follow_up_at' => $next]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postponeDate(array $data): ?CarbonImmutable
    {
        $preset = $data['preset'] ?? null;

        if ($preset === 'custom') {
            $value = $data['next_follow_up_at'] ?? null;

            if (! is_string($value) || $value === '') {
                return null;
            }

            try {
                return CarbonImmutable::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        if (is_int($preset)) {
            $preset = (string) $preset;
        }

        if (! is_string($preset) || ! in_array($preset, ['3', '7', '14'], true)) {
            return null;
        }

        return now()->addDays((int) $preset)->setTime(9, 0);
    }

    private function canUpdate(CrmAccount $account): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', $account);
    }

    private function notifyUnauthorized(): void
    {
        Notification::make()
            ->title('Action non autorisée')
            ->danger()
            ->send();
    }

    private function notifyBulkResult(int $count, string $label): void
    {
        if ($count === 0) {
            $this->notifyUnauthorized();

            return;
        }

        Notification::make()
            ->title($count.' '.$label)
            ->success()
            ->send();
    }
}
