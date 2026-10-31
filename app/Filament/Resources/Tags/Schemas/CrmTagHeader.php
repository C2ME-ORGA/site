<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Enums\CrmTagColor;
use App\Models\CrmTag;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;

class CrmTagHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (CrmTag $record): string => $record->name)
                ->description(fn (CrmTag $record): string => $record->slug)
                ->icon(Heroicon::OutlinedTag)
                ->iconBgColor('primary')
                ->badges([
                    TextEntry::make('color')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): ?string => CrmTagColor::tryFrom((string) $state)?->getLabel())
                        ->color(fn (?string $state): string => CrmTagColor::tryFrom((string) $state)?->getColor() ?? 'gray'),
                ])
                ->metadata([
                    MetadataEntry::make('created_at')
                        ->label('Créée le')
                        ->fieldIcon(Heroicon::OutlinedCalendarDays)
                        ->dateTime('d/m/Y')
                        ->placeholder('—'),
                ])
                ->whenCompact(fn (CompactHeader $compact): CompactHeader => $compact
                    ->show(HeaderPart::Image, HeaderPart::Badges)),
        ]);
    }
}
