<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Models\CrmAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('crm:anonymize-prospects')]
#[Description('Anonymise les prospects inactifs au-delà de la durée de conservation.')]
class AnonymizeInactiveProspects extends Command
{
    public function handle(): int
    {
        $months = (int) config('crm.retention.prospects_months', 36);
        $cutoff = now()->subMonths($months);

        $accounts = 0;
        $contacts = 0;

        CrmAccount::query()
            ->whereIn('status', [AccountStatus::Prospect->value, AccountStatus::Inactive->value])
            ->where(fn (Builder $query) => $query
                ->where('last_contacted_at', '<', $cutoff)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('last_contacted_at')
                    ->where('updated_at', '<', $cutoff)))
            ->whereDoesntHave('quotes')
            ->whereDoesntHave('opportunities')
            ->where(fn (Builder $query) => $query
                ->whereNotNull('email')
                ->orWhereNotNull('phone')
                ->orWhereNotNull('mobile'))
            ->chunkById(100, function ($batch) use (&$accounts, &$contacts): void {
                foreach ($batch as $account) {
                    $contacts += $account->contacts()->update([
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
                        'do_not_contact' => true,
                        'last_contacted_at' => null,
                    ]);

                    $account->forceFill([
                        'name' => 'Prospect anonymisé #'.$account->id,
                        'legal_name' => null,
                        'siret' => null,
                        'vat_number' => null,
                        'website' => null,
                        'email' => null,
                        'phone' => null,
                        'mobile' => null,
                        'address_line1' => null,
                        'address_line2' => null,
                        'postal_code' => null,
                        'city' => null,
                        'notes' => null,
                        'metadata' => null,
                        'status' => AccountStatus::Inactive,
                        'last_contacted_at' => null,
                        'next_follow_up_at' => null,
                    ])->save();

                    $accounts++;
                }
            });

        $this->info(sprintf(
            '%d prospect(s) anonymisé(s), %d contact(s) — inactivité de %d mois.',
            $accounts,
            $contacts,
            $months
        ));

        return self::SUCCESS;
    }
}
