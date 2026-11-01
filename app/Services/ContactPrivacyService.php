<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\EmailMessage;
use App\Models\Quote;
use Carbon\CarbonInterface;

/**
 * Exercice des droits RGPD sur un contact : export des données (droit
 * d’accès et de portabilité) et anonymisation (droit à l’effacement).
 *
 * L’anonymisation conserve l’identifiant et le rattachement au compte : les
 * affaires, devis et activités restent cohérents, mais plus aucune donnée
 * identifiante n’est associée au contact.
 */
class ContactPrivacyService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Données personnelles détenues sur un contact, dans un format lisible
     * et portable (JSON).
     *
     * @return array<string, mixed>
     */
    public function export(CrmContact $contact): array
    {
        $contact->loadMissing(['account', 'activities', 'quotes', 'emails', 'opportunities']);

        return [
            'exported_at' => now()->toIso8601String(),
            'contact' => [
                'id' => $contact->getKey(),
                'civility' => $contact->civility,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'job_title' => $contact->job_title,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'mobile' => $contact->mobile,
                'is_primary' => $contact->is_primary,
                'decision_maker' => $contact->decision_maker,
                'do_not_contact' => $contact->do_not_contact,
                'notes' => $contact->notes,
                'last_contacted_at' => $this->date($contact->last_contacted_at),
                'created_at' => $this->date($contact->created_at),
                'updated_at' => $this->date($contact->updated_at),
            ],
            'account' => $contact->account === null ? null : [
                'name' => $contact->account->name,
                'legal_name' => $contact->account->legal_name,
                'siret' => $contact->account->siret,
                'vat_number' => $contact->account->vat_number,
                'website' => $contact->account->website,
                'email' => $contact->account->email,
                'phone' => $contact->account->phone,
                'mobile' => $contact->account->mobile,
                'address_line1' => $contact->account->address_line1,
                'address_line2' => $contact->account->address_line2,
                'postal_code' => $contact->account->postal_code,
                'city' => $contact->account->city,
                'country' => $contact->account->country,
                'status' => $contact->account->status->value,
            ],
            'opportunities' => $contact->opportunities
                ->map(fn (CrmOpportunity $opportunity): array => [
                    'reference' => $opportunity->reference,
                    'title' => $opportunity->title,
                    'description' => $opportunity->description,
                    'stage' => $opportunity->stage->value,
                    'amount_estimated' => $opportunity->amount_estimated,
                    'expected_close_date' => $this->date($opportunity->expected_close_date),
                    'won_at' => $this->date($opportunity->won_at),
                    'closed_at' => $this->date($opportunity->closed_at),
                ])->values()->all(),
            'quotes' => $contact->quotes
                ->map(fn (Quote $quote): array => [
                    'reference' => $quote->reference,
                    'subject' => $quote->subject,
                    'status' => $quote->status->value,
                    'issued_at' => $this->date($quote->issued_at),
                    'valid_until' => $this->date($quote->valid_until),
                    'amount_ht' => $quote->amount_ht,
                    'amount_ttc' => $quote->amount_ttc,
                    'sent_at' => $this->date($quote->sent_at),
                    'accepted_at' => $this->date($quote->accepted_at),
                    'refused_at' => $this->date($quote->refused_at),
                ])->values()->all(),
            'activities' => $contact->activities
                ->map(fn (CrmActivity $activity): array => [
                    'type' => $activity->type->value,
                    'subject' => $activity->subject,
                    'body' => $activity->body,
                    'outcome' => $activity->outcome,
                    'occurred_at' => $this->date($activity->occurred_at),
                    'due_at' => $this->date($activity->due_at),
                    'completed_at' => $this->date($activity->completed_at),
                ])->values()->all(),
            'emails' => $contact->emails
                ->map(fn (EmailMessage $email): array => [
                    'direction' => $email->is_sent ? 'sortant' : 'entrant',
                    'from_email' => $email->from_email,
                    'from_name' => $email->from_name,
                    'to_recipients' => $email->to_recipients,
                    'subject' => $email->subject,
                    'sent_at' => $this->date($email->sent_at),
                    'received_at' => $this->date($email->received_at),
                ])->values()->all(),
        ];
    }

    /**
     * Efface les données identifiantes du contact. Les affaires, devis,
     * activités et e-mails existants sont conservés (historique commercial
     * et obligations comptables) mais ne sont plus rattachés à une personne
     * identifiable.
     */
    public function anonymize(CrmContact $contact): void
    {
        $contact->forceFill([
            'civility' => null,
            'first_name' => 'Contact',
            'last_name' => 'anonymisé',
            'job_title' => null,
            'email' => null,
            'phone' => null,
            'mobile' => null,
            'notes' => null,
            'is_primary' => false,
            'decision_maker' => false,
            // Empêche toute reprise de prospection sur cette fiche.
            'do_not_contact' => true,
        ])->save();

        // Le journal d’audit ne conserve aucune valeur personnelle effacée.
        $this->auditLogger->record($contact, AuditEvent::Anonymized, [
            'new' => [
                'first_name' => 'Contact',
                'last_name' => 'anonymisé',
                'email' => null,
                'phone' => null,
                'mobile' => null,
                'notes' => null,
                'do_not_contact' => '1',
            ],
        ], 'Contact anonymisé #'.$contact->getKey());
    }

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }
}
