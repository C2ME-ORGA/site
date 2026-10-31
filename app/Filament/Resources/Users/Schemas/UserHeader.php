<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use MortalKiller\FilamentPageHeader\CompactHeader;
use MortalKiller\FilamentPageHeader\Components\Header;
use MortalKiller\FilamentPageHeader\Components\MetadataEntry;
use MortalKiller\FilamentPageHeader\Enums\HeaderPart;

class UserHeader
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Header::make()
                ->heading(fn (User $record): string => $record->name)
                ->description(fn (User $record): string => $record->email)
                ->initials(fn (User $record): string => $record->name)
                ->initialsBgColor('primary')
                ->badges([
                    TextEntry::make('role')
                        ->badge()
                        ->formatStateUsing(fn (?UserRole $state): ?string => $state?->getLabel())
                        ->color(fn (?UserRole $state): string => $state?->getColor() ?? 'gray'),

                    TextEntry::make('is_active')
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => (bool) $state ? 'Actif' : 'Désactivé')
                        ->color(fn (mixed $state): string => (bool) $state ? 'success' : 'danger'),
                ])
                ->metadata([
                    MetadataEntry::make('job_title')
                        ->label('Fonction')
                        ->fieldIcon(Heroicon::OutlinedBriefcase)
                        ->placeholder('—'),

                    MetadataEntry::make('phone')
                        ->label('Téléphone')
                        ->fieldIcon(Heroicon::OutlinedPhone)
                        ->placeholder('—'),

                    MetadataEntry::make('last_login_at')
                        ->label('Dernière connexion')
                        ->fieldIcon(Heroicon::OutlinedClock)
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                ])
                ->whenCompact(fn (CompactHeader $compact): CompactHeader => $compact
                    ->show(HeaderPart::Image, HeaderPart::Badges)
                    ->only(HeaderPart::Metadata, ['job_title', 'last_login_at'])),
        ]);
    }
}
