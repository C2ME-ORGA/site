<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom complet')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Select::make('role')
                            ->label('Rôle')
                            ->options(UserRole::options())
                            ->default(UserRole::Commercial->value)
                            ->required()
                            ->native(false)
                            ->disabled(fn (?User $record): bool => self::isSelf($record))
                            ->helperText('Le rôle détermine les accès ; il ne peut pas être modifié sur son propre compte.'),

                        TextInput::make('job_title')
                            ->label('Fonction')
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(32),

                        Toggle::make('is_active')
                            ->label('Compte actif')
                            ->default(true)
                            ->disabled(fn (?User $record): bool => self::isSelf($record)),
                    ]),

                Section::make('Sécurité')
                    ->columns(2)
                    ->schema([
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Laisser vide pour conserver le mot de passe actuel.'),
                    ]),
            ]);
    }

    /**
     * Indique si l'enregistrement édité est celui de l'utilisateur connecté.
     */
    private static function isSelf(?User $record): bool
    {
        return $record !== null && $record->id === auth()->id();
    }
}
