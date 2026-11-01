<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Enums\QuoteDocumentStatus;
use App\Models\Quote;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Devis')
                    ->columns(2)
                    ->schema([
                        Select::make('crm_account_id')
                            ->label('Compte')
                            ->relationship('account', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

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

                        Select::make('status')
                            ->label('Statut')
                            ->options(QuoteDocumentStatus::options())
                            ->default(QuoteDocumentStatus::Draft->value)
                            ->disabled()
                            ->dehydrated()
                            ->native(false)
                            ->helperText('Le statut se modifie via les actions de transition du devis.'),

                        TextInput::make('subject')
                            ->label('Objet / référence')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        DatePicker::make('issued_at')
                            ->label('Date d’émission')
                            ->native(false)
                            ->default(now()),

                        DatePicker::make('valid_until')
                            ->label('Date de validité')
                            ->native(false)
                            ->default(fn (): CarbonImmutable => now()->addDays((int) config('crm.quotes.validity_days', 7))),

                        Select::make('owner_id')
                            ->label('Commercial')
                            ->relationship('owner', 'name')
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Totaux')
                    ->columns(3)
                    ->schema([
                        TextInput::make('amount_ht')
                            ->label('Total HT')
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('vat_rate')
                            ->label('Taux de TVA (%)')
                            ->numeric()
                            ->default(fn (): int => (int) config('crm.quotes.default_vat_rate', 0))
                            ->live(onBlur: true)
                            ->helperText('Le total TTC est recalculé automatiquement.'),

                        TextInput::make('amount_ttc')
                            ->label('Total TTC')
                            ->prefix('€')
                            ->disabled()
                            ->dehydrated(),

                        Textarea::make('notes')
                            ->label('Notes / conditions')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Document')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pdf_path')
                            ->label('PDF')
                            ->disabled()
                            ->placeholder('Généré à la demande'),
                    ]),

                Section::make('Lignes')
                    ->schema([
                        Repeater::make('lines')
                            ->relationship()
                            ->label('Lignes du devis')
                            ->hiddenLabel()
                            ->live()
                            ->schema([
                                Grid::make()
                                    ->columnSpanFull()
                                    ->columns(6)
                                    ->schema([
                                        TextInput::make('position')
                                            ->label('N°')
                                            ->numeric()
                                            ->default(0)
                                            ->columnSpan(1),

                                        TextInput::make('code')
                                            ->label('Code')
                                            ->maxLength(32)
                                            ->columnSpan(1),

                                        TextInput::make('designation')
                                            ->label('Désignation')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(4),
                                    ]),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Grid::make(5)
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('quantity')
                                            ->label('Quantité')
                                            ->numeric()
                                            ->default(1)
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        Select::make('unit')
                                            ->label('Unité')
                                            ->options(config('crm.quotes.units'))
                                            ->default('u')
                                            ->native(false)
                                            ->columnSpan(1),

                                        TextInput::make('unit_price')
                                            ->label('P.U. HT')
                                            ->numeric()
                                            ->prefix('€')
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        TextInput::make('discount_percent')
                                            ->label('% Remise')
                                            ->numeric()
                                            ->default(0)
                                            ->live(onBlur: true)
                                            ->columnSpan(1),

                                        TextInput::make('total_ht')
                                            ->label('Total HT')
                                            ->prefix('€')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->columns(9)
                            ->defaultItems(1)
                            ->addActionLabel('Ajouter une ligne')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function prepare(Quote $quote): array
    {
        return $quote->only([
            'crm_account_id',
            'crm_contact_id',
            'crm_opportunity_id',
            'status',
            'subject',
            'issued_at',
            'valid_until',
            'owner_id',
            'amount_ht',
            'vat_rate',
            'amount_ttc',
            'notes',
        ]);
    }
}
