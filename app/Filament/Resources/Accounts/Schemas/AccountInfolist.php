<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountPotential;
use App\Enums\AccountStatus;
use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Enums\CrmSource;
use App\Enums\CrmTagColor;
use App\Models\CrmAccount;
use App\Models\CrmTag;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class AccountInfolist
{
    public static function configure(Schema $schema, CrmBook $book): Schema
    {
        return $schema
            ->components([
                Tabs::make('Fiche client')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Identité')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Société')
                                    ->weight('bold'),

                                TextEntry::make('legal_name')
                                    ->label('Raison sociale')
                                    ->placeholder('—'),

                                TextEntry::make('code')
                                    ->label('Code')
                                    ->placeholder('—'),

                                TextEntry::make('category')
                                    ->label('Catégorie')
                                    ->badge()
                                    ->formatStateUsing(fn (?CrmCategory $state): ?string => $state?->getLabel()),

                                TextEntry::make('status')
                                    ->label('Statut')
                                    ->badge()
                                    ->formatStateUsing(fn (?AccountStatus $state): ?string => $state?->getLabel()),

                                TextEntry::make('potential')
                                    ->label('Potentiel')
                                    ->badge()
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?AccountPotential $state): ?string => $state?->getLabel()),

                                TextEntry::make('source')
                                    ->label('Source')
                                    ->placeholder('—')
                                    ->formatStateUsing(fn (?CrmSource $state): ?string => $state?->getLabel()),

                                TextEntry::make('owner.name')
                                    ->label('Commercial')
                                    ->placeholder('—'),

                                TextEntry::make('tags.name')
                                    ->label('Étiquettes')
                                    ->badge()
                                    ->placeholder('—')
                                    ->color(fn (mixed $state, ?Model $relatedRecord): string => $relatedRecord instanceof CrmTag
                                        ? $relatedRecord->badgeColor()
                                        : CrmTagColor::Gray->getColor()),
                            ]),

                        Tab::make('Coordonnées')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('siret')
                                    ->label('SIRET')
                                    ->placeholder('—'),

                                TextEntry::make('vat_number')
                                    ->label('N° TVA')
                                    ->placeholder('—'),

                                TextEntry::make('website')
                                    ->label('Site web')
                                    ->placeholder('—')
                                    ->url(fn (?string $state): ?string => $state)
                                    ->openUrlInNewTab(),

                                TextEntry::make('email')
                                    ->label('E-mail')
                                    ->placeholder('—')
                                    ->copyable(),

                                TextEntry::make('phone')
                                    ->label('Téléphone')
                                    ->placeholder('—'),

                                TextEntry::make('mobile')
                                    ->label('Mobile')
                                    ->placeholder('—'),

                                TextEntry::make('address_line1')
                                    ->label('Adresse')
                                    ->placeholder('—'),

                                TextEntry::make('address_line2')
                                    ->label('Complément')
                                    ->placeholder('—'),

                                TextEntry::make('city')
                                    ->label('Commune')
                                    ->state(fn (CrmAccount $record): ?string => trim(($record->postal_code ?? '').' '.($record->city ?? '')) ?: null)
                                    ->placeholder('—'),
                            ]),

                        Tab::make('Suivi')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('last_contacted_at')
                                    ->label('Dernier contact')
                                    ->date('d/m/Y')
                                    ->placeholder('—'),

                                TextEntry::make('next_follow_up_at')
                                    ->label('Prochaine relance')
                                    ->date('d/m/Y')
                                    ->placeholder('—'),

                                TextEntry::make('created_at')
                                    ->label('Créé le')
                                    ->date('d/m/Y')
                                    ->placeholder('—'),
                            ]),

                        Tab::make('Informations spécifiques')
                            ->columns(2)
                            ->visible(fn (): bool => self::metadataFields($book) !== [])
                            ->schema(fn (): array => self::metadataEntries($book)),

                        Tab::make('Notes')
                            ->schema([
                                TextEntry::make('notes')
                                    ->hiddenLabel()
                                    ->placeholder('Aucune note.')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Définitions des champs propres au carnet.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function metadataFields(CrmBook $book): array
    {
        /** @var array<int, array<string, mixed>> $fields */
        $fields = config('crm.fields.'.$book->value, []);

        return $fields;
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function metadataEntries(CrmBook $book): array
    {
        return array_map(function (array $field): TextEntry {
            $entry = TextEntry::make('metadata.'.$field['key'])
                ->label((string) $field['label']);

            if (($field['type'] ?? null) === 'select') {
                /** @var array<string, string> $options */
                $options = $field['options'] ?? [];

                $entry->formatStateUsing(fn (mixed $state): ?string => is_scalar($state)
                    ? ($options[(string) $state] ?? (string) $state)
                    : null);
            } elseif (($field['type'] ?? null) === 'textarea') {
                $entry->columnSpanFull();
            }

            return $entry->placeholder('—');
        }, self::metadataFields($book));
    }
}
