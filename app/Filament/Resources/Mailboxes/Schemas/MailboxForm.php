<?php

namespace App\Filament\Resources\Mailboxes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MailboxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Libellé')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Adresse e-mail')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        Select::make('user_id')
                            ->label('Utilisateur')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Boîte partagée'),

                        Toggle::make('is_shared')
                            ->label('Boîte partagée'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),

                Section::make('Réception (IMAP)')
                    ->columns(2)
                    ->schema([
                        TextInput::make('imap_host')
                            ->label('Serveur')
                            ->required()
                            ->default(config('crm.email.imap.host'))
                            ->maxLength(255),

                        TextInput::make('imap_port')
                            ->label('Port')
                            ->numeric()
                            ->required()
                            ->default(config('crm.email.imap.port')),

                        Select::make('imap_encryption')
                            ->label('Chiffrement')
                            ->options(['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'Aucun'])
                            ->default(config('crm.email.imap.encryption'))
                            ->native(false),

                        TextInput::make('imap_username')
                            ->label('Identifiant')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('imap_password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Laisser vide pour conserver le mot de passe actuel.'),
                    ]),

                Section::make('Envoi (SMTP)')
                    ->columns(2)
                    ->schema([
                        TextInput::make('smtp_host')
                            ->label('Serveur')
                            ->required()
                            ->default(config('crm.email.smtp.host'))
                            ->maxLength(255),

                        TextInput::make('smtp_port')
                            ->label('Port')
                            ->numeric()
                            ->required()
                            ->default(config('crm.email.smtp.port')),

                        Select::make('smtp_encryption')
                            ->label('Chiffrement')
                            ->options(['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'Aucun'])
                            ->default(config('crm.email.smtp.encryption'))
                            ->native(false),

                        TextInput::make('smtp_username')
                            ->label('Identifiant')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('smtp_password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Laisser vide pour conserver le mot de passe actuel.'),
                    ]),

                Section::make('Signature')
                    ->schema([
                        Textarea::make('signature')
                            ->label('Signature par défaut')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
