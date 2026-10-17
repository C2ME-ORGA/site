<?php

namespace App\Filament\Resources\QuoteRequests\Tables;

use App\Enums\QuoteKind;
use App\Enums\QuoteStatus;
use App\Models\QuoteRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuoteRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('files'))
            ->columns([
                TextColumn::make('kind')
                    ->badge(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('name')
                    ->label('Demandeur')
                    ->description(fn (QuoteRequest $record): ?string => $record->company)
                    ->searchable()
                    ->weight('bold')
                    ->placeholder('—'),

                TextColumn::make('city')
                    ->label('Commune')
                    ->placeholder('—'),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->copyable()
                    ->copyMessage('Adresse copiée'),

                TextColumn::make('files_count')
                    ->label('Plans')
                    ->state(fn (QuoteRequest $record): string => $record->files_count > 0
                        ? $record->files_count.' fichier(s)'
                        : 'Aucun')
                    ->placeholder('Aucun'),

                TextColumn::make('created_at')
                    ->label('Reçue le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(QuoteStatus::cases())
                        ->mapWithKeys(fn (QuoteStatus $status) => [$status->value => $status->getLabel()])
                        ->all()),

                SelectFilter::make('kind')
                    ->label('Type de demande')
                    ->options(collect(QuoteKind::cases())
                        ->mapWithKeys(fn (QuoteKind $kind) => [$kind->value => $kind->getLabel()])
                        ->all()),

                TernaryFilter::make('has_files')
                    ->label('Avec plans joint')
                    ->placeholder('Toutes')
                    ->trueLabel('Avec plans uniquement')
                    ->falseLabel('Sans plan uniquement')
                    ->queries(
                        true: fn ($query) => $query->whereHas('files'),
                        false: fn ($query) => $query->whereDoesntHave('files'),
                    ),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label('Répondre')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->url(fn (QuoteRequest $record): string => 'mailto:'.$record->email),

                Action::make('markAsQuoted')
                    ->label('Marquer devis envoyé')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (QuoteRequest $record): bool => $record->status !== QuoteStatus::QuoteSent)
                    ->requiresConfirmation()
                    ->action(fn (QuoteRequest $record) => $record->markAsQuoted()),

                Action::make('downloadPlans')
                    ->label('Télécharger les plans')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (QuoteRequest $record): bool => $record->files->isNotEmpty())
                    ->action(fn (QuoteRequest $record) => $record->downloadArchive()),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
