<?php

namespace App\Filament\Resources\QuoteRequests\Schemas;

use App\Enums\QuoteKind;
use App\Enums\QuoteStatus;
use App\Models\QuoteRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;

class QuoteRequestHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (QuoteRequest $record): string => $record->name)
                ->description(fn (QuoteRequest $record): ?string => $record->company)
                ->initials(fn (QuoteRequest $record): string => $record->name)
                ->initialsBgColor('primary')
                ->badges([
                    TextEntry::make('kind')
                        ->badge()
                        ->formatStateUsing(fn (?QuoteKind $state): ?string => $state?->getLabel())
                        ->color(fn (?QuoteKind $state): string => $state?->getColor() ?? 'gray'),

                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (?QuoteStatus $state): ?string => $state?->getLabel())
                        ->color(fn (?QuoteStatus $state): string => $state?->getColor() ?? 'gray'),
                ])
                ->metadata([
                    MetadataEntry::make('email')
                        ->label('E-mail')
                        ->fieldIcon(Heroicon::OutlinedEnvelope)
                        ->copyable()
                        ->placeholder('—'),

                    MetadataEntry::make('phone')
                        ->label('Téléphone')
                        ->fieldIcon(Heroicon::OutlinedPhone)
                        ->placeholder('—'),

                    MetadataEntry::make('city')
                        ->label('Commune')
                        ->fieldIcon(Heroicon::OutlinedMapPin)
                        ->placeholder('—'),

                    MetadataEntry::make('created_at')
                        ->label('Reçue le')
                        ->fieldIcon(Heroicon::OutlinedCalendarDays)
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                ])
                ->whenCompact(fn (CompactHeader $compact): CompactHeader => $compact
                    ->show(HeaderPart::Image, HeaderPart::Badges)
                    ->only(HeaderPart::Metadata, ['email', 'created_at'])),
        ]);
    }
}
