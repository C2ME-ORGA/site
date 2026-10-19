<?php

namespace App\Filament\Resources\Quotes\Actions;

use App\Enums\QuoteDocumentStatus;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class QuoteTransitions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            self::transition('mark_sent', QuoteDocumentStatus::Sent, 'Marquer envoyé', 'heroicon-o-paper-airplane', 'info'),
            self::transition('accept', QuoteDocumentStatus::Accepted, 'Accepter le devis', 'heroicon-o-check-badge', 'success'),
            self::transition('refuse', QuoteDocumentStatus::Refused, 'Refuser le devis', 'heroicon-o-x-circle', 'danger'),
            self::transition('cancel', QuoteDocumentStatus::Cancelled, 'Annuler le devis', 'heroicon-o-no-symbol', 'gray'),
        ];
    }

    private static function transition(string $name, QuoteDocumentStatus $target, string $label, string $icon, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription(fn (Quote $record): string => sprintf(
                'Le devis %s passera au statut « %s ».',
                $record->reference,
                $target->getLabel(),
            ))
            ->visible(fn (Quote $record): bool => $record->status->canTransitionTo($target))
            ->action(function (Quote $record) use ($target, $label): void {
                if (! $record->transitionTo($target)) {
                    Notification::make()
                        ->title('Transition impossible depuis le statut actuel.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($label.' : effectué')
                    ->success()
                    ->send();
            });
    }
}
