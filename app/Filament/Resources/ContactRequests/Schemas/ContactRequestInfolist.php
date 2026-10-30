<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Models\ContactRequest;
use App\Support\FilamentAccountUrl;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Demande')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('request_type')
                            ->label('Type de demande')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Découpe laser' => 'info',
                                'Dessin & Ingénierie' => 'warning',
                                'Charpente métallique' => 'success',
                                default => 'gray',
                            }),

                        TextEntry::make('read_state')
                            ->label('Traitement')
                            ->badge()
                            ->state(fn (ContactRequest $record): string => $record->isRead() ? 'Traitée' : 'Non traitée')
                            ->color(fn (ContactRequest $record): string => $record->isRead() ? 'success' : 'warning'),

                        TextEntry::make('created_at')
                            ->label('Reçue le')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('read_at')
                            ->label('Traitée le')
                            ->dateTime('d/m/Y H:i')
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
                    ]),

                Section::make('Message')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('message')
                            ->hiddenLabel()
                            ->placeholder('Aucun message.'),
                    ]),

                Section::make('Suivi')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('account.name')
                            ->label('Compte lié')
                            ->placeholder('—')
                            ->url(fn (ContactRequest $record): ?string => $record->account !== null
                                ? FilamentAccountUrl::view($record->account)
                                : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('contact_name')
                            ->label('Contact lié')
                            ->state(fn (ContactRequest $record): ?string => $record->contact?->fullName())
                            ->placeholder('—'),
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
