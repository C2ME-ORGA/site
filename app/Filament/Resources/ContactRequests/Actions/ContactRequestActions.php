<?php

namespace App\Filament\Resources\ContactRequests\Actions;

use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Models\ContactRequest;
use App\Services\ResolveInboundRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ContactRequestActions
{
    public static function reply(): Action
    {
        return Action::make('reply')
            ->label('Répondre')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->url(fn (ContactRequest $record): string => 'mailto:'.$record->email);
    }

    public static function markAsRead(): Action
    {
        return Action::make('markAsRead')
            ->label('Marquer comme traitée')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (ContactRequest $record): bool => ! $record->isRead())
            ->action(fn (ContactRequest $record) => $record->markAsRead());
    }

    public static function resolve(): Action
    {
        return Action::make('resolve')
            ->label('Créer dans le CRM')
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->visible(fn (ContactRequest $record): bool => $record->crm_contact_id === null)
            ->modalHeading('Créer la fiche CRM')
            ->modalDescription('Un compte et un contact seront créés ou réutilisés.')
            ->modalSubmitActionLabel('Créer')
            ->schema(function (ContactRequest $record): array {
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
            ->action(function (ContactRequest $record, array $data): void {
                app(ResolveInboundRequest::class)->resolveContactRequest(
                    $record,
                    CrmBook::from((string) $data['book']),
                    CrmCategory::from((string) $data['category']),
                );

                Notification::make()
                    ->title('Fiche CRM créée')
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
