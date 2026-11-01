<?php

namespace App\Filament\Resources\Activities\RelationManagers;

use App\Models\CrmActivityAttachment;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Photos';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('preview')
                    ->label('Aperçu')
                    ->getStateUsing(fn (CrmActivityAttachment $record): string => $record->url())
                    ->url(fn (CrmActivityAttachment $record): string => $record->url())
                    ->openUrlInNewTab()
                    ->height(72)
                    ->width(96),

                TextColumn::make('original_name')
                    ->label('Fichier')
                    ->searchable(),

                TextColumn::make('size')
                    ->label('Taille')
                    ->formatStateUsing(fn (CrmActivityAttachment $record): string => $record->humanSize()),

                TextColumn::make('created_at')
                    ->label('Ajoutée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Supprimer'),
            ]);
    }
}
