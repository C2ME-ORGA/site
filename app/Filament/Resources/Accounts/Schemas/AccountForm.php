<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountPotential;
use App\Enums\AccountStatus;
use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Enums\CrmSource;
use App\Models\CrmAccount;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema, CrmBook $book): Schema
    {
        return $schema
            ->components([
                Tabs::make('Fiche client')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Identité')
                            ->columns(2)
                            ->schema([
                                Select::make('category')
                                    ->label('Catégorie')
                                    ->options(CrmCategory::optionsForBook($book))
                                    ->required()
                                    ->native(false)
                                    ->live(),

                                TextInput::make('name')
                                    ->label('Nom commercial')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('code')
                                    ->label('Code client')
                                    ->maxLength(32),

                                TextInput::make('legal_name')
                                    ->label('Raison sociale')
                                    ->maxLength(255),

                                TextInput::make('siret')
                                    ->label('SIRET')
                                    ->maxLength(20),

                                TextInput::make('vat_number')
                                    ->label('TVA intracommunautaire')
                                    ->maxLength(32),

                                TextInput::make('website')
                                    ->label('Site web')
                                    ->url()
                                    ->maxLength(255),
                            ]),

                        Tab::make('Coordonnées')
                            ->columns(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->maxLength(32),

                                TextInput::make('mobile')
                                    ->label('Mobile')
                                    ->tel()
                                    ->maxLength(32),

                                TextInput::make('address_line1')
                                    ->label('Adresse')
                                    ->maxLength(255),

                                TextInput::make('address_line2')
                                    ->label('Complément d’adresse')
                                    ->maxLength(255),

                                TextInput::make('postal_code')
                                    ->label('Code postal')
                                    ->maxLength(16),

                                TextInput::make('city')
                                    ->label('Ville')
                                    ->maxLength(255),

                                TextInput::make('country')
                                    ->label('Pays')
                                    ->default('FR')
                                    ->maxLength(2),
                            ]),

                        Tab::make('Qualification')
                            ->columns(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Statut')
                                    ->options(AccountStatus::options())
                                    ->required()
                                    ->native(false),

                                Select::make('potential')
                                    ->label('Potentiel')
                                    ->options(AccountPotential::options())
                                    ->native(false),

                                Select::make('source')
                                    ->label('Source')
                                    ->options(CrmSource::options())
                                    ->native(false),

                                Select::make('owner_id')
                                    ->label('Commercial référent')
                                    ->relationship('owner', 'name')
                                    ->searchable()
                                    ->preload(),

                                Select::make('tags')
                                    ->label('Tags secondaires')
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Suivi')
                            ->columns(2)
                            ->schema([
                                TextInput::make('next_follow_up_at')
                                    ->label('Prochaine relance')
                                    ->type('datetime-local'),

                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Informations spécifiques')
                            ->columns(2)
                            ->schema(fn (Get $get): array => self::metadataFields($get('category'))),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function metadataFields(?string $category): array
    {
        $categoryEnum = $category !== null
            ? CrmCategory::tryFrom($category)
            : null;

        if ($categoryEnum === null) {
            return [];
        }

        $definitions = config('crm.fields.'.$categoryEnum->book()->value, []);

        return array_map(function (array $field): mixed {
            $name = 'metadata.'.$field['key'];

            return match ($field['type']) {
                'textarea' => Textarea::make($name)
                    ->label($field['label'])
                    ->rows(3)
                    ->columnSpanFull(),

                'number' => TextInput::make($name)
                    ->label($field['label'])
                    ->numeric(),

                'select' => Select::make($name)
                    ->label($field['label'])
                    ->options($field['options'] ?? [])
                    ->native(false),

                default => TextInput::make($name)
                    ->label($field['label'])
                    ->maxLength(255),
            };
        }, $definitions);
    }

    /**
     * Valeurs par défaut à la création.
     *
     * @return array<string, mixed>
     */
    public static function defaults(CrmBook $book): array
    {
        return [
            'book' => $book,
            'status' => AccountStatus::Prospect,
            'country' => 'FR',
            'metadata' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function prepare(CrmAccount $account): array
    {
        return $account->only([
            'category',
            'code',
            'name',
            'legal_name',
            'siret',
            'vat_number',
            'website',
            'email',
            'phone',
            'mobile',
            'address_line1',
            'address_line2',
            'postal_code',
            'city',
            'country',
            'status',
            'potential',
            'source',
            'owner_id',
            'next_follow_up_at',
            'notes',
            'metadata',
        ]);
    }
}
