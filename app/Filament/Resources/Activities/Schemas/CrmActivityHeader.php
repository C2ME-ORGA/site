<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Enums\CrmActivityType;
use App\Models\CrmActivity;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;

class CrmActivityHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (CrmActivity $record): string => (string) $record->subject)
                ->description(fn (CrmActivity $record): string => $record->account->name)
                ->icon(fn (CrmActivity $record): string => $record->type->icon())
                ->iconBgColor('primary')
                ->badges([
                    TextEntry::make('type')
                        ->badge()
                        ->formatStateUsing(fn (?CrmActivityType $state): ?string => $state?->getLabel())
                        ->color(fn (?CrmActivityType $state): string => $state?->getColor() ?? 'gray'),

                    TextEntry::make('activity_state')
                        ->label('État')
                        ->state(fn (CrmActivity $record): string => $record->completed_at === null ? 'À faire' : 'Terminée')
                        ->badge()
                        ->color(fn (string $state): string => $state === 'Terminée' ? 'success' : 'warning'),
                ])
                ->metadata([
                    MetadataEntry::make('account.name')
                        ->label('Compte')
                        ->fieldIcon(Heroicon::OutlinedBuildingOffice2)
                        ->placeholder('—'),

                    MetadataEntry::make('contact.last_name')
                        ->label('Contact')
                        ->fieldIcon(Heroicon::OutlinedUser)
                        ->placeholder('—'),

                    MetadataEntry::make('user.name')
                        ->label('Auteur')
                        ->fieldIcon(Heroicon::OutlinedIdentification)
                        ->placeholder('—'),

                    MetadataEntry::make('occurred_at')
                        ->label('Date')
                        ->fieldIcon(Heroicon::OutlinedCalendarDays)
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('Planifiée'),

                    MetadataEntry::make('due_at')
                        ->label('Échéance')
                        ->fieldIcon(Heroicon::OutlinedClock)
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                ])
                ->whenCompact(fn (CompactHeader $compact): CompactHeader => $compact
                    ->show(HeaderPart::Image, HeaderPart::Badges)
                    ->only(HeaderPart::Metadata, ['account.name', 'occurred_at'])),
        ]);
    }
}
