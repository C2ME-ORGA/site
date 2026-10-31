<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountStatus;
use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Models\CrmAccount;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;
use TinusG\FilamentCompanyLogoColumn\CompanyLogoColumn;

/**
 * En-tête commun aux trois carnets de comptes : identité, statut, potentiel,
 * coordonnées et suivi commercial.
 */
class CrmAccountHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (CrmAccount $record): string => $record->name)
                ->description(fn (CrmAccount $record): ?string => $record->legal_name)
                ->avatar(fn (CrmAccount $record): ?string => $record->website === null || $record->website === ''
                    ? null
                    : CompanyLogoColumn::make('website')->domain($record->website)->size(96)->getLogoUrl())
                ->initials(fn (CrmAccount $record): string => $record->name)
                ->initialsBgColor('primary')
                ->badges([
                    TextEntry::make('book')
                        ->badge()
                        ->formatStateUsing(fn (?CrmBook $state): ?string => $state?->getLabel())
                        ->color(fn (?CrmBook $state): string => $state?->getColor() ?? 'gray'),

                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (?AccountStatus $state): ?string => $state?->getLabel())
                        ->color(fn (?AccountStatus $state): string => $state?->getColor() ?? 'gray'),

                    TextEntry::make('category')
                        ->badge()
                        ->formatStateUsing(fn (?CrmCategory $state): ?string => $state?->getLabel())
                        ->color('gray'),

                    TextEntry::make('potential')
                        ->badge()
                        ->state(fn (CrmAccount $record): string => $record->potential?->getLabel() ?? 'Potentiel non défini')
                        ->color(fn (CrmAccount $record): string => $record->potential?->getColor() ?? 'gray'),
                ])
                ->metadata([
                    MetadataEntry::make('owner.name')
                        ->label('Commercial')
                        ->fieldIcon(Heroicon::OutlinedUser)
                        ->placeholder('—'),

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

                    MetadataEntry::make('last_contacted_at')
                        ->label('Dernier contact')
                        ->fieldIcon(Heroicon::OutlinedClock)
                        ->date('d/m/Y')
                        ->placeholder('—'),

                    MetadataEntry::make('next_follow_up_at')
                        ->label('Prochaine relance')
                        ->fieldIcon(Heroicon::OutlinedCalendarDays)
                        ->date('d/m/Y')
                        ->placeholder('—'),
                ])
                ->whenCompact(fn (CompactHeader $compact): CompactHeader => $compact
                    ->show(HeaderPart::Image, HeaderPart::Badges)
                    ->only(HeaderPart::Metadata, ['owner.name', 'next_follow_up_at'])),
        ]);
    }
}
