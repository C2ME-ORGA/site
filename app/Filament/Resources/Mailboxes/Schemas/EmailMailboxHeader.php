<?php

namespace App\Filament\Resources\Mailboxes\Schemas;

use App\Models\EmailMailbox;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;

class EmailMailboxHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (EmailMailbox $record): string => $record->name)
                ->description(fn (EmailMailbox $record): string => $record->email)
                ->icon(Heroicon::OutlinedInbox)
                ->iconBgColor('primary')
                ->badges([
                    TextEntry::make('is_shared')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => (bool) $state ? 'Partagée' : 'Personnelle')
                        ->color(fn (mixed $state): string => (bool) $state ? 'info' : 'gray'),
                ])
                ->metadata([
                    MetadataEntry::make('user.name')
                        ->label('Propriétaire')
                        ->fieldIcon(Heroicon::OutlinedUser)
                        ->placeholder('—'),

                    MetadataEntry::make('imap_host')
                        ->label('Serveur IMAP')
                        ->fieldIcon(Heroicon::OutlinedServer)
                        ->placeholder('—'),

                    MetadataEntry::make('last_synced_at')
                        ->label('Dernière synchronisation')
                        ->fieldIcon(Heroicon::OutlinedArrowPath)
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                ])
                ->whenCompact(fn (CompactHeader $compact): CompactHeader => $compact
                    ->show(HeaderPart::Image, HeaderPart::Badges)
                    ->only(HeaderPart::Metadata, ['user.name', 'last_synced_at'])),
        ]);
    }
}
