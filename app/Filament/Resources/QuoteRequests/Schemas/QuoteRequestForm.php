<?php

namespace App\Filament\Resources\QuoteRequests\Schemas;

use App\Enums\QuoteStatus;
use App\Models\QuoteRequest;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class QuoteRequestForm
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

                        TextInput::make('city')
                            ->label('Commune')
                            ->maxLength(255),
                    ]),

                Section::make('Suivi')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Statut')
                            ->required()
                            ->options(collect(QuoteStatus::cases())
                                ->mapWithKeys(fn (QuoteStatus $status) => [$status->value => $status->getLabel()])
                                ->all()),

                        Placeholder::make('quoted_at')
                            ->label('Devis envoyé le')
                            ->content(fn (?QuoteRequest $record): string => $record?->quoted_at?->format('d/m/Y à H:i')
                                ?? '—'),

                        Textarea::make('note')
                            ->label('Note interne')
                            ->helperText('Visible uniquement par l’équipe, jamais transmise au client.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Détail de la demande')
                    ->description('Informations saisies par le client durant le parcours.')
                    ->schema([
                        Placeholder::make('payload')
                            ->label(null)
                            ->content(fn (?QuoteRequest $record): string => self::payloadSummary($record)),

                        Repeater::make('files')
                            ->relationship()
                            ->label('Plans joints')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('original_name')
                                    ->label('Fichier')
                                    ->disabled(),

                                TextInput::make('size')
                                    ->label('Poids')
                                    ->disabled()
                                    ->formatStateUsing(function (Get $get, ?QuoteRequest $record): string {
                                        $file = $record?->files->firstWhere('id', $get('id'));

                                        return $file?->humanSize() ?? '—';
                                    }),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
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

    /**
     * Restitue la charge utile propre à chaque parcours sans imposer un
     * schéma rigide : le détail varie selon le type de demande.
     */
    private static function payloadSummary(?QuoteRequest $record): string
    {
        $summary = $record?->summary() ?? [];

        if ($summary === []) {
            return 'Aucune information complémentaire.';
        }

        return collect($summary)
            ->map(fn (string $value, string $key): string => Str::headline($key).' : '.$value)
            ->implode("\n");
    }
}
