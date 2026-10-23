<?php

namespace App\Filament\Resources\Tags\Tables;

use App\Models\CrmTag;
use App\Services\CrmTagMerger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        // Calculé une seule fois par rendu de tableau : la colonne en a besoin
        // à chaque ligne, et la requête ne doit pas être rejouée.
        $duplicates = CrmTag::duplicateKeyMap();

        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('accounts'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->weight('bold')
                    ->badge()
                    ->color(fn (CrmTag $record): string => $record->badgeColor()),

                TextColumn::make('slug')
                    ->label('Référence')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('accounts_count')
                    ->label('Comptes')
                    ->counts('accounts')
                    ->sortable()
                    ->placeholder('0'),

                IconColumn::make('duplicates')
                    ->label('Doublon')
                    ->state(fn (CrmTag $record): bool => isset($duplicates[$record->getKey()]))
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success')
                    ->tooltip(fn (CrmTag $record): ?string => isset($duplicates[$record->getKey()])
                        ? 'Ressemble à une autre étiquette : fusionnez-les.'
                        : null),
            ])
            ->filters([
                TernaryFilter::make('duplicate')
                    ->label('Doublons')
                    ->placeholder('Toutes')
                    ->trueLabel('En doublon probable')
                    ->falseLabel('Sans doublon')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereIn('id', array_keys(CrmTag::duplicateKeyMap())),
                        false: fn (Builder $query): Builder => $query
                            ->whereNotIn('id', array_keys(CrmTag::duplicateKeyMap())),
                    ),

                TernaryFilter::make('unused')
                    ->label('Utilisation')
                    ->placeholder('Toutes')
                    ->trueLabel('Jamais utilisées')
                    ->falseLabel('Au moins une fois')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereDoesntHave('accounts'),
                        false: fn (Builder $query): Builder => $query
                            ->whereHas('accounts'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->authorize('delete', CrmTag::class)
                    ->modalHeading('Supprimer l’étiquette')
                    ->modalDescription(fn (CrmTag $record): string => sprintf(
                        'L’étiquette « %s » sera retirée de %d compte(s).',
                        $record->name,
                        $record->accountsCount(),
                    ))
                    ->successNotificationTitle('Étiquette supprimée'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::mergeAction(),

                    DeleteBulkAction::make()
                        ->authorize('deleteAny', CrmTag::class)
                        ->modalHeading('Supprimer les étiquettes')
                        ->modalDescription('Les étiquetages correspondants seront retirés de leurs comptes.')
                        ->successNotificationTitle('Étiquettes supprimées'),
                ]),
            ]);
    }

    private static function mergeAction(): BulkAction
    {
        return BulkAction::make('merge')
            ->label('Fusionner')
            ->icon('heroicon-o-rectangle-stack')
            ->color('primary')
            ->authorize('merge', CrmTag::class)
            ->form(fn (Collection $records): array => [
                Select::make('target')
                    ->label('Étiquette conservée')
                    ->options(
                        $records->mapWithKeys(
                            fn (CrmTag $tag): array => [$tag->getKey() => $tag->name],
                        )->all(),
                    )
                    ->default(fn (Collection $records): mixed => $records->first()?->getKey())
                    ->required()
                    ->native(false)
                    ->helperText('Les étiquetages des autres étiquettes seront déplacés vers celle-ci, puis celles-ci seront supprimées.'),
            ])
            ->action(function (Action $action, Collection $records, array $data, CrmTagMerger $merger): void {
                $allowed = $records->pluck('id')->all();

                if (! in_array((int) $data['target'], $allowed, true)) {
                    $action->halt();

                    return;
                }

                /** @var CrmTag $target */
                $target = CrmTag::query()->findOrFail($data['target']);

                $merger->merge($target, array_values($records->all()));

                Notification::make()
                    ->title('Étiquettes fusionnées')
                    ->body(sprintf('Tous les étiquetages ont été reportés sur « %s ».', $target->name))
                    ->success()
                    ->send();
            })
            ->successNotificationTitle('Étiquettes fusionnées');
    }
}
