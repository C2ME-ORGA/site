<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Enums\AccountPotential;
use App\Enums\AccountStatus;
use App\Enums\CrmCategory;
use App\Enums\CrmSource;
use App\Models\CrmAccount;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TinusG\FilamentCompanyLogoColumn\CompanyLogoColumn;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['contacts', 'opportunities']))
            ->columns([
                CompanyLogoColumn::make('website')
                    ->label('')
                    ->size(32)
                    ->tooltip(fn (CrmAccount $record): string => $record->name),

                TextColumn::make('name')
                    ->label('Société')
                    ->description(fn (CrmAccount $record): ?string => $record->city)
                    ->searchable(['name', 'legal_name', 'siret', 'email'])
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge()
                    ->formatStateUsing(fn (CrmCategory $state): string => $state->getLabel()),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),

                TextColumn::make('potential')
                    ->label('Potentiel')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('owner.name')
                    ->label('Commercial')
                    ->placeholder('—'),

                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('opportunities_count')
                    ->label('Affaires')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('next_follow_up_at')
                    ->label('Relance')
                    ->date('d/m/Y')
                    ->color(fn (CrmAccount $record): string => $record->next_follow_up_at !== null && $record->next_follow_up_at->isPast()
                        ? 'danger'
                        : 'gray')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('last_contacted_at')
                    ->label('Dernier contact')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Catégorie')
                    ->options(CrmCategory::options()),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(AccountStatus::options()),

                SelectFilter::make('potential')
                    ->label('Potentiel')
                    ->options(AccountPotential::options()),

                SelectFilter::make('source')
                    ->label('Source')
                    ->options(CrmSource::options()),

                SelectFilter::make('owner_id')
                    ->label('Commercial')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('follow_up_due')
                    ->label('Relance due')
                    ->placeholder('Toutes')
                    ->trueLabel('Relances dues')
                    ->falseLabel('Sans relance due')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNotNull('next_follow_up_at')
                            ->where('next_follow_up_at', '<=', now()->endOfDay()),
                        false: fn (Builder $query) => $query->where(fn (Builder $query) => $query
                            ->whereNull('next_follow_up_at')
                            ->orWhere('next_follow_up_at', '>', now()->endOfDay())),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
