<?php

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Filament\Actions\ContactPrivacyActions;
use App\Filament\Resources\Accounts\RelationManagers\ContactsRelationManager;
use App\Filament\Resources\PrescriberAccounts\Pages\ViewPrescriberAccount;
use App\Models\AuditLog;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\User;
use App\Services\ContactPrivacyService;
use Livewire\Livewire;

test('l’export RGPD contient les données du contact et son historique', function (): void {
    $contact = CrmContact::factory()->create([
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'email' => 'jean.dupont@exemple.fr',
        'phone' => '06 12 34 56 78',
        'notes' => 'Souhaite être rappelé le matin.',
    ]);

    CrmActivity::factory()->create([
        'crm_contact_id' => $contact->id,
        'crm_account_id' => $contact->crm_account_id,
        'subject' => 'Appel de qualification',
    ]);

    $payload = app(ContactPrivacyService::class)->export($contact);

    expect($payload)->toHaveKeys(['exported_at', 'contact', 'account', 'activities', 'quotes', 'emails', 'opportunities'])
        ->and($payload['contact']['email'])->toBe('jean.dupont@exemple.fr')
        ->and($payload['contact']['phone'])->toBe('06 12 34 56 78')
        ->and($payload['account']['name'])->toBe($contact->account->name)
        ->and($payload['activities'])->toHaveCount(1)
        ->and($payload['activities'][0]['subject'])->toBe('Appel de qualification');
});

test('l’anonymisation efface les données identifiantes et conserve le rattachement', function (): void {
    $contact = CrmContact::factory()->create([
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
        'job_title' => 'Directeur',
        'email' => 'jean.dupont@exemple.fr',
        'phone' => '06 12 34 56 78',
        'mobile' => '07 00 00 00 00',
        'notes' => 'Note personnelle.',
    ]);

    $accountId = $contact->crm_account_id;

    app(ContactPrivacyService::class)->anonymize($contact);

    $contact->refresh();

    expect($contact->first_name)->toBe('Contact')
        ->and($contact->last_name)->toBe('anonymisé')
        ->and($contact->email)->toBeNull()
        ->and($contact->phone)->toBeNull()
        ->and($contact->mobile)->toBeNull()
        ->and($contact->job_title)->toBeNull()
        ->and($contact->notes)->toBeNull()
        ->and($contact->do_not_contact)->toBeTrue()
        ->and($contact->crm_account_id)->toBe($accountId);
});

test('l’anonymisation est journalisée sans conserver les données effacées', function (): void {
    $contact = CrmContact::factory()->create([
        'email' => 'jean.dupont@exemple.fr',
        'phone' => '06 12 34 56 78',
    ]);

    app(ContactPrivacyService::class)->anonymize($contact);

    $log = AuditLog::query()
        ->where('auditable_type', $contact->getMorphClass())
        ->where('auditable_id', $contact->getKey())
        ->where('event', AuditEvent::Anonymized->value)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->auditable_label)->toContain('anonymisé');

    /** @var array<string, mixed> $changes */
    $changes = (array) $log->changes;

    expect(json_encode($changes, JSON_THROW_ON_ERROR))
        ->not->toContain('jean.dupont@exemple.fr')
        ->not->toContain('06 12 34 56 78');
});

test('les actions RGPD sont réservées aux administrateurs', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $commercial = User::factory()->create(['role' => UserRole::Commercial]);

    $this->actingAs($admin);
    expect(ContactPrivacyActions::export()->isVisible())->toBeTrue()
        ->and(ContactPrivacyActions::anonymize()->isVisible())->toBeTrue();

    $this->actingAs($commercial);
    expect(ContactPrivacyActions::export()->isVisible())->toBeFalse()
        ->and(ContactPrivacyActions::anonymize()->isVisible())->toBeFalse();
});

test('la fiche compte expose les actions RGPD dans l’onglet Contacts', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $account = CrmAccount::factory()->create();
    $contact = CrmContact::factory()->create(['crm_account_id' => $account->id]);

    $this->actingAs($admin);

    Livewire::test(ContactsRelationManager::class, [
        'ownerRecord' => $account,
        'pageClass' => ViewPrescriberAccount::class,
    ])
        ->assertSuccessful()
        ->assertTableActionVisible('exportPersonalData', $contact)
        ->assertTableActionVisible('anonymize', $contact);
});
