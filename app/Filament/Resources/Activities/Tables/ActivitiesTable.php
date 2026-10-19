<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Enums\CrmActivityType;
use App\Models\CrmActivity;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['account', 'contact', 'user']))
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->icon(fn (CrmActivityType $state): string => $state->icon()),

                TextColumn::make('subject')
                    ->label('Objet')
                    ->searchable()
                    ->weight('bold')
                    ->limit(50),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('contact.last_name')
                    ->label('Contact')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('due_at')
                    ->label('Échéance')
                    ->dateTime('d/m/Y H:i')
                    ->color(fn (CrmActivity $record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label('Réalisée le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Assigné à')
                    ->placeholder('—'),

                TextColumn::make('completed_at')
                    ->label('Statut')
                    ->badge()
                    ->state(fn (CrmActivity $record): string => $record->isDone() ? 'Terminée' : 'En cours')
                    ->color(fn (CrmActivity $record): string => $record->isDone() ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(CrmActivityType::options()),

                SelectFilter::make('user_id')
                    ->label('Assigné à')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('pending')
                    ->label('À faire')
                    ->placeholder('Toutes')
                    ->trueLabel('En attente')
                    ->falseLabel('Terminées')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('due_at')->whereNull('completed_at'),
                        false: fn (Builder $query) => $query->whereNotNull('completed_at'),
                    ),

                TernaryFilter::make('overdue')
                    ->label('En retard')
                    ->placeholder('Toutes')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNotNull('due_at')
                            ->whereNull('completed_at')
                            ->where('due_at', '<', now()->startOfDay()),
                        false: fn (Builder $query) => $query->where(fn (Builder $query) => $query
                            ->whereNull('due_at')
                            ->orWhere('due_at', '>=', now()->startOfDay())),
                    ),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Terminer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CrmActivity $record): bool => ! $record->isDone())
                    ->action(fn (CrmActivity $record) => $record->forceFill(['completed_at' => now()])->save()),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
