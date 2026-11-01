<?php

namespace App\Services;

use App\Enums\CrmBook;
use App\Models\CalendarEvent;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IcsCalendar
{
    /**
     * Construit un flux iCalendar des activités planifiées et des relances
     * de comptes visibles par l'utilisateur.
     */
    public function forUser(User $user): string
    {
        $activities = CrmActivity::query()
            ->pending()
            ->where(fn (Builder $query) => $query->forUser($user))
            ->with(['account', 'contact'])
            ->orderBy('due_at')
            ->get();

        $followUps = (bool) config('crm.calendar.include_follow_ups', true)
            ? CrmAccount::query()
                ->ownedBy($user)
                ->whereNotNull('next_follow_up_at')
                ->orderBy('next_follow_up_at')
                ->get()
            : collect();

        $events = CalendarEvent::query()
            ->visibleTo($user)
            ->with(['account', 'contact', 'owner'])
            ->where(fn (Builder $query) => $query
                ->where('starts_at', '>=', now()->subMonths((int) config('crm.calendar.feed_past_months', 12)))
                ->orWhereNotNull('recurrence'))
            ->orderBy('starts_at')
            ->get();

        return $this->build($activities, $followUps, $events, $user);
    }

    /**
     * @param  Collection<int, CrmActivity>  $activities
     * @param  Collection<int, CrmAccount>  $followUps
     * @param  Collection<int, CalendarEvent>  $events
     */
    public function build(Collection $activities, Collection $followUps, Collection $events, ?User $user = null): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//C2ME//CRM//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($user === null ? 'C2ME' : 'C2ME — Agenda de '.$user->name),
            'X-WR-TIMEZONE:Europe/Paris',
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($activities as $activity) {
            array_push($lines, ...$this->activityLines($activity));
        }

        foreach ($followUps as $account) {
            array_push($lines, ...$this->followUpLines($account));
        }

        foreach ($events as $event) {
            array_push($lines, ...$this->eventLines($event, $user));
        }

        $lines[] = 'END:VCALENDAR';

        $output = [];

        foreach ($lines as $line) {
            array_push($output, ...$this->fold($line));
        }

        return implode("\r\n", $output)."\r\n";
    }

    /**
     * @return list<string>
     */
    private function activityLines(CrmActivity $activity): array
    {
        $start = $activity->due_at;

        if ($start === null) {
            return [];
        }

        $duration = max(15, (int) config('crm.calendar.duration_minutes', 60));
        $summary = $activity->type->getLabel().' : '.$activity->subject;
        $url = $this->activityUrl($activity);
        $description = implode("\n", array_filter([
            $activity->account?->name,
            $activity->contact?->fullName(),
            $activity->body,
            $url,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));

        $lines = [
            'BEGIN:VEVENT',
            'UID:activity-'.$activity->id.'@c2me.fr',
            'DTSTAMP:'.$this->timestamp(now()),
            'DTSTART:'.$this->timestamp($start),
            'DTEND:'.$this->timestamp($start->addMinutes($duration)),
            'SUMMARY:'.$this->escape($summary),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'CATEGORIES:'.$this->escape($activity->type->getLabel()),
        ];

        if ($description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escape($description);
        }

        if ($activity->updated_at !== null) {
            $lines[] = 'LAST-MODIFIED:'.$this->timestamp($activity->updated_at);
        }

        $location = $activity->account?->fullAddress();

        if ($location !== null && $location !== '') {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        if ($url !== null) {
            $lines[] = 'URL:'.$url;
        }

        array_push($lines, ...$this->activityAlarm($activity));

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function activityAlarm(CrmActivity $activity): array
    {
        $start = $activity->due_at;

        if ($start === null) {
            return [];
        }

        $remindAt = $activity->remind_at;

        $trigger = $remindAt !== null && $remindAt->lessThanOrEqualTo($start)
            ? 'TRIGGER;VALUE=DATE-TIME:'.$this->timestamp($remindAt)
            : 'TRIGGER:-PT'.max(0, (int) config('crm.calendar.default_reminder_minutes', 15)).'M';

        return [
            'BEGIN:VALARM',
            $trigger,
            'ACTION:DISPLAY',
            'DESCRIPTION:'.$this->escape('Rappel : '.$activity->subject),
            'END:VALARM',
        ];
    }

    /**
     * Les relances sont des événements de journée entière, avec un rappel
     * à l'heure configurée (9 h par défaut) le jour J.
     *
     * @return list<string>
     */
    private function followUpLines(CrmAccount $account): array
    {
        $date = $account->next_follow_up_at;

        if ($date === null) {
            return [];
        }

        $summary = 'Relance : '.$account->name;
        $url = $this->accountUrl($account);
        $description = implode("\n", array_filter([
            $account->notes,
            $url,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));

        $lines = [
            'BEGIN:VEVENT',
            'UID:follow-up-'.$account->id.'@c2me.fr',
            'DTSTAMP:'.$this->timestamp(now()),
            'DTSTART;VALUE=DATE:'.$date->format('Ymd'),
            'DTEND;VALUE=DATE:'.$date->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.$this->escape($summary),
            'STATUS:CONFIRMED',
            'TRANSP:TRANSPARENT',
            'CATEGORIES:Relance',
        ];

        if ($description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escape($description);
        }

        if ($account->updated_at !== null) {
            $lines[] = 'LAST-MODIFIED:'.$this->timestamp($account->updated_at);
        }

        $location = $account->fullAddress();

        if ($location !== '') {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        if ($url !== null) {
            $lines[] = 'URL:'.$url;
        }

        $reminder = $date->copy()->setTime(
            (int) config('crm.calendar.follow_up_reminder_hour', 9),
            0,
        );

        if ($reminder->isFuture()) {
            $lines = [
                ...$lines,
                'BEGIN:VALARM',
                'TRIGGER;VALUE=DATE-TIME:'.$this->timestamp($reminder),
                'ACTION:DISPLAY',
                'DESCRIPTION:'.$this->escape('Rappel : '.$summary),
                'END:VALARM',
            ];
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function eventLines(CalendarEvent $event, ?User $viewer): array
    {
        $start = $event->starts_at->toImmutable();
        $end = $event->ends_at?->toImmutable();

        $url = route('filament.admin.pages.calendar', ['date' => $start->format('Y-m-d')]);
        $description = implode("\n", array_filter([
            $event->account?->name,
            $event->contact?->fullName(),
            $event->description,
            $viewer !== null && ! $event->isOwnedBy($viewer) ? 'Partagé par '.$event->owner?->name : null,
            $url,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));

        $lines = [
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@c2me.fr',
            'DTSTAMP:'.$this->timestamp(now()),
            'SUMMARY:'.$this->escape($event->title),
            'STATUS:CONFIRMED',
            'TRANSP:'.($event->all_day ? 'TRANSPARENT' : 'OPAQUE'),
            'CATEGORIES:'.$this->escape('Événement'),
        ];

        if ($event->all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.($end !== null ? $end->startOfDay()->addDay() : $start->addDay())->format('Ymd');
        } else {
            $lines[] = 'DTSTART:'.$this->timestamp($start);
            $lines[] = 'DTEND:'.$this->timestamp($end ?? $start->addHour());
        }

        if ($description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escape($description);
        }

        if ($event->updated_at !== null) {
            $lines[] = 'LAST-MODIFIED:'.$this->timestamp($event->updated_at);
        }

        if ($event->location !== null && $event->location !== '') {
            $lines[] = 'LOCATION:'.$this->escape($event->location);
        }

        $lines[] = 'URL:'.$url;

        $rule = match ($event->recurrence) {
            'weekly' => 'FREQ=WEEKLY',
            'monthly' => 'FREQ=MONTHLY',
            default => null,
        };

        if ($rule !== null) {
            if ($event->recurrence_until !== null) {
                $until = $event->recurrence_until->toImmutable();

                $rule .= $event->all_day
                    ? ';UNTIL='.$until->format('Ymd')
                    : ';UNTIL='.$this->timestamp($until->endOfDay());
            }

            $lines[] = 'RRULE:'.$rule;
        }

        if ($event->reminder_minutes !== null) {
            $lines = [
                ...$lines,
                'BEGIN:VALARM',
                'TRIGGER:-PT'.max(0, (int) $event->reminder_minutes).'M',
                'ACTION:DISPLAY',
                'DESCRIPTION:'.$this->escape('Rappel : '.$event->title),
                'END:VALARM',
            ];
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function activityUrl(CrmActivity $activity): ?string
    {
        if ($activity->getKey() === null) {
            return null;
        }

        return route('filament.admin.resources.activities.edit', ['record' => $activity]);
    }

    private function accountUrl(CrmAccount $account): ?string
    {
        if ($account->getKey() === null) {
            return null;
        }

        $route = match ($account->book) {
            CrmBook::Prescribers => 'filament.admin.resources.prescriber-accounts.edit',
            CrmBook::IndustrialClients => 'filament.admin.resources.industrial-accounts.edit',
            CrmBook::BuildingCompanies => 'filament.admin.resources.building-company-accounts.edit',
        };

        return route($route, ['record' => $account]);
    }

    private function timestamp(\DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->utc()->format('Ymd\THis\Z');
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }

    /**
     * Replie une ligne à 75 octets maximum, comme exigé par la RFC 5545.
     *
     * @return list<string>
     */
    private function fold(string $line): array
    {
        if ($line === '') {
            return [''];
        }

        $lines = [];
        $offset = 0;
        $bytes = strlen($line);

        while ($offset < $bytes) {
            $limit = $offset === 0 ? 75 : 74;
            $chunk = mb_strcut($line, $offset, $limit, 'UTF-8');

            if ($chunk === '') {
                $chunk = substr($line, $offset, $limit);
            }

            $lines[] = ($offset === 0 ? '' : ' ').$chunk;
            $offset += strlen($chunk);
        }

        return $lines;
    }
}
