<?php

use App\Enums\AccountStatus;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\EmailMessage;
use App\Models\Quote;
use Illuminate\Support\Facades\Storage;

test('les prospects inactifs sont anonymisés avec leurs contacts', function (): void {
    $account = CrmAccount::factory()->create([
        'last_contacted_at' => now()->subMonths(40),
    ]);

    $contact = CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'email' => 'jean@usine.fr',
        'phone' => '0102030405',
    ]);

    artisanCommand($this, 'crm:anonymize-prospects')->assertSuccessful();

    $account->refresh();
    $contact->refresh();

    expect($account->email)->toBeNull()
        ->and($account->phone)->toBeNull()
        ->and($account->name)->toBe('Prospect anonymisé #'.$account->id)
        ->and($account->status)->toBe(AccountStatus::Inactive)
        ->and($account->metadata)->toBeNull()
        ->and($contact->email)->toBeNull()
        ->and($contact->last_name)->toBe('anonymisé')
        ->and($contact->do_not_contact)->toBeTrue();
});

test('les prospects récents ou ayant un devis sont conservés', function (): void {
    $recent = CrmAccount::factory()->create([
        'last_contacted_at' => now()->subMonths(2),
    ]);

    $withQuote = CrmAccount::factory()->create([
        'last_contacted_at' => now()->subMonths(40),
    ]);

    Quote::factory()->create(['crm_account_id' => $withQuote->id]);

    artisanCommand($this, 'crm:anonymize-prospects')->assertSuccessful();

    expect($recent->refresh()->email)->not->toBeNull()
        ->and($withQuote->refresh()->email)->not->toBeNull();
});

test('les activités trop anciennes sont supprimées', function (): void {
    $old = CrmActivity::factory()->create(['occurred_at' => now()->subMonths(70)]);
    $recent = CrmActivity::factory()->create(['occurred_at' => now()->subMonths(6)]);

    artisanCommand($this, 'crm:purge-activities')->assertSuccessful();

    expect(CrmActivity::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(CrmActivity::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('les e-mails expirés et leurs pièces jointes sont supprimés', function (): void {
    Storage::fake('local');

    $old = EmailMessage::factory()->create(['received_at' => now()->subMonths(40)]);
    $recent = EmailMessage::factory()->create(['received_at' => now()->subMonths(2)]);

    Storage::disk('local')->put('mail-attachments/inbound/1/plan.pdf', 'contenu');

    $old->attachments()->create([
        'filename' => 'plan.pdf',
        'mime_type' => 'application/pdf',
        'size' => 7,
        'path' => 'mail-attachments/inbound/1/plan.pdf',
    ]);

    artisanCommand($this, 'crm:purge-emails')->assertSuccessful();

    expect(EmailMessage::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(EmailMessage::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists('mail-attachments/inbound/1/plan.pdf'))->toBeFalse();
});
