<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Tests policies d'autorisation pour s'assurer que les rôles admin et commercial
 * disposent des droits attendus sur les ressources CRM.
 */
test('les policies d autorisation fonctionnent correctement', function (): void
{
    // --- Admin ---
    $admin = User::factory()->create([
        'email' => 'admin@test.local',
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    // --- Commercial ---
    $commercial = User::factory()->create([
        'email' => 'commercial@test.local',
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    // ══════════════════════════════════════════════════════════════
    // 1️⃣ EmailMessagePolicy
    // ══════════════════════════════════════════════════════════════

    // viewAny : tout le monde (admin + commercial) peut voir la liste
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->viewAny($admin),
        'Admin doit pouvoir voir la liste des e-mails'
    );
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->viewAny($commercial),
        'Commercial doit pouvoir voir la liste des e-mails'
    );

    // view : admin ou commercial attaché à la boîte mail
    $mailbox = EmailMailbox::factory()->create([
        'email' => 'commercial@test.local',
        'is_shared' => true, // shared => commercial can view
    ]);

    $message = EmailMessage::factory()->create([
        'email_mailbox_id' => $mailbox->id,
        'from_email' => 'test@test.local',
    ]);

    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->view($admin, $message),
        'Admin doit pouvoir voir le message'
    );
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->view($commercial, $message),
        'Commercial attaché à une boîte partagée doit pouvoir voir le message'
    );

    // create : admin ou commercial
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->create($admin),
        'Admin doit pouvoir créer un e-mail'
    );
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->create($commercial),
        'Commercial doit pouvoir créer un e-mail'
    );

    // update : renvoie à view (même logique)
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->update($admin, $message),
        'Admin doit pouvoir mettre à jour le message'
    );
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->update($commercial, $message),
        'Commercial doit pouvoir mettre à jour le message'
    );

    // delete : uniquement admin
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->delete($admin, $message),
        'Admin doit pouvoir supprimer le message'
    );
    $this->assertFalse(
        app(\App\Policies\EmailMessagePolicy::class)->delete($commercial, $message),
        'Commercial ne doit pas pouvoir supprimer le message'
    );

    // deleteAny : uniquement admin
    $this->assertTrue(
        app(\App\Policies\EmailMessagePolicy::class)->deleteAny($admin),
        'Admin doit pouvoir supprimer n’importe quel e-mail'
    );
    $this->assertFalse(
        app(\App\Policies\EmailMessagePolicy::class)->deleteAny($commercial),
        'Commercial ne doit pas pouvoir supprimer n’importe quel e-mail'
    );

    // ══════════════════════════════════════════════════════════════
    // 2️⃣ CrmAccountPolicy (méthodes standards)
    // ══════════════════════════════════════════════════════════════

    $account = CrmAccount::factory()->create([
        'book' => \App\Enums\CrmBook::Prescribers,
        'owner_id' => $admin->id,
    ]);

    // viewAny
    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->viewAny($admin),
        'Admin doit pouvoir voir la liste des comptes'
    );
    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->viewAny($commercial),
        'Commercial doit pouvoir voir la liste des comptes'
    );

    // view : admin ou propriétaire
    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->view($admin, $account),
        'Admin doit pouvoir voir le compte'
    );

    // Commercial ne peut voir que ses propres comptes → on en crée un possédé par lui
    $commercialAccount = CrmAccount::factory()->create([
        'book' => \App\Enums\CrmBook::Prescribers,
        'owner_id' => $commercial->id,
    ]);

    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->view($commercial, $commercialAccount),
        'Commercial propriétaire doit pouvoir voir son propre compte'
    );
    $this->assertFalse(
        app(\App\Policies\CrmAccountPolicy::class)->view($commercial, $account),
        'Commercial ne doit pas pouvoir voir le compte d\'autre'
    );

    // create
    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->create($admin),
        'Admin doit pouvoir créer un compte'
    );
    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->create($commercial),
        'Commercial doit pouvoir créer un compte'
    );

    // update : renvoie à view
    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->update($admin, $account),
        'Admin doit pouvoir mettre à jour le compte'
    );
    $this->assertFalse(
        app(\App\Policies\CrmAccountPolicy::class)->update($commercial, $account),
        'Commercial ne doit pas pouvoir mettre à jour le compte d\'autre'
    );

    // Commercial ne peut mettre à jour que ses propres comptes
    $commercialAccount = CrmAccount::factory()->create([
        'book' => \App\Enums\CrmBook::Prescribers,
        'owner_id' => $commercial->id,
    ]);

    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->update($commercial, $commercialAccount),
        'Commercial propriétaire doit pouvoir mettre à jour son propre compte'
    );

    // delete : uniquement admin
    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->delete($admin, $account),
        'Admin doit pouvoir supprimer le compte'
    );
    $this->assertFalse(
        app(\App\Policies\CrmAccountPolicy::class)->delete($commercial, $account),
        'Commercial ne doit pas pouvoir supprimer le compte'
    );

    // deleteAny : uniquement admin
    $this->assertTrue(
        app(\App\Policies\CrmAccountPolicy::class)->deleteAny($admin),
        'Admin doit pouvoir supprimer n’importe quel compte'
    );
    $this->assertFalse(
        app(\App\Policies\CrmAccountPolicy::class)->deleteAny($commercial),
        'Commercial ne doit pas pouvoir supprimer n’importe quel compte'
    );

    // ══════════════════════════════════════════════════════════════
    // 3️⃣ CrmContactPolicy (modèle + policy existants)
    // ══════════════════════════════════════════════════════════════

    $contact = CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'first_name' => 'Test',
        'last_name' => 'Contact',
    ]);

    // viewAny
    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->viewAny($admin),
        'Admin doit pouvoir voir la liste des contacts'
    );
    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->viewAny($commercial),
        'Commercial doit pouvoir voir la liste des contacts'
    );

    // view : admin ou attaché au compte
    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->view($admin, $contact),
        'Admin doit pouvoir voir le contact'
    );

    // Commercial ne peut voir que les contacts de son propre compte
    $commercialAccount = CrmAccount::factory()->create([
        'book' => \App\Enums\CrmBook::Prescribers,
        'owner_id' => $commercial->id,
    ]);
    $commercialContact = CrmContact::factory()->create([
        'crm_account_id' => $commercialAccount->id,
        'first_name' => 'Commercial',
        'last_name' => 'Contact',
    ]);

    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->view($commercial, $commercialContact),
        'Commercial propriétaire du compte doit pouvoir voir le contact'
    );
    $this->assertFalse(
        app(\App\Policies\CrmContactPolicy::class)->view($commercial, $contact),
        'Commercial ne doit pas pouvoir voir un contact d\'autre compte'
    );

    // create
    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->create($admin),
        'Admin doit pouvoir créer un contact'
    );
    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->create($commercial),
        'Commercial doit pouvoir créer un contact'
    );

    // update : renvoie à view
    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->update($admin, $contact),
        'Admin doit pouvoir mettre à jour le contact'
    );
    $this->assertFalse(
        app(\App\Policies\CrmContactPolicy::class)->update($commercial, $contact),
        'Commercial ne doit pas pouvoir mettre à jour le contact d\'autre compte'
    );

    // Commercial ne peut mettre à jour que ses propres contacts
    $commercialAccount = CrmAccount::factory()->create([
        'book' => \App\Enums\CrmBook::Prescribers,
        'owner_id' => $commercial->id,
    ]);
    $commercialContact = CrmContact::factory()->create([
        'crm_account_id' => $commercialAccount->id,
        'first_name' => 'Commercial',
        'last_name' => 'Contact',
    ]);

    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->update($commercial, $commercialContact),
        'Commercial propriétaire doit pouvoir mettre à jour son propre contact'
    );

    // delete : uniquement admin
    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->delete($admin, $contact),
        'Admin doit pouvoir supprimer le contact'
    );
    $this->assertFalse(
        app(\App\Policies\CrmContactPolicy::class)->delete($commercial, $contact),
        'Commercial ne doit pas pouvoir supprimer le contact'
    );

    // deleteAny : uniquement admin
    $this->assertTrue(
        app(\App\Policies\CrmContactPolicy::class)->deleteAny($admin),
        'Admin doit pouvoir supprimer n’importe quel contact'
    );
    $this->assertFalse(
        app(\App\Policies\CrmContactPolicy::class)->deleteAny($commercial),
        'Commercial ne doit pas pouvoir supprimer n’importe quel contact'
    );

    // ══════════════════════════════════════════════════════════════
    // 4️⃣ CrmOpportunityPolicy (modèle + policy existants)
    // ══════════════════════════════════════════════════════════════

    $commercialAccount = CrmAccount::factory()->create([
        'book' => \App\Enums\CrmBook::Prescribers,
        'owner_id' => $commercial->id,
    ]);

    $opportunity = CrmOpportunity::factory()->create([
        'crm_account_id' => $commercialAccount->id,
        'owner_id' => $commercial->id,
        'stage' => 'nouveau',
        'amount_estimated' => 1000,
    ]);

    // viewAny
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->viewAny($admin),
        'Admin doit pouvoir voir la liste des affaires'
    );
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->viewAny($commercial),
        'Commercial doit pouvoir voir la liste des affaires'
    );

    // view : admin ou propriétaire du compte
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->view($admin, $opportunity),
        'Admin doit pouvoir voir l’affaire'
    );
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->view($commercial, $opportunity),
        'Commercial propriétaire du compte doit pouvoir voir l’affaire'
    );

    // create
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->create($admin),
        'Admin doit pouvoir créer une affaire'
    );
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->create($commercial),
        'Commercial doit pouvoir créer une affaire'
    );

    // update : renvoie à view
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->update($admin, $opportunity),
        'Admin doit pouvoir mettre à jour l’affaire'
    );
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->update($commercial, $opportunity),
        'Commercial doit pouvoir mettre à jour l’affaire'
    );

    // delete : uniquement admin
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->delete($admin, $opportunity),
        'Admin doit pouvoir supprimer l’affaire'
    );
    $this->assertFalse(
        app(\App\Policies\CrmOpportunityPolicy::class)->delete($commercial, $opportunity),
        'Commercial ne doit pas pouvoir supprimer l’affaire'
    );

    // deleteAny : uniquement admin
    $this->assertTrue(
        app(\App\Policies\CrmOpportunityPolicy::class)->deleteAny($admin),
        'Admin doit pouvoir supprimer n’importe quelle affaire'
    );
    $this->assertFalse(
        app(\App\Policies\CrmOpportunityPolicy::class)->deleteAny($commercial),
        'Commercial ne doit pas pouvoir supprimer n’importe quelle affaire'
    );

    // ══════════════════════════════════════════════════════════════
    // 5️⃣ QuotePolicy (modèle + policy existants)
    // ══════════════════════════════════════════════════════════════

    $quote = Quote::factory()->create([
        'crm_account_id' => $commercialAccount->id,
        'owner_id' => $commercial->id,
        'status' => 'draft',
    ]);

    // viewAny
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->viewAny($admin),
        'Admin doit pouvoir voir la liste des devis'
    );
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->viewAny($commercial),
        'Commercial doit pouvoir voir la liste des devis'
    );

    // view : admin ou propriétaire du compte
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->view($admin, $quote),
        'Admin doit pouvoir voir le devis'
    );
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->view($commercial, $quote),
        'Commercial propriétaire du compte doit pouvoir voir le devis'
    );

    // create
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->create($admin),
        'Admin doit pouvoir créer un devis'
    );
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->create($commercial),
        'Commercial doit pouvoir créer un devis'
    );

    // update : renvoie à view
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->update($admin, $quote),
        'Admin doit pouvoir mettre à jour le devis'
    );
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->update($commercial, $quote),
        'Commercial doit pouvoir mettre à jour le devis'
    );

    // delete : uniquement admin
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->delete($admin, $quote),
        'Admin doit pouvoir supprimer le devis'
    );
    $this->assertFalse(
        app(\App\Policies\QuotePolicy::class)->delete($commercial, $quote),
        'Commercial ne doit pas pouvoir supprimer le devis'
    );

    // deleteAny : uniquement admin
    $this->assertTrue(
        app(\App\Policies\QuotePolicy::class)->deleteAny($admin),
        'Admin doit pouvoir supprimer n’importe quel devis'
    );
    $this->assertFalse(
        app(\App\Policies\QuotePolicy::class)->deleteAny($commercial),
        'Commercial ne doit pas pouvoir supprimer n’importe quel devis'
    );
});