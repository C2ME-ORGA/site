<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Models\ContactRequest;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Demandeur')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('company')
                            ->label('Société')
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(32),
                    ]),

                Section::make('Demande')
                    ->schema([
                        TextInput::make('request_type')
                            ->label('Type de demande')
                            ->required()
                            ->maxLength(255)
                            ->datalist(collect(ContactRequest::requestTypes())->all()),

                        Textarea::make('message')
                            ->label('Message')
                            ->required()
                            ->rows(10)
                            ->columnSpanFull()
                            ->maxLength(4000),
                    ]),

                Section::make('Traçage')
                    ->description('Informations collectées automatiquement à la soumission.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ip_address')
                            ->label('Adresse IP')
                            ->maxLength(45),

                        TextInput::make('referer')
                            ->label('Page d’origine')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
