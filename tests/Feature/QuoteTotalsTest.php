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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function quoteTotalsLine(array $overrides = []): array
{
    return [
        'position' => 1,
        'designation' => 'Ligne test',
        'quantity' => 1,
        'unit' => 'u',
        'unit_price' => 100,
        'discount_percent' => 0,
        ...$overrides,
    ];
}

test('la création d’un devis sans ligne enregistre des totaux à zéro', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateQuote::class)
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis sans ligne',
            'vat_rate' => 20,
            'lines' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('subject', 'Devis sans ligne')->sole();

    expect((float) $quote->amount_ht)->toBe(0.0)
        ->and((float) $quote->amount_ttc)->toBe(0.0)
        ->and($quote->lines)->toHaveCount(0);
});

test('une ligne à quantité nulle ne contribue pas aux totaux', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateQuote::class)
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis quantité nulle',
            'vat_rate' => 20,
            'lines' => [quoteTotalsLine(['quantity' => 0])],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('subject', 'Devis quantité nulle')->sole();

    expect((float) $quote->amount_ht)->toBe(0.0)
        ->and((float) $quote->amount_ttc)->toBe(0.0)
        ->and((float) $quote->lines()->sole()->total_ht)->toBe(0.0);
});

test('une remise de 100 % ramène la ligne et le devis à zéro', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateQuote::class)
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis remise totale',
            'vat_rate' => 20,
            'lines' => [quoteTotalsLine(['discount_percent' => 100])],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('subject', 'Devis remise totale')->sole();

    expect((float) $quote->lines()->sole()->total_ht)->toBe(0.0)
        ->and((float) $quote->amount_ht)->toBe(0.0)
        ->and((float) $quote->amount_ttc)->toBe(0.0);
});

test('le formulaire refuse une remise supérieure à 100 %', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    $component = Livewire::actingAs($admin)->test(CreateQuote::class);

    $component->fillForm([
        'crm_account_id' => $account->id,
        'subject' => 'Devis remise invalide',
        'vat_rate' => 20,
        'lines' => [quoteTotalsLine(['discount_percent' => 120])],
    ]);

    $key = array_key_first($component->get('data.lines'));

    $component
        ->call('create')
        ->assertHasFormErrors(['lines.'.$key.'.discount_percent' => 'max']);

    expect(Quote::query()->where('subject', 'Devis remise invalide')->exists())->toBeFalse();
});

test('le formulaire refuse une remise négative', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    $component = Livewire::actingAs($admin)->test(CreateQuote::class);

    $component->fillForm([
        'crm_account_id' => $account->id,
        'subject' => 'Devis remise négative',
        'vat_rate' => 20,
        'lines' => [quoteTotalsLine(['discount_percent' => -1])],
    ]);

    $key = array_key_first($component->get('data.lines'));

    $component
        ->call('create')
        ->assertHasFormErrors(['lines.'.$key.'.discount_percent' => 'min']);

    expect(Quote::query()->where('subject', 'Devis remise négative')->exists())->toBeFalse();
});

test('le formulaire refuse un prix unitaire négatif', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    $component = Livewire::actingAs($admin)->test(CreateQuote::class);

    $component->fillForm([
        'crm_account_id' => $account->id,
        'subject' => 'Devis prix négatif',
        'vat_rate' => 20,
        'lines' => [quoteTotalsLine(['unit_price' => -10])],
    ]);

    $key = array_key_first($component->get('data.lines'));

    $component
        ->call('create')
        ->assertHasFormErrors(['lines.'.$key.'.unit_price' => 'min']);

    expect(Quote::query()->where('subject', 'Devis prix négatif')->exists())->toBeFalse();
});

test('le formulaire refuse une quantité négative', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    $component = Livewire::actingAs($admin)->test(CreateQuote::class);

    $component->fillForm([
        'crm_account_id' => $account->id,
        'subject' => 'Devis quantité négative',
        'vat_rate' => 20,
        'lines' => [quoteTotalsLine(['quantity' => -1])],
    ]);

    $key = array_key_first($component->get('data.lines'));

    $component
        ->call('create')
        ->assertHasFormErrors(['lines.'.$key.'.quantity' => 'min']);

    expect(Quote::query()->where('subject', 'Devis quantité négative')->exists())->toBeFalse();
});

test('le formulaire refuse un taux de TVA invalide', function (int $vatRate, string $rule): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    $component = Livewire::actingAs($admin)->test(CreateQuote::class);

    $component
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis TVA invalide',
            'vat_rate' => $vatRate,
            'lines' => [quoteTotalsLine()],
        ])
        ->call('create')
        ->assertHasFormErrors(['vat_rate' => $rule]);

    expect(Quote::query()->where('subject', 'Devis TVA invalide')->exists())->toBeFalse();
})->with([
    'négatif' => [-1, 'min'],
    'supérieur à 100' => [101, 'max'],
]);

test('le calcul cumule plusieurs lignes décimales', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateQuote::class)
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis décimal',
            'vat_rate' => 20,
            'lines' => [
                quoteTotalsLine(['position' => 1, 'quantity' => 2.4, 'unit_price' => 19.99]),
                quoteTotalsLine(['position' => 2, 'quantity' => 1.25, 'unit_price' => 40, 'discount_percent' => 12.5]),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('subject', 'Devis décimal')->sole();

    expect((float) $quote->amount_ht)->toBe(91.73)
        ->and((float) $quote->amount_ttc)->toBe(110.08);
});

test('les arrondis HT et TTC sont appliqués à deux décimales', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateQuote::class)
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis arrondis',
            'vat_rate' => 20,
            'lines' => [
                quoteTotalsLine(['position' => 1, 'quantity' => 3, 'unit_price' => 10.01, 'discount_percent' => 5.55]),
                quoteTotalsLine(['position' => 2, 'quantity' => 7, 'unit_price' => 0.07]),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('subject', 'Devis arrondis')->sole();

    expect((float) $quote->lines()->orderBy('position')->first()->total_ht)->toBe(28.36)
        ->and((float) $quote->amount_ht)->toBe(28.85)
        ->and((float) $quote->amount_ttc)->toBe(34.62);
});

test('le total TTC suit une modification du taux de TVA', function (): void {
    $quote = quoteWithLine(2, 100, 0, 20);

    expect((float) $quote->amount_ttc)->toBe(240.0);

    $quote->update(['vat_rate' => 5.5]);

    expect((float) $quote->refresh()->amount_ht)->toBe(200.0)
        ->and((float) $quote->amount_ttc)->toBe(211.0);
});

test('les champs financiers vides sont enregistrés à zéro', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateQuote::class)
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis champs vides',
            'vat_rate' => null,
            'lines' => [
                quoteTotalsLine([
                    'quantity' => null,
                    'unit_price' => null,
                    'discount_percent' => null,
                ]),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('subject', 'Devis champs vides')->sole();
    $line = $quote->lines()->sole();

    expect((float) $quote->vat_rate)->toBe(0.0)
        ->and((float) $quote->amount_ht)->toBe(0.0)
        ->and((float) $quote->amount_ttc)->toBe(0.0)
        ->and((float) $line->quantity)->toBe(0.0)
        ->and((float) $line->unit_price)->toBe(0.0)
        ->and((float) $line->discount_percent)->toBe(0.0)
        ->and((float) $line->total_ht)->toBe(0.0);
});

test('les montants manipulés côté client sont ignorés à la création', function (): void {
    $admin = quoteTotalsAdmin();
    $account = CrmAccount::factory()->create();

    $component = Livewire::actingAs($admin)->test(CreateQuote::class);

    $component
        ->fillForm([
            'crm_account_id' => $account->id,
            'subject' => 'Devis création manipulée',
            'vat_rate' => 20,
            'lines' => [quoteTotalsLine(['quantity' => 2, 'unit_price' => 100])],
        ])
        ->set('data.amount_ht', 9999)
        ->set('data.amount_ttc', 9999)
        ->call('create')
        ->assertHasNoFormErrors();

    $quote = Quote::query()->where('subject', 'Devis création manipulée')->sole();

    expect((float) $quote->amount_ht)->toBe(200.0)
        ->and((float) $quote->amount_ttc)->toBe(240.0)
        ->and((float) $quote->lines()->sole()->total_ht)->toBe(200.0);
});

test('les montants manipulés côté client sont ignorés à la modification', function (): void {
    $admin = quoteTotalsAdmin();
    $quote = quoteWithLine(2, 100, 0, 20);

    $component = Livewire::actingAs($admin)->test(EditQuote::class, [
        'record' => $quote->getKey(),
    ]);

    $key = array_key_first($component->get('data.lines'));

    $component
        ->set('data.amount_ht', 9999)
        ->set('data.amount_ttc', 9999)
        ->set('data.lines.'.$key.'.total_ht', 9999)
        ->call('save')
        ->assertHasNoFormErrors();

    $quote->refresh();

    expect((float) $quote->amount_ht)->toBe(200.0)
        ->and((float) $quote->amount_ttc)->toBe(240.0)
        ->and((float) $quote->lines()->sole()->total_ht)->toBe(200.0);
});

test('un enregistrement silencieux de ligne laisse les totaux à réparer par un enregistrement normal', function (): void {
    $quote = quoteWithLine();
    $line = $quote->lines()->sole();

    $line->updateQuietly(['quantity' => 3]);

    expect((float) $quote->refresh()->amount_ht)->toBe(200.0);

    $line->refresh()->save();

    expect((float) $quote->refresh()->amount_ht)->toBe(300.0)
        ->and((float) $quote->amount_ttc)->toBe(360.0);
});

test('le recalcul explicite répare des totaux désynchronisés', function (): void {
    $quote = quoteWithLine();
    $quote->updateQuietly(['amount_ht' => 0, 'amount_ttc' => 0]);

    $quote->recalculateTotals();

    expect((float) $quote->refresh()->amount_ht)->toBe(200.0)
        ->and((float) $quote->amount_ttc)->toBe(240.0);
});

test('une modification sans effet sur les montants ne réenregistre pas le devis', function (): void {
    $quote = quoteWithLine();
    $updatedAt = $quote->updated_at;

    $this->travel(5)->minutes();

    $quote->lines()->sole()->update(['designation' => 'Nouvelle désignation']);

    expect($quote->refresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});
