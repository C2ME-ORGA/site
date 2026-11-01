<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Models\ContactRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;

class ContactRequestHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (ContactRequest $record): string => $record->name)
                ->description(fn (ContactRequest $record): ?string => $record->company)
                ->initials(fn (ContactRequest $record): string => $record->name)
                ->initialsBgColor('primary')
                ->badges([
                    TextEntry::make('request_type')
                        ->badge()
                        ->color('info'),

                    TextEntry::make('read_state')
                        ->label('État')
                        ->state(fn (ContactRequest $record): string => $record->read_at === null ? 'Nouvelle' : 'Traitée')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Nouvelle' ? 'warning' : 'success'),
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
