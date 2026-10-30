<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use App\Services\IcsCalendar;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportIcs')
                ->label('Exporter (ICS)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $user = auth()->user();
                    $content = app(IcsCalendar::class)->forUser($user);

                    return response()->streamDownload(
                        fn () => print $content,
                        'c2me-agenda-'.now()->format('Y-m-d').'.ics',
                        ['Content-Type' => 'text/calendar; charset=utf-8'],
                    );
                }),

            CreateAction::make(),
        ];
    }
}
