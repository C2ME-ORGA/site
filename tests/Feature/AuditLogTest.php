<?php

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Enums\OpportunityStage;
use App\Enums\QuoteDocumentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CrmAccount;
use App\Models\CrmOpportunity;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\User;
use App\Services\Mail\MailboxMailer;
use Illuminate\Support\Facades\Mail;

function auditAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function auditCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

test('un changement de statut de devis est journalisé', function (): void {
    Mail::fake();

    $admin = auditAdmin();
    $quote = Quote::factory()->create(['status' => QuoteDocumentStatus::Draft]);

    $this->actingAs($admin);

    $quote->update(['status' => QuoteDocumentStatus::Sent]);

    $log = AuditLog::query()->sole();

    expect($log->event)->toBe(AuditEvent::Updated)
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->auditable_type)->toBe(Quote::class)
        ->and($log->auditable_type_label)->toBe('Devis')
        ->and($log->auditable_id)->toBe($quote->id)
        ->and($log->auditable_label)->toBe($quote->reference)
        ->and($log->oldValue('status'))->toBe('Brouillon')
        ->and($log->newValue('status'))->toBe('Envoyé');
});

test('un changement d’étape d’affaire est journalisé', function (): void {
    $admin = auditAdmin();
    $opportunity = CrmOpportunity::factory()->create(['stage' => OpportunityStage::Qualified]);

    $this->actingAs($admin);

    $opportunity->update([
        'stage' => OpportunityStage::Negotiation,
        'probability' => 70,
    ]);

    $log = AuditLog::query()->sole();

    expect($log->event)->toBe(AuditEvent::Updated)
        ->and($log->auditable_type)->toBe(CrmOpportunity::class)
        ->and($log->oldValue('stage'))->toBe('Qualifié')
        ->and($log->newValue('stage'))->toBe('Négociation')
        ->and($log->newValue('probability'))->toBe('70');
});

test('une modification non sensible n’est pas journalisée', function (): void {
    $admin = auditAdmin();
    $quote = Quote::factory()->create();

    $this->actingAs($admin);

    $quote->update(['subject' => 'Nouvel objet sans impact sur le statut']);

    expect(AuditLog::query()->count())->toBe(0);
});

test('une suppression et une restauration de compte sont journalisées', function (): void {
    $admin = auditAdmin();
    $account = CrmAccount::factory()->create();

    $this->actingAs($admin);

    $account->delete();

    $deleted = AuditLog::query()->where('event', AuditEvent::Deleted)->sole();

    expect($deleted->auditable_type)->toBe(CrmAccount::class)
        ->and($deleted->auditable_id)->toBe($account->id)
        ->and($deleted->auditable_label)->toBe($account->name);

    $account->restore();

    $restored = AuditLog::query()->where('event', AuditEvent::Restored)->sole();

    expect($restored->auditable_id)->toBe($account->id);
});

test('une suppression définitive n’est journalisée qu’une fois', function (): void {
    $admin = auditAdmin();
    $account = CrmAccount::factory()->create();

    $this->actingAs($admin);

    $account->forceDelete();

    expect(AuditLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->sole()->event)->toBe(AuditEvent::ForceDeleted);
});

test('un changement de rôle utilisateur est journalisé', function (): void {
    $admin = auditAdmin();
    $target = User::factory()->create(['role' => UserRole::Commercial]);

    $this->actingAs($admin);

    $target->update(['role' => UserRole::Admin]);

    $log = AuditLog::query()->sole();

    expect($log->auditable_type)->toBe(User::class)
        ->and($log->oldValue('role'))->toBe('Commercial')
        ->and($log->newValue('role'))->toBe('Administrateur principal');
});

test('un envoi d’e-mail est journalisé', function (): void {
    Mail::fake();

    $commercial = auditCommercial();
    $mailbox = EmailMailbox::factory()->create();

    $this->actingAs($commercial);

    app(MailboxMailer::class)->send(
        mailbox: $mailbox,
        to: ['client@example.test'],
        subject: 'Proposition commerciale',
        html: '<p>Bonjour</p>',
        cc: ['copie@example.test'],
    );

    $log = AuditLog::query()->sole();

    expect($log->event)->toBe(AuditEvent::Sent)
        ->and($log->user_id)->toBe($commercial->id)
        ->and($log->auditable_type)->toBe(EmailMessage::class)
        ->and($log->auditable_label)->toBe('Proposition commerciale')
        ->and($log->newValue('destinataires'))->toBe('client@example.test')
        ->and($log->newValue('copie'))->toBe('copie@example.test')
        ->and($log->newValue('objet'))->toBe('Proposition commerciale');
});

test('un administrateur consulte le journal d’audit', function (): void {
    Mail::fake();

    $admin = auditAdmin();
    $quote = Quote::factory()->create();

    $this->actingAs($admin);
    $quote->update(['status' => QuoteDocumentStatus::Sent]);

    $this->actingAs($admin)
        ->get('/admin/journal-audit')
        ->assertOk()
        ->assertSee('Journal d’audit')
        ->assertSee($quote->reference)
        ->assertSee('Modification');
});

test('l’entrée de navigation est visible pour l’administrateur', function (): void {
    $this->actingAs(auditAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Journal d’audit');
});

test('l’entrée de navigation est masquée pour le commercial', function (): void {
    $this->actingAs(auditCommercial())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('Journal d’audit');
});

test('un commercial n’accède pas au journal d’audit', function (): void {
    $this->actingAs(auditCommercial())
        ->get('/admin/journal-audit')
        ->assertForbidden();
});

test('un visiteur non connecté est redirigé vers la connexion', function (): void {
    $this->get('/admin/journal-audit')
        ->assertRedirect();
});
