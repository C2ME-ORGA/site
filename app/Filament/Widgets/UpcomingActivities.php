<?php

namespace App\Filament\Widgets;

use App\Enums\CrmActivityType;
use App\Models\CrmActivity;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingActivities extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Actions à venir et en retard';

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->query(
                CrmActivity::query()
                    ->pending()
                    ->when($user !== null, fn ($query) => $query->forUser($user))
                    ->orderBy('due_at')
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->icon(fn (CrmActivityType $state): string => $state->icon()),

                TextColumn::make('subject')
                    ->label('Objet')
                    ->weight('bold')
                    ->limit(60),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->placeholder('—'),

                TextColumn::make('due_at')
                    ->label('Échéance')
                    ->dateTime('d/m/Y')
                    ->color(fn (CrmActivity $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Terminer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (CrmActivity $record) => $record->forceFill(['completed_at' => now()])->save()),

                EditAction::make(),
            ]);
    }
}
