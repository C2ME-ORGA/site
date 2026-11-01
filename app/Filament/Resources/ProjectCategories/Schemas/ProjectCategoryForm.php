<?php

namespace App\Filament\Resources\ProjectCategories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Département')
                    ->columnSpanFull()
                    ->description('Une famille d’ouvrages qui regroupe les chantiers sur la page « Nos réalisations ».')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->helperText('L’adresse publique est générée depuis ce nom (ex. « Bâtiments industriels » → batiments-industriels).'),

                        TextInput::make('position')
                            ->label('Ordre d’affichage')
                            ->numeric()
                            ->default(0)
                            ->helperText('Les départements sont triés par ordre croissant.'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Visible sur le site')
                            ->default(true),
                    ]),
            ]);
    }
}
