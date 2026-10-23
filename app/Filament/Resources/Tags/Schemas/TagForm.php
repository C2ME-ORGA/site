<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Enums\CrmTagColor;
use App\Models\CrmTag;
use App\Rules\UniqueTagName;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Étiquette')
                    ->description('Un vocabulaire partagé par toute l’équipe : la même casse ou les mêmes accents ne créent pas deux entrées distinctes.')
                    ->columns(2)
                    ->schema(array_values(self::fields())),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(255)
                ->autofocus()
                ->rule(fn (mixed $record): UniqueTagName => UniqueTagName::forRecord(
                    $record instanceof CrmTag ? $record : null,
                )),

            Select::make('color')
                ->label('Couleur')
                ->options(CrmTagColor::options())
                ->default(CrmTagColor::Gray->value)
                ->native(false),
        ];
    }
}
