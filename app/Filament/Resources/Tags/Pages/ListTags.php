<?php

namespace App\Filament\Resources\Tags\Pages;

use App\Filament\Resources\Tags\TagResource;
use App\Models\CrmTag;
use App\Services\CrmTagMerger;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('cleanupDuplicates')
                ->label('Nettoyer les doublons')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->authorize('merge', CrmTag::class)
                ->modalHeading('Nettoyage des doublons')
                ->modalDescription('Les étiquettes qui semblent désigner la même chose sont fusionnées en conservant la plus ancienne. Les étiquetages sont reportés avant chaque suppression.')
                ->modalSubmitActionLabel('Fusionner')
                ->requiresConfirmation()
                ->action(function (CrmTagMerger $merger): void {
                    $groups = CrmTag::duplicateGroups();

                    foreach ($groups as $group) {
                        $merger->merge(CrmTag::cleanupSurvivor($group), $group);
                    }

                    Notification::make()
                        ->title($groups === []
                            ? 'Aucun doublon détecté'
                            : sprintf('%d groupe(s) d’étiquettes fusionné(s).', count($groups)))
                        ->success()
                        ->send();
                }),
        ];
    }
}
