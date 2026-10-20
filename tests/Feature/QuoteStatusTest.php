<?php

namespace Tests\Feature;

use App\Enums\QuoteDocumentStatus;
use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;

uses(RefreshDatabase::class);

test('les transitions de statut de devis sont correctes', function (): void
{
    // --- Statuts de test ---
    $draftStatus = QuoteDocumentStatus::Draft;
    $sentStatus = QuoteDocumentStatus::Sent;
    $acceptedStatus = QuoteDocumentStatus::Accepted;
    $refusedStatus = QuoteDocumentStatus::Refused;
    $expiredStatus = QuoteDocumentStatus::Expired;
    $cancelledStatus = QuoteDocumentStatus::Cancelled;

    // --- Création de devis dans chaque statut ---
    $draftQuote = Quote::factory()->create(['status' => $draftStatus->value]);
    $sentQuote = Quote::factory()->create(['status' => $sentStatus->value]);
    $acceptedQuote = Quote::factory()->create(['status' => $acceptedStatus->value]);
    $refusedQuote = Quote::factory()->create(['status' => $refusedStatus->value]);
    $expiredQuote = Quote::factory()->create(['status' => $expiredStatus->value]);
    $cancelledQuote = Quote::factory()->create(['status' => $cancelledStatus->value]);

    // --- 1. Transitions depuis Brouillon ---
    $this->assertTrue(
        in_array($sentStatus, $draftStatus->allowedTransitions(), true),
        'Depuis Brouillon : on peut Envoyer'
    );
    $this->assertTrue(
        in_array($cancelledStatus, $draftStatus->allowedTransitions(), true),
        'Depuis Brouillon : on peut Annuler'
    );

    // --- 2. Transitions depuis Envoyé ---
    $this->assertTrue(
        in_array($acceptedStatus, $sentStatus->allowedTransitions(), true),
        'Depuis Envoyé : on peut Accepter'
    );
    $this->assertTrue(
        in_array($refusedStatus, $sentStatus->allowedTransitions(), true),
        'Depuis Envoyé : on peut Refuser'
    );
    $this->assertTrue(
        in_array($expiredStatus, $sentStatus->allowedTransitions(), true),
        'Depuis Envoyé : on peut Expirer'
    );
    $this->assertTrue(
        in_array($cancelledStatus, $sentStatus->allowedTransitions(), true),
        'Depuis Envoyé : on peut Annuler'
    );

    // --- 3. Transitions depuis Accepté (terminal) ---
    $this->assertEmpty($acceptedStatus->allowedTransitions(), 'Accepté est terminal');

    // --- 4. Transitions depuis Refusé (terminal) ---
    $this->assertEmpty($refusedStatus->allowedTransitions(), 'Refusé est terminal');

    // --- 5. Transitions depuis Expiré ---
    $this->assertTrue(
        in_array($sentStatus, $expiredStatus->allowedTransitions(), true),
        'Depuis Expiré : on peut Retourner Envoyé'
    );
    $this->assertTrue(
        in_array($cancelledStatus, $expiredStatus->allowedTransitions(), true),
        'Depuis Expiré : on peut Annuler'
    );

    // --- 6. Transitions depuis Annulé (terminal) ---
    $this->assertEmpty($cancelledStatus->allowedTransitions(), 'Annulé est terminal');
});

test('un administrateur peut voir tous les devis', function (): void
{
    $admin = User::factory()->create([
        'email' => 'admin@test.local',
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $quote = Quote::factory()->create();

    $this->assertTrue(
        (new \App\Policies\QuotePolicy)->viewAny($admin)
    );
});

test('un commercial peut voir ses propres devis', function (): void
{
    $commercial = User::factory()->create([
        'email' => 'commercial@test.local',
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $account = \App\Models\CrmAccount::factory()->create([
        'book' => \App\Enums\CrmBook::Prescribers,
        'owner_id' => $commercial->id,
    ]);

    $quote = Quote::factory()->create([
        'crm_account_id' => $account->id,
        'owner_id' => $commercial->id,
    ]);

    $this->assertTrue(
        (new \App\Policies\QuotePolicy)->view($commercial, $quote)
    );
});

test('un commercial ne peut pas voir les devis des autres comptes', function (): void
{
    $commercial = User::factory()->create([
        'email' => 'commercial@test.local',
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $otherAccount = \App\Models\CrmAccount::factory()->create([
        'book' => \App\Enums\CrmBook::Prescribers,
        'owner_id' => User::factory()->create(['role' => UserRole::Admin, 'is_active' => true])->id,
    ]);

    $quote = Quote::factory()->create([
        'crm_account_id' => $otherAccount->id,
        'owner_id' => User::factory()->create(['role' => UserRole::Admin, 'is_active' => true])->id,
    ]);

    $this->assertFalse(
        (new \App\Policies\QuotePolicy)->view($commercial, $quote)
    );
});