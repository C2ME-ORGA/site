<?php

namespace App\Services;

use App\Models\CrmActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IcsCalendar
{
    /**
     * Construit un flux iCalendar des activités planifiées de l'utilisateur.
     */
    public function forUser(User $user): string
    {
        $activities = CrmActivity::query()
            ->pending()
            ->where(fn (Builder $query) => $query->forUser($user))
            ->with(['account'])
            ->orderBy('due_at')
            ->get();

        return $this->build($activities);
    }

    /**
     * @param  Collection<int, CrmActivity>  $activities
     */
    public function build(Collection $activities): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//C2ME//CRM//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($activities as $activity) {
            $start = $activity->due_at;

            if ($start === null) {
                continue;
            }

            $summary = $activity->type->getLabel().' : '.$activity->subject;
            $description = collect([
                $activity->account?->name,
                $activity->body,
            ])->filter()->implode(' — ');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:activity-'.$activity->id.'@c2me.fr';
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$start->copy()->addHour()->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->escape($summary);

            if ($description !== '') {
                $lines[] = 'DESCRIPTION:'.$this->escape($description);
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $value,
        );
    }
}
