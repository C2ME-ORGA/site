<?php

namespace App\Filament\Resources\QuoteRequests\Schemas;

use App\Models\QuoteRequest;
use App\Models\QuoteRequestFile;
use App\Support\FilamentAccountUrl;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class QuoteRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Demande')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('kind')
                            ->label('Type')
                            ->badge(),

                        TextEntry::make('status')
                            ->label('Statut')
                            ->badge(),

                        TextEntry::make('created_at')
                            ->label('Reçue le')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('quoted_at')
                            ->label('Devis envoyé le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('owner.name')
                            ->label('Responsable')
                            ->placeholder('—'),
                    ]),

                Section::make('Demandeur')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nom')
                            ->weight('bold'),

                        TextEntry::make('company')
                            ->label('Société')
                            ->placeholder('—'),

                        TextEntry::make('email')
                            ->label('E-mail')
                            ->copyable()
                            ->copyMessage('Adresse copiée'),

                        TextEntry::make('phone')
                            ->label('Téléphone')
                            ->placeholder('—'),

                        TextEntry::make('city')
                            ->label('Commune')
                            ->placeholder('—'),

                        TextEntry::make('message')
                            ->label('Message du client')
                            ->columnSpanFull()
                            ->placeholder('Aucun message.'),
                    ]),

                Section::make('Détail de la demande')
                    ->columnSpanFull()
                    ->description('Informations saisies par le client durant le parcours.')
                    ->schema([
                        View::make('filament.quote-requests.payload')
                            ->viewData(fn (QuoteRequest $record): array => ['rows' => $record->summary()])
                            ->columnSpanFull(),
                    ]),

                Section::make('Plans joints')
                    ->schema([
                        RepeatableEntry::make('files')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('original_name')
                                    ->label('Fichier')
                                    ->weight('medium'),

                                TextEntry::make('extension')
                                    ->label('Format')
                                    ->badge()
                                    ->state(fn (QuoteRequestFile $record): string => strtoupper($record->extension() ?: '—')),

                                TextEntry::make('size')
                                    ->label('Taille')
                                    ->state(fn (QuoteRequestFile $record): string => $record->humanSize()),

                                TextEntry::make('availability')
                                    ->label('Disponibilité')
                                    ->badge()
                                    ->state(fn (QuoteRequestFile $record): string => $record->exists() ? 'Disponible' : 'Manquant')
                                    ->color(fn (QuoteRequestFile $record): string => $record->exists() ? 'success' : 'danger'),
                            ])
                            ->columns(4)
                            ->placeholder('Aucun plan joint.'),
                    ])
                    ->columnSpanFull(),

                Section::make('Suivi')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('account.name')
                            ->label('Compte lié')
                            ->placeholder('—')
                            ->url(fn (QuoteRequest $record): ?string => $record->account !== null
                                ? FilamentAccountUrl::view($record->account)
                                : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('contact_name')
                            ->label('Contact lié')
                            ->state(fn (QuoteRequest $record): ?string => $record->contact?->fullName())
                            ->placeholder('—'),

                        TextEntry::make('opportunity.reference')
                            ->label('Affaire liée')
                            ->placeholder('—'),

                        TextEntry::make('opportunity.stage')
                            ->label('Étape de l’affaire')
                            ->badge()
                            ->placeholder('—'),

                        TextEntry::make('note')
                            ->label('Note interne')
                            ->columnSpanFull()
                            ->placeholder('Aucune note.'),
                    ]),

                Section::make('Traçage')
                    ->columnSpanFull()
                    ->description('Informations collectées automatiquement à la soumission.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('Adresse IP')
                            ->placeholder('—'),

                        TextEntry::make('referer')
                            ->label('Page d’origine')
                            ->placeholder('—'),

                        TextEntry::make('user_agent')
                            ->label('Navigateur')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
