<?php

namespace App\Filament\Pages;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Observers\AuditObserver;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AuditJournal extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.audit-journal';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Système';

    protected static ?int $navigationSort = 96;

    protected static ?string $navigationLabel = 'Journal d’audit';

    protected static ?string $title = 'Journal d’audit';

    protected static ?string $slug = 'journal-audit';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLog::query())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Événement')
                    ->badge()
                    ->icon(fn (AuditEvent $state): string => $state->icon()),

                TextColumn::make('auditable_label')
                    ->label('Élément')
                    ->description(fn (AuditLog $record): string => AuditObserver::subjectOptions()[$record->auditable_type] ?? class_basename($record->auditable_type))
                    ->searchable()
                    ->weight('bold')
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label('Auteur')
                    ->placeholder('Système')
                    ->searchable(),

                TextColumn::make('summary')
                    ->label('Détail')
                    ->state(fn (AuditLog $record): string => $this->summarize($record))
                    ->limit(70)
                    ->tooltip(fn (AuditLog $record): ?string => $this->summarize($record) ?: null)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Événement')
                    ->options(AuditEvent::options()),

                SelectFilter::make('auditable_type')
                    ->label('Type d’élément')
                    ->options(AuditObserver::subjectOptions()),

                SelectFilter::make('user_id')
                    ->label('Auteur')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Tous les auteurs'),

                Filter::make('created_at')
                    ->label('Période')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Du'),
                        DatePicker::make('until')
                            ->label('Au'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        return $query
                            ->when($from, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($until, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Détail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (AuditLog $record): string => 'Entrée du journal — '.$record->created_at?->format('d/m/Y H:i:s'))
                    ->modalContent(fn (AuditLog $record): View => view('filament.audit.details', ['audit' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
            ]);
    }

    private function summarize(AuditLog $record): string
    {
        return collect($record->changeList())
            ->map(fn (string $change, string $field): string => $field.' : '.$change)
            ->implode(' · ');
    }
}
