<?php

namespace Database\Seeders;

use App\Enums\CrmActivityType;
use App\Enums\CrmBook;
use App\Enums\QuoteDocumentStatus;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\CrmTag;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin)->first()
            ?? User::factory()->create([
                'name' => 'Admin démo',
                'email' => 'demo.admin@c2me.fr',
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);

        $commercial = User::firstOrCreate(
            ['email' => 'demo.commercial@c2me.fr'],
            [
                'name' => 'Camille Commercial',
                'role' => UserRole::Commercial,
                'is_active' => true,
                'password' => Hash::make('password'),
            ],
        );

        $mailbox = EmailMailbox::firstOrCreate(
            ['email' => 'commercial@c2me.fr'],
            [
                'name' => 'Commercial',
                'is_shared' => true,
                'imap_host' => 'imap.orange.fr',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'imap_username' => 'commercial@c2me.fr',
                'imap_password' => 'secret',
                'smtp_host' => 'smtp.orange.fr',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'smtp_username' => 'commercial@c2me.fr',
                'smtp_password' => 'secret',
                'is_active' => true,
            ],
        );

        foreach (['Chaud', 'Partenaire', 'Grand compte'] as $name) {
            CrmTag::firstOrCreate(['name' => $name], ['color' => 'info', 'kind' => 'libre']);
        }

        $allAccounts = collect();

        foreach (CrmBook::cases() as $index => $book) {
            $owner = $index === 0 ? $admin : $commercial;

            $accounts = CrmAccount::factory()
                ->forBook($book)
                ->count(4)
                ->create([
                    'owner_id' => $owner->id,
                    'last_contacted_at' => now()->subDays(random_int(1, 20)),
                    'next_follow_up_at' => now()->addDays(random_int(1, 14)),
                ]);

            foreach ($accounts as $account) {
                $contact = CrmContact::factory()->primary()->create([
                    'crm_account_id' => $account->id,
                ]);

                CrmContact::factory()->create([
                    'crm_account_id' => $account->id,
                ]);

                $opportunity = CrmOpportunity::factory()->create([
                    'crm_account_id' => $account->id,
                    'crm_contact_id' => $contact->id,
                    'owner_id' => $owner->id,
                ]);

                CrmActivity::factory()->count(2)->create([
                    'crm_account_id' => $account->id,
                    'crm_contact_id' => $contact->id,
                    'crm_opportunity_id' => $opportunity->id,
                    'user_id' => $owner->id,
                    'type' => CrmActivityType::FollowUp,
                    'occurred_at' => null,
                    'due_at' => now()->addDays(random_int(1, 10)),
                ]);

                $quote = Quote::factory()->create([
                    'crm_account_id' => $account->id,
                    'crm_contact_id' => $contact->id,
                    'crm_opportunity_id' => $opportunity->id,
                    'owner_id' => $owner->id,
                    'status' => QuoteDocumentStatus::Sent,
                    'sent_at' => now()->subDays(random_int(1, 5)),
                ]);

                QuoteLine::create([
                    'quote_id' => $quote->id,
                    'position' => 1,
                    'designation' => 'Découpe laser — tôle acier 3 mm',
                    'quantity' => 10,
                    'unit' => 'u',
                    'unit_price' => 85,
                ]);

                QuoteLine::create([
                    'quote_id' => $quote->id,
                    'position' => 2,
                    'designation' => 'Pliage et finition',
                    'quantity' => 1,
                    'unit' => 'forfait',
                    'unit_price' => 240,
                ]);
            }

            $allAccounts = $allAccounts->merge($accounts);
        }

        foreach ($allAccounts->take(4) as $index => $account) {
            $contact = $account->contacts()->first();

            EmailMessage::factory()->create([
                'email_mailbox_id' => $mailbox->id,
                'from_email' => $account->contacts()->value('email') ?? 'client@example.test',
                'from_name' => $contact?->fullName(),
                'subject' => 'Demande de devis',
                'body_text' => 'Bonjour, pouvez-vous nous établir un devis pour ce projet ?',
                'crm_account_id' => $account->id,
                'crm_contact_id' => $contact?->id,
                'is_read' => $index > 0,
            ]);
        }

        $this->command->info(sprintf(
            'Démo CRM : %d comptes, %d contacts, %d devis.',
            $allAccounts->count(),
            CrmContact::query()->count(),
            Quote::query()->count(),
        ));
    }
}
