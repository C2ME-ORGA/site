<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Models\ProjectCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('title')
                    ->label('Chantier')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record): ?string => $record->client),

                TextColumn::make('category.name')
                    ->label('Département')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('location')
                    ->label('Commune')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('year')
                    ->label('Année')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('images_count')
                    ->label('Photos')
                    ->counts('images')
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_published')
                    ->label('Publié')
                    ->boolean(),

                TextColumn::make('position')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('project_category_id')
                    ->label('Département')
                    ->relationship('category', 'name')
                    ->options(fn (): array => ProjectCategory::query()->ordered()->pluck('name', 'id')->all()),

                TernaryFilter::make('is_published')
                    ->label('Publication'),
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
