<?php

namespace App\Notifications;

use App\Enums\QuoteDocumentStatus;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuoteStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Quote $quote,
        public readonly QuoteDocumentStatus $from,
        public readonly QuoteDocumentStatus $to,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon($this->icon())
            ->color($this->color())
            ->actions([
                FilamentAction::make('view')
                    ->label('Voir le devis')
                    ->url(QuoteResource::getUrl('edit', ['record' => $this->quote]))
                    ->color('primary'),
            ])
            ->getDatabaseMessage();
    }

    public function title(): string
    {
        return sprintf('Devis %s — %s', $this->quote->reference, $this->to->getLabel());
    }

    public function body(): string
    {
        return sprintf(
            'Statut modifié de « %s » à « %s ».',
            $this->from->getLabel(),
            $this->to->getLabel(),
        );
    }

    public function icon(): string
    {
        return match ($this->to) {
            QuoteDocumentStatus::Draft => 'heroicon-o-document-text',
            QuoteDocumentStatus::Sent => 'heroicon-o-paper-airplane',
            QuoteDocumentStatus::Accepted => 'heroicon-o-check-badge',
            QuoteDocumentStatus::Refused => 'heroicon-o-x-circle',
            QuoteDocumentStatus::Expired => 'heroicon-o-clock',
            QuoteDocumentStatus::Cancelled => 'heroicon-o-no-symbol',
        };
    }

    public function color(): string
    {
        return match ($this->to) {
            QuoteDocumentStatus::Draft => 'gray',
            QuoteDocumentStatus::Sent => 'info',
            QuoteDocumentStatus::Accepted => 'success',
            QuoteDocumentStatus::Refused => 'danger',
            QuoteDocumentStatus::Expired => 'warning',
            QuoteDocumentStatus::Cancelled => 'gray',
        };
    }
}
