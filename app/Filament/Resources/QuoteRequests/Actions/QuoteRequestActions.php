<?php

namespace App\Filament\Resources\QuoteRequests\Actions;

use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Enums\QuoteStatus;
use App\Models\QuoteRequest;
use App\Services\ResolveInboundRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class QuoteRequestActions
{
    public static function reply(): Action
    {
        return Action::make('reply')
            ->label('Répondre')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->url(fn (QuoteRequest $record): string => 'mailto:'.$record->email);
    }

    public static function markAsQuoted(): Action
    {
        return Action::make('markAsQuoted')
            ->label('Marquer devis envoyé')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn (QuoteRequest $record): bool => $record->status !== QuoteStatus::QuoteSent)
            ->requiresConfirmation()
            ->action(fn (QuoteRequest $record) => $record->markAsQuoted());
    }

    public static function downloadPlans(): Action
    {
        return Action::make('downloadPlans')
            ->label('Télécharger les plans')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (QuoteRequest $record): bool => $record->files->isNotEmpty())
            ->action(fn (QuoteRequest $record) => $record->downloadArchive());
    }

    public static function resolve(): Action
    {
        return Action::make('resolve')
            ->label('Convertir en affaire')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->visible(fn (QuoteRequest $record): bool => $record->crm_opportunity_id === null)
            ->modalHeading('Convertir la demande')
            ->modalDescription('Un compte, un contact et une affaire seront créés ou réutilisés.')
            ->modalSubmitActionLabel('Convertir')
            ->schema(function (QuoteRequest $record): array {
                $service = app(ResolveInboundRequest::class);

                return [
                    Select::make('book')
                        ->label('Carnet')
                        ->options(CrmBook::options())
                        ->default($service->defaultBook($record)->value)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('category', null)),

                    Select::make('category')
                        ->label('Catégorie')
                        ->options(fn (Get $get): array => self::categoriesFor($get('book')))
                        ->default($service->defaultCategory($record)->value)
                        ->required(),
                ];
            })
            ->action(function (QuoteRequest $record, array $data): void {
                app(ResolveInboundRequest::class)->resolveQuoteRequest(
                    $record,
                    CrmBook::from((string) $data['book']),
                    CrmCategory::from((string) $data['category']),
                );

                Notification::make()
                    ->title('Demande convertie en affaire')
                    ->success()
                    ->send();
            });
    }

    /**
     * Catégories du carnet sélectionné, pour le champ dépendant.
     *
     * @return array<string, string>
     */
    private static function categoriesFor(mixed $book): array
    {
        if (! is_string($book) || $book === '') {
            return [];
        }

        $resolved = CrmBook::tryFrom($book);

        return $resolved instanceof CrmBook
            ? CrmCategory::optionsForBook($resolved)
            : [];
    }
}
