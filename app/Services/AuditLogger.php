<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\CrmTag;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Enregistre une entrée dans le journal d'audit.
     *
     * @param  array{old?: array<string, string|null>, new?: array<string, string|null>}  $changes
     */
    public function record(
        Model $subject,
        AuditEvent $event,
        array $changes = [],
        ?string $label = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => auth()->check() ? auth()->id() : null,
            'event' => $event,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'auditable_label' => $label ?? self::labelFor($subject),
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * Libellé lisible d'un enregistrement audité.
     */
    public static function labelFor(Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Quote => (string) $subject->reference,
            $subject instanceof CrmOpportunity => trim(($subject->reference ?? '').' — '.($subject->title ?? ''), ' —'),
            $subject instanceof CrmAccount => $subject->name,
            $subject instanceof CrmContact => $subject->fullName(),
            $subject instanceof CrmActivity => $subject->subject,
            $subject instanceof EmailMessage => $subject->subject,
            $subject instanceof EmailMailbox => $subject->name,
            $subject instanceof CrmTag => $subject->name,
            $subject instanceof User => $subject->name,
            default => class_basename($subject).' #'.$subject->getKey(),
        };
    }
}
