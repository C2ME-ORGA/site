<?php

namespace App\Filament\Resources\ProjectCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')
                    ->label('Département')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label('Adresse')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('projects_count')
                    ->label('Chantiers')
                    ->counts('projects')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Visible')
                    ->boolean(),

                TextColumn::make('position')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->recordActions([
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
