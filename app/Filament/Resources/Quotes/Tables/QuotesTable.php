<?php

namespace App\Filament\Resources\Quotes\Tables;

use App\Enums\QuoteDocumentStatus;
use App\Filament\Resources\Quotes\Actions\QuoteTransitions;
use App\Models\Quote;
use App\Services\QuotePdf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class QuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('account.name')
                    ->label('Compte')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Objet')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('amount_ht')
                    ->label('Total HT')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('amount_ttc')
                    ->label('Total TTC')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Validité')
                    ->date('d/m/Y')
                    ->color(fn (Quote $record): string => $record->isExpired() ? 'danger' : 'gray')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('owner.name')
                    ->label('Commercial')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('issued_at')
                    ->label('Émis le')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(QuoteDocumentStatus::options()),

                SelectFilter::make('owner_id')
                    ->label('Commercial')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('pdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->action(function (Quote $record) {
                            $service = app(QuotePdf::class);
                            $service->generate($record);

                            return Storage::disk((string) config('crm.quotes.storage_disk'))
                                ->download($record->pdf_path);
                        }),

                    ...QuoteTransitions::make(),

                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
