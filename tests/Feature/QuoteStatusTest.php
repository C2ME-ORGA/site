<?php

use App\Enums\QuoteDocumentStatus;
use App\Enums\UserRole;
use App\Mail\QuoteStatusChangedMail;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\QuoteStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function quoteOwner(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

test('la machine à états du devis autorise et interdit les bons statuts', function (): void {
    expect(QuoteDocumentStatus::Draft->canTransitionTo(QuoteDocumentStatus::Sent))->toBeTrue()
        ->and(QuoteDocumentStatus::Draft->canTransitionTo(QuoteDocumentStatus::Accepted))->toBeFalse()
        ->and(QuoteDocumentStatus::Sent->canTransitionTo(QuoteDocumentStatus::Accepted))->toBeTrue()
        ->and(QuoteDocumentStatus::Sent->canTransitionTo(QuoteDocumentStatus::Expired))->toBeTrue()
        ->and(QuoteDocumentStatus::Accepted->canTransitionTo(QuoteDocumentStatus::Sent))->toBeFalse()
        ->and(QuoteDocumentStatus::Accepted->isTerminal())->toBeTrue()
        ->and(QuoteDocumentStatus::Sent->isTerminal())->toBeFalse();
});

test('transitionTo applique une transition valide et horodate l’envoi', function (): void {
    $quote = Quote::factory()->create(['status' => QuoteDocumentStatus::Draft]);

    expect($quote->transitionTo(QuoteDocumentStatus::Sent))->toBeTrue();

    $quote->refresh();

    expect($quote->status)->toBe(QuoteDocumentStatus::Sent)
        ->and($quote->sent_at)->not->toBeNull();
});

test('transitionTo refuse une transition interdite', function (): void {
    $quote = Quote::factory()->create(['status' => QuoteDocumentStatus::Draft]);

    expect($quote->transitionTo(QuoteDocumentStatus::Accepted))->toBeFalse()
        ->and($quote->refresh()->status)->toBe(QuoteDocumentStatus::Draft);
});

test('une transition interdite enregistrée directement lève une exception', function (): void {
    $quote = Quote::factory()->create(['status' => QuoteDocumentStatus::Accepted]);

    expect(fn () => $quote->update(['status' => QuoteDocumentStatus::Sent]))
        ->toThrow(DomainException::class);
});

test('les lignes calculent leur total et celui du devis', function (): void {
    $quote = Quote::factory()->create(['amount_ht' => 0, 'vat_rate' => 20]);

    $line = $quote->lines()->create([
        'position' => 1,
        'designation' => 'Pièce découpée',
        'quantity' => 2,
        'unit' => 'u',
        'unit_price' => 100,
        'discount_percent' => 10,
    ]);

    $quote->refresh();

    expect((float) $line->total_ht)->toBe(180.0)
        ->and((float) $quote->amount_ht)->toBe(180.0)
        ->and((float) $quote->amount_ttc)->toBe(216.0);
});

test('un changement de statut notifie le propriétaire par base et par e-mail', function (): void {
    Notification::fake();
    Mail::fake();

    $owner = quoteOwner();

    $quote = Quote::factory()->create([
        'owner_id' => $owner->id,
        'status' => QuoteDocumentStatus::Draft,
    ]);

    $quote->transitionTo(QuoteDocumentStatus::Sent);

    Notification::assertSentTo($owner, QuoteStatusChanged::class);
    Mail::assertSent(QuoteStatusChangedMail::class);
});

test('une modification sans changement de statut n’envoie pas de notification', function (): void {
    Notification::fake();
    Mail::fake();

    $owner = quoteOwner();

    $quote = Quote::factory()->create([
        'owner_id' => $owner->id,
        'status' => QuoteDocumentStatus::Draft,
    ]);

    $quote->update(['subject' => 'Objet modifié']);

    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

test('la commande quotes:expire bascule les devis périmés', function (): void {
    $owner = quoteOwner();

    $expired = Quote::factory()->create([
        'owner_id' => $owner->id,
        'status' => QuoteDocumentStatus::Sent,
        'valid_until' => now()->subDay(),
    ]);

    $valid = Quote::factory()->create([
        'owner_id' => $owner->id,
        'status' => QuoteDocumentStatus::Sent,
        'valid_until' => now()->addWeek(),
    ]);

    $this->artisan('quotes:expire')->assertSuccessful();

    expect($expired->refresh()->status)->toBe(QuoteDocumentStatus::Expired)
        ->and($valid->refresh()->status)->toBe(QuoteDocumentStatus::Sent);
});
