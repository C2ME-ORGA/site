<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Enums\CrmActivityType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activité')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(CrmActivityType::options())
                            ->required()
                            ->native(false),

                        TextInput::make('subject')
                            ->label('Objet')
                            ->required()
                            ->maxLength(255),

                        Select::make('crm_account_id')
                            ->label('Compte')
                            ->relationship('account', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('crm_contact_id')
                            ->label('Contact')
                            ->relationship('contact', 'last_name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->fullName())
                            ->searchable()
                            ->preload(),

                        Select::make('crm_opportunity_id')
                            ->label('Affaire')
                            ->relationship('opportunity', 'reference')
                            ->searchable()
                            ->preload(),

                        Select::make('quote_id')
                            ->label('Devis')
                            ->relationship('quote', 'reference')
                            ->searchable()
                            ->preload(),

                        Select::make('user_id')
                            ->label('Assigné à')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->id()),

                        DateTimePicker::make('occurred_at')
                            ->label('Réalisée le')
                            ->native(false),

                        DateTimePicker::make('due_at')
                            ->label('Échéance')
                            ->native(false),

                        DateTimePicker::make('remind_at')
                            ->label('Rappel')
                            ->native(false),

                        DateTimePicker::make('completed_at')
                            ->label('Terminée le')
                            ->native(false),
                    ]),

                Section::make('Contenu')
                    ->schema([
                        Textarea::make('body')
                            ->label('Description')
                            ->rows(5)
                            ->columnSpanFull(),

                        Textarea::make('outcome')
                            ->label('Résultat')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
