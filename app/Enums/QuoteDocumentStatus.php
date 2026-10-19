<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuoteDocumentStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';

    case Sent = 'sent';

    case Accepted = 'accepted';

    case Refused = 'refused';

    case Expired = 'expired';

    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Sent => 'Envoyé',
            self::Accepted => 'Accepté',
            self::Refused => 'Refusé',
            self::Expired => 'Expiré',
            self::Cancelled => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Refused => 'danger',
            self::Expired => 'warning',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Étape d'affaire correspondant au statut du devis.
     */
    public function opportunityStage(): ?OpportunityStage
    {
        return match ($this) {
            self::Draft => OpportunityStage::Study,
            self::Sent => OpportunityStage::QuoteSent,
            self::Accepted => OpportunityStage::Won,
            self::Refused => OpportunityStage::Lost,
            self::Expired => OpportunityStage::Closed,
            self::Cancelled => null,
        };
    }

    /**
     * Statuts accessibles depuis l'état courant.
     *
     * Le cycle de vie est strict : un devis accepté ou refusé est définitif,
     * et seul un devis envoyé peut être accepté, refusé ou expiré.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Accepted, self::Refused, self::Expired, self::Cancelled],
            self::Expired => [self::Sent, self::Cancelled],
            self::Accepted, self::Refused, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Un statut terminal ne peut plus évoluer.
     */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }
}
