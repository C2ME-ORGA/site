<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Section::make('Chantier')
                            ->columnSpan(2)
                            ->description('Identité du chantier affichée sur la page publique.')
                            ->columns(2)
                            ->schema([
                                Select::make('project_category_id')
                                    ->label('Département')
                                    ->relationship('category', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false),

                                TextInput::make('title')
                                    ->label('Titre du chantier')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('L’adresse publique est générée depuis le titre.'),

                                TextInput::make('client')
                                    ->label('Maître d’ouvrage')
                                    ->maxLength(255),

                                TextInput::make('location')
                                    ->label('Commune')
                                    ->maxLength(255)
                                    ->placeholder('Montaigu-Vendée (85)'),

                                TextInput::make('year')
                                    ->label('Année de livraison')
                                    ->numeric()
                                    ->minValue(1900)
                                    ->maxValue(2100),

                                TagsInput::make('services')
                                    ->label('Prestations')
                                    ->placeholder('Charpente métal')
                                    ->helperText('Appuyez sur Entrée pour ajouter une prestation. Elles s’affichent en étiquettes sur le site.')
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(6)
                                    ->maxLength(4000)
                                    ->columnSpanFull(),
                            ]),

                        Grid::make(1)
                            ->schema([
                                Section::make('Photos')
                                    ->description('La première photo sert de vignette sur la liste. Glissez les photos pour les réordonner.')
                                    ->schema([
                                        Repeater::make('images')
                                            ->relationship()
                                            ->hiddenLabel()
                                            ->defaultItems(0)
                                            ->orderColumn('position')
                                            ->reorderable()
                                            ->addActionLabel('Ajouter une photo')
                                            ->columns(1)
                                            ->schema([
                                                FileUpload::make('path')
                                                    ->label('Fichier')
                                                    ->image()
                                                    ->disk('public')
                                                    ->directory('projects')
                                                    ->maxSize(5120)
                                                    ->imageResizeTargetWidth('1600')
                                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                                    ->required(),

                                                TextInput::make('alt')
                                                    ->label('Texte alternatif')
                                                    ->maxLength(255)
                                                    ->helperText('Décrit la photo pour l’accessibilité. Laisser vide pour utiliser le titre du chantier.'),

                                                Hidden::make('position')->default(0),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => filled($state['alt'] ?? null)
                                                ? (string) $state['alt']
                                                : null)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Publication')
                                    ->columnSpan(1)
                                    ->columns(1)
                                    ->schema([
                                        Toggle::make('is_published')
                                            ->label('Publié sur le site')
                                            ->default(true),

                                        TextInput::make('position')
                                            ->label('Ordre d’affichage')
                                            ->numeric()
                                            ->default(0),
                                    ]),
                            ])
                    ]),
            ]);
    }
}
