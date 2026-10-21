<?php

namespace App\Notifications;

use App\Enums\FollowUpReminderStage;
use App\Models\CrmFollowUpReminder;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class FollowUpDigest extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, CrmFollowUpReminder>  $reminders
     */
    public function __construct(
        public readonly FollowUpReminderStage $stage,
        public readonly Collection $reminders,
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
                    ->label('Ouvrir mes relances')
                    ->url($this->url())
                    ->color('primary'),
            ])
            ->getDatabaseMessage();
    }

    public function title(): string
    {
        return $this->stage->notificationTitle($this->reminders->count());
    }

    public function body(): string
    {
        $limit = max(1, (int) config('crm.follow_ups.max_notification_items', 5));
        $visible = $this->reminders->take($limit);

        $lines = $visible
            ->map(function (CrmFollowUpReminder $reminder): string {
                $name = $reminder->account->name;
                $dueAt = $reminder->due_at->format('d/m à H:i');
                $overdue = $this->overdueLabel($reminder);

                return '• '.$name.' — '.$dueAt.($overdue !== null ? ' ('.$overdue.')' : '');
            })
            ->values()
            ->all();

        $remaining = $this->reminders->count() - $visible->count();

        if ($remaining > 0) {
            $lines[] = sprintf('+ %d autre(s)', $remaining);
        }

        return implode("\n", $lines);
    }

    public function icon(): string
    {
        return $this->hasOverdue() || $this->stage->isDueDay()
            ? 'heroicon-o-exclamation-triangle'
            : 'heroicon-o-arrow-path';
    }

    public function color(): string
    {
        return $this->hasOverdue() ? 'danger' : $this->stage->getColor();
    }

    public function url(): string
    {
        return url('/admin/relances?stage='.$this->stage->value);
    }

    private function hasOverdue(): bool
    {
        return $this->reminders->contains(
            fn (CrmFollowUpReminder $reminder): bool => $this->overdueLabel($reminder) !== null,
        );
    }

    private function overdueLabel(CrmFollowUpReminder $reminder): ?string
    {
        $dueDay = $reminder->due_at->startOfDay();
        $today = now()->startOfDay();

        if ($dueDay->greaterThanOrEqualTo($today)) {
            return null;
        }

        $days = (int) $dueDay->diffInDays($today);

        return sprintf('en retard de %d j', $days);
    }
}
