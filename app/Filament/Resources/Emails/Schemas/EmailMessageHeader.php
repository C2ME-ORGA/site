<?php

namespace App\Filament\Resources\Emails\Schemas;

use App\Models\EmailMessage;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;

class EmailMessageHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (EmailMessage $record): string => (string) $record->subject)
                ->description(fn (EmailMessage $record): string => trim(
                    ($record->from_name !== null && $record->from_name !== '' ? $record->from_name.' · ' : '')
                        .$record->from_email,
                    ' ·',
                ))
                ->icon(Heroicon::OutlinedEnvelope)
                ->iconBgColor('primary')
                ->badges([
                    TextEntry::make('is_sent')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => (bool) $state ? 'Sortant' : 'Entrant')
                        ->color(fn (mixed $state): string => (bool) $state ? 'info' : 'success'),

                    TextEntry::make('is_read')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => (bool) $state ? 'Lu' : 'Non lu')
                        ->color(fn (mixed $state): string => (bool) $state ? 'gray' : 'warning'),
                ])
                ->metadata([
                    MetadataEntry::make('mailbox.name')
                        ->label('Boîte')
                        ->fieldIcon(Heroicon::OutlinedInbox)
                        ->placeholder('—'),

                    MetadataEntry::make('from_email')
                        ->label('Expéditeur')
                        ->fieldIcon(Heroicon::OutlinedUser)
                        ->copyable()
                        ->placeholder('—'),

                    MetadataEntry::make('received_at')
                        ->label('Reçu le')
                        ->fieldIcon(Heroicon::OutlinedCalendarDays)
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),

                    MetadataEntry::make('sent_at')
                        ->label('Envoyé le')
                        ->fieldIcon(Heroicon::OutlinedPaperAirplane)
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                ])
                ->whenCompact(fn (CompactHeader $compact): CompactHeader => $compact
                    ->show(HeaderPart::Image, HeaderPart::Badges)
                    ->only(HeaderPart::Metadata, ['mailbox.name', 'received_at'])),
        ]);
    }
}
