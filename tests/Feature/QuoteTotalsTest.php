<?php

use App\Enums\UserRole;
use App\Filament\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Models\CrmAccount;
use App\Models\Quote;
use App\Models\User;
use Livewire\Livewire;

function quoteTotalsAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function quoteWithLine(float $quantity = 2, float $unitPrice = 100, float $discount = 0, float $vat = 20): Quote
{
    $quote = Quote::factory()->create(['vat_rate' => $vat, 'amount_ht' => 0]);

    $quote->lines()->create([
        'position' => 1,
        'designation' => 'Ligne test',
        'quantity' => $quantity,
        'unit' => 'u',
        'unit_price' => $unitPrice,
        'discount_percent' => $discount,
    ]);

    return $quote->refresh();
}

test('les totaux du devis suivent les ajouts, modifications et suppressions de lignes', function (): void {
    $quote = quoteWithLine();

    expect((float) $quote->amount_ht)->toBe(200.0)
        ->and((float) $quote->amount_ttc)->toBe(240.0);

    $line = $quote->lines()->sole();
    $line->update(['quantity' => 3, 'discount_percent' => 10]);

    expect((float) $quote->refresh()->amount_ht)->toBe(270.0)
        ->and((float) $quote->amount_ttc)->toBe(324.0);

    $quote->lines()->create([
        'position' => 2,
        'designation' => 'Ligne ajoutée',
        'quantity' => 1,
        'unit' => 'u',
        'unit_price' => 50,
        'discount_percent' => 0,
    ]);

    expect((float) $quote->refresh()->amount_ht)->toBe(320.0)
        ->and((float) $quote->amount_ttc)->toBe(384.0);

    $line->delete();

    expect((float) $quote->refresh()->amount_ht)->toBe(50.0)
        ->and((float) $quote->amount_ttc)->toBe(60.0);
});

test('le formulaire affiche les totaux calculés au chargement', function (): void {
    $admin = quoteTotalsAdmin();
    $quote = quoteWithLine(2, 100, 0, 20);

    Livewire::actingAs($admin)
        ->test(EditQuote::class, ['record' => $quote->getKey()])
        ->assertSet('data.amount_ht', 200.0)
        ->assertSet('data.amount_ttc', 240.0)
        ->assertFormFieldDisabled('amount_ht')
        ->assertFormFieldDisabled('amount_ttc');
});

test('le formulaire recalcule le total HT et le total TTC à l’enregistrement', function (): void {
    $admin = quoteTotalsAdmin();
    $quote = quoteWithLine();

    $component = Livewire::actingAs($admin)->test(EditQuote::class, [
        'record' => $quote->getKey(),
    ]);

    $lines = $component->get('data.lines');
    $key = array_key_first($lines);

    $component
        ->set('data.lines.'.$key.'.quantity', 4)
        ->assertSet('data.amount_ht', 400.0)
        ->assertSet('data.amount_ttc', 480.0)
        ->set('data.vat_rate', 10)
        ->assertSet('data.amount_ttc', 440.0)
        ->call('save')
        ->assertHasNoFormErrors();

    $quote->refresh();

    expect((float) $quote->amount_ht)->toBe(400.0)
        ->and((float) $quote->amount_ttc)->toBe(440.0);
});

test('supprimer toutes les lignes dans le formulaire remet les totaux à zéro', function (): void {
    $admin = quoteTotalsAdmin();
    $quote = quoteWithLine();

    Livewire::actingAs($admin)
        ->test(EditQuote::class, ['record' => $quote->getKey()])
        ->set('data.lines', [])
        ->call('save')
        ->assertHasNoFormErrors();

    $quote->refresh();

    expect((float) $quote->amount_ht)->toBe(0.0)
        ->and((float) $quote->amount_ttc)->toBe(0.0);
});

test('la création d’un devis calcule les totaux depuis ses lignes', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateQuote::class)
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis avec lignes',
            'vat_rate' => 20,
            'lines' => [
                [
                    'position' => 1,
                    'designation' => 'Fourniture et pose',
                    'quantity' => 2,
                    'unit' => 'u',
                    'unit_price' => 150,
                    'discount_percent' => 0,
                ],
                [
                    'position' => 2,
                    'designation' => 'Transport',
                    'quantity' => 1,
                    'unit' => 'u',
                    'unit_price' => 50,
                    'discount_percent' => 0,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('subject', 'Devis avec lignes')->sole();

    expect((float) $quote->amount_ht)->toBe(350.0)
        ->and((float) $quote->amount_ttc)->toBe(420.0)
        ->and($quote->lines)->toHaveCount(2);
});
