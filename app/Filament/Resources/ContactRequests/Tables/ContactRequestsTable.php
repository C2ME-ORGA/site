<?php

namespace App\Filament\Resources\ContactRequests\Tables;

use App\Models\ContactRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContactRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('read_at')
                    ->label('')
                    ->state(fn (ContactRequest $record): string => $record->isRead() ? '' : '●')
                    ->color('warning')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('Demandeur')
                    ->description(fn (ContactRequest $record): ?string => $record->company)
                    ->searchable()
                    ->weight('bold')
                    ->placeholder('—'),

                TextColumn::make('request_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Découpe laser' => 'info',
                        'Dessin & Ingénierie' => 'warning',
                        'Charpente métallique' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->copyable()
                    ->copyMessage('Adresse copiée')
                    ->tooltip(fn (ContactRequest $record): string => 'Copier '.$record->email),

                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Reçue le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('read_at')
                    ->label('Non traitées')
                    ->placeholder('Toutes')
                    ->trueLabel('Non traitées seulement')
                    ->falseLabel('Traitées seulement')
                    ->queries(
                        true: fn ($query) => $query->whereNull('read_at'),
                        false: fn ($query) => $query->whereNotNull('read_at'),
                    ),

                SelectFilter::make('request_type')
                    ->label('Type de demande')
                    ->options(collect(ContactRequest::requestTypes())
                        ->mapWithKeys(fn (string $type) => [$type => $type])
                        ->all()),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Répondre')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->url(fn (ContactRequest $record): string => 'mailto:'.$record->email),

                Action::make('markAsRead')
                    ->label('Marquer comme traitée')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactRequest $record): bool => ! $record->isRead())
                    ->action(fn (ContactRequest $record) => $record->markAsRead()),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Action::make('markManyAsRead')
                        ->label('Marquer comme traitées')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->markAsRead())
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
