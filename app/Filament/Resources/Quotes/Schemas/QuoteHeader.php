<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Enums\QuoteDocumentStatus;
use App\Models\Quote;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;

class QuoteHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (Quote $record): string => (string) $record->reference)
                ->description(fn (Quote $record): string => $record->subject)
                ->icon(Heroicon::OutlinedDocumentText)
                ->iconBgColor('primary')
                ->badges([
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (?QuoteDocumentStatus $state): ?string => $state?->getLabel())
                        ->color(fn (?QuoteDocumentStatus $state): string => $state?->getColor() ?? 'gray'),

                    TextEntry::make('version')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn (mixed $state): string => 'v'.(int) $state),
                ])
                ->metadata([
                    MetadataEntry::make('account.name')
                        ->label('Compte')
                        ->fieldIcon(Heroicon::OutlinedBuildingOffice2)
                        ->placeholder('—'),

                    MetadataEntry::make('owner.name')
                        ->label('Commercial')
                        ->fieldIcon(Heroicon::OutlinedUser)
                        ->placeholder('—'),

                    MetadataEntry::make('amount_ttc')
                        ->label('Montant TTC')
                        ->fieldIcon(Heroicon::OutlinedBanknotes)
                        ->money('EUR', locale: 'fr')
                        ->placeholder('—'),

                    MetadataEntry::make('issued_at')
                        ->label('Émis le')
                        ->fieldIcon(Heroicon::OutlinedCalendarDays)
                        ->date('d/m/Y')
                        ->placeholder('—'),

                    MetadataEntry::make('valid_until')
                        ->label('Valable jusqu’au')
                        ->fieldIcon(Heroicon::OutlinedCalendarDays)
                        ->date('d/m/Y')
                        ->placeholder('—'),
                ])
                ->whenCompact(fn (CompactHeader $compact): CompactHeader => $compact
                    ->show(HeaderPart::Image, HeaderPart::Badges)
                    ->only(HeaderPart::Metadata, ['account.name', 'amount_ttc'])),
        ]);
    }
}
