<?php

namespace Tests\Feature;

use App\Enums\CrmBook;
use App\Enums\CrmTagColor;
use App\Enums\UserRole;
use App\Filament\Resources\PrescriberAccounts\Pages\EditPrescriberAccount;
use App\Filament\Resources\PrescriberAccounts\Pages\ListPrescriberAccounts;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\CrmAccount;
use App\Models\CrmTag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function tagAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function tagCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

function tagAccount(?User $owner = null): CrmAccount
{
    return CrmAccount::factory()->forBook(CrmBook::Prescribers)->create([
        'owner_id' => $owner?->id,
        'phone' => '01 23 45 67 89',
    ]);
}

/**
 * @param  Testable<Component>  $component
 */
function tagDownloadedCsv(Testable $component): string
{
    $encoded = data_get($component->__get('effects'), 'download.content');

    expect($encoded)->toBeString();

    return base64_decode((string) $encoded);
}

test('la page des étiquettes s’ouvre pour l’administrateur', function (): void {
    $this->actingAs(tagAdmin())
        ->get('/admin/tags')
        ->assertOk()
        ->assertSee('Étiquettes');
});

test('la page des étiquettes s’ouvre pour le commercial', function (): void {
    $this->actingAs(tagCommercial())
        ->get('/admin/tags')
        ->assertOk()
        ->assertSee('Étiquettes');
});

test('un visiteur non connecté est redirigé vers la connexion', function (): void {
    $this->get('/admin/tags')->assertRedirect();
});

test('l’entrée de navigation est visible dans le groupe CRM', function (): void {
    $this->actingAs(tagCommercial())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Étiquettes');
});

test('l’administrateur crée une étiquette avec sa couleur', function (): void {
    Livewire::actingAs(tagAdmin())
        ->test(CreateTag::class)
        ->fillForm([
            'name' => 'Chaud',
            'color' => CrmTagColor::Danger->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $tag = CrmTag::query()->sole();

    expect($tag->name)->toBe('Chaud')
        ->and($tag->slug)->toBe('chaud')
        ->and($tag->color)->toBe(CrmTagColor::Danger->value);
});

test('le commercial crée une étiquette', function (): void {
    Livewire::actingAs(tagCommercial())
        ->test(CreateTag::class)
        ->fillForm(['name' => 'Partenaire'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CrmTag::query()->sole()->name)->toBe('Partenaire');
});

test('un nom déjà utilisé est refusé même avec une casse ou des accents différents', function (string $existing, string $rejected): void {
    CrmTag::factory()->create(['name' => $existing]);

    Livewire::actingAs(tagAdmin())
        ->test(CreateTag::class)
        ->fillForm(['name' => $rejected])
        ->call('create')
        ->assertHasFormErrors(['name']);

    expect(CrmTag::query()->count())->toBe(1);
})->with([
    ['Chaud', 'chaud'],
    ['Chaud', 'CHAUD'],
    ['Été', 'Ete'],
    ['Grand compte', 'grand-compte'],
]);

test('un nom sans lettre ni chiffre est refusé', function (): void {
    Livewire::actingAs(tagAdmin())
        ->test(CreateTag::class)
        ->fillForm(['name' => '!!!'])
        ->call('create')
        ->assertHasFormErrors(['name']);

    expect(CrmTag::query()->count())->toBe(0);
});

test('renommer une étiquette met à jour son slug', function (): void {
    $tag = CrmTag::factory()->create(['name' => 'Chaud']);

    Livewire::actingAs(tagAdmin())
        ->test(EditTag::class, ['record' => $tag->getKey()])
        ->fillForm(['name' => 'Très chaud'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tag->refresh()->slug)->toBe('tres-chaud');
});

test('enregistrer sans toucher au nom conserve la couleur existante', function (): void {
    $tag = CrmTag::factory()->create([
        'name' => 'Chaud',
        'color' => CrmTagColor::Info->value,
    ]);

    Livewire::actingAs(tagAdmin())
        ->test(EditTag::class, ['record' => $tag->getKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($tag->refresh()->name)->toBe('Chaud')
        ->and($tag->color)->toBe(CrmTagColor::Info->value);
});

test('un renommage vers un nom déjà pris est refusé', function (): void {
    CrmTag::factory()->create(['name' => 'Chaud']);
    $other = CrmTag::factory()->create(['name' => 'Froid']);

    Livewire::actingAs(tagAdmin())
        ->test(EditTag::class, ['record' => $other->getKey()])
        ->fillForm(['name' => 'CHAUD'])
        ->call('save')
        ->assertHasFormErrors(['name']);

    expect($other->refresh()->name)->toBe('Froid');
});

test('le formulaire d’un compte crée une étiquette à la volée', function (): void {
    $admin = tagAdmin();
    $account = tagAccount($admin);

    Livewire::actingAs($admin)
        ->test(EditPrescriberAccount::class, ['record' => $account->getKey()])
        ->callFormComponentAction('tags', 'createOption', [
            'name' => 'Urgent',
            'color' => CrmTagColor::Warning->value,
        ])
        ->call('save')
        ->assertHasNoActionErrors();

    $tag = CrmTag::query()->where('slug', 'urgent')->sole();

    expect($account->refresh()->tags->pluck('id')->all())->toContain($tag->getKey())
        ->and($tag->color)->toBe(CrmTagColor::Warning->value);
});

test('fusionner des étiquettes déplace les comptes puis supprime les sources', function (): void {
    $target = CrmTag::factory()->create(['name' => 'Chaud']);
    $source = CrmTag::factory()->create(['name' => 'Chauds']);

    $onlySource = tagAccount();
    $onlySource->tags()->attach($source->getKey());

    $both = tagAccount();
    $both->tags()->attach([$target->getKey(), $source->getKey()]);

    Livewire::actingAs(tagAdmin())
        ->test(ListTags::class)
        ->callTableBulkAction('merge', [$source->getKey(), $target->getKey()], [
            'target' => $target->getKey(),
        ])
        ->assertHasNoTableBulkActionErrors();

    expect(CrmTag::query()->pluck('id')->all())->toBe([$target->getKey()])
        ->and($onlySource->refresh()->tags->pluck('id')->all())->toBe([$target->getKey()])
        ->and($both->refresh()->tags->pluck('id')->all())->toBe([$target->getKey()]);
});

test('fusionner ne duplique pas les associations d’un compte déjà étiqueté des deux côtés', function (): void {
    $target = CrmTag::factory()->create(['name' => 'Urgent']);
    $source = CrmTag::factory()->create(['name' => 'Urgents']);

    $account = tagAccount();
    $account->tags()->attach([$target->getKey(), $source->getKey()]);

    Livewire::actingAs(tagAdmin())
        ->test(ListTags::class)
        ->callTableBulkAction('merge', [$source->getKey(), $target->getKey()], [
            'target' => $target->getKey(),
        ])
        ->assertHasNoTableBulkActionErrors();

    expect($account->refresh()->tags()->count())->toBe(1);
});

test('la fusion est refusée quand l’étiquette cible n’est pas dans la sélection', function (): void {
    $target = CrmTag::factory()->create(['name' => 'Urgent']);
    $source = CrmTag::factory()->create(['name' => 'Urgents']);

    Livewire::actingAs(tagAdmin())
        ->test(ListTags::class)
        ->callTableBulkAction('merge', [$source->getKey()], [
            'target' => $target->getKey(),
        ])
        ->assertHasTableBulkActionErrors(['target']);

    expect(CrmTag::query()->count())->toBe(2);
});

test('un commercial ne peut pas supprimer une étiquette', function (): void {
    $commercial = tagCommercial();
    $admin = tagAdmin();
    $tag = CrmTag::factory()->create();

    expect(Gate::forUser($commercial)->denies('delete', $tag))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $tag))->toBeTrue();

    // Aucun tag n’est altéré en base par cette vérification.
    expect(CrmTag::query()->count())->toBe(1);
});

test('l’administrateur supprime une étiquette et détache ses comptes', function (): void {
    $tag = CrmTag::factory()->create(['name' => 'Chaud']);
    $account = tagAccount();
    $account->tags()->attach($tag->getKey());

    Livewire::actingAs(tagAdmin())
        ->test(ListTags::class)
        ->callTableAction('delete', $tag)
        ->assertHasNoTableActionErrors();

    expect(CrmTag::query()->count())->toBe(0)
        ->and($account->refresh()->tags()->count())->toBe(0);
});

test('le filtre par étiquette ne montre que les comptes étiquetés', function (): void {
    $tag = CrmTag::factory()->create(['name' => 'Chaud']);

    $tagged = tagAccount();
    $tagged->tags()->attach($tag->getKey());

    $untagged = tagAccount();

    Livewire::actingAs(tagAdmin())
        ->test(ListPrescriberAccounts::class)
        ->assertCanSeeTableRecords([$tagged, $untagged])
        ->filterTable('tags', [$tag->getKey()])
        ->assertCanSeeTableRecords([$tagged])
        ->assertCanNotSeeTableRecords([$untagged])
        ->assertCountTableRecords(1);
});

test('les étiquettes en doublon probable sont signalées dans la liste', function (): void {
    $original = CrmTag::factory()->create(['name' => 'Grand compte']);
    CrmTag::factory()->create(['name' => 'Grands comptes']);
    $isolated = CrmTag::factory()->create(['name' => 'Urgent']);

    Livewire::actingAs(tagAdmin())
        ->test(ListTags::class)
        ->assertTableColumnStateSet('duplicates', true, $original)
        ->assertTableColumnStateSet('duplicates', false, $isolated);
});

test('la comparaison de doublons ignore la casse, les accents, le pluriel et la ponctuation', function (): void {
    expect(CrmTag::areLikelyDuplicates(
        CrmTag::factory()->make(['name' => 'Grand compte']),
        CrmTag::factory()->make(['name' => 'grands-comptes']),
    ))->toBeTrue()
        ->and(CrmTag::areLikelyDuplicates(
            CrmTag::factory()->make(['name' => 'Laser']),
            CrmTag::factory()->make(['name' => 'Bureau']),
        ))->toBeFalse()
        ->and(CrmTag::areLikelyDuplicates(
            CrmTag::factory()->make(['name' => 'B2B']),
            CrmTag::factory()->make(['name' => 'B2C']),
        ))->toBeFalse();
});

test('le nettoyage fusionne les doublons détectés en conservant le plus ancien', function (): void {
    $first = CrmTag::factory()->create(['name' => 'Grand compte']);
    $second = CrmTag::factory()->create(['name' => 'Grands comptes']);

    $account = tagAccount();
    $account->tags()->attach($second->getKey());

    Livewire::actingAs(tagAdmin())
        ->test(ListTags::class)
        ->callAction('cleanupDuplicates')
        ->assertHasNoActionErrors();

    expect(CrmTag::query()->pluck('id')->all())->toBe([$first->getKey()])
        ->and($account->refresh()->tags->pluck('id')->all())->toBe([$first->getKey()]);
});

test('le nettoyage n’écrit rien quand aucun doublon n’est détecté', function (): void {
    CrmTag::factory()->create(['name' => 'Urgent']);
    CrmTag::factory()->create(['name' => 'Froid']);

    Livewire::actingAs(tagAdmin())
        ->test(ListTags::class)
        ->callAction('cleanupDuplicates')
        ->assertHasNoActionErrors();

    expect(CrmTag::query()->count())->toBe(2);
});

test('le nettoyage est accessible aux commerciaux car il fusionne sans détruire', function (): void {
    CrmTag::factory()->create(['name' => 'Grand compte']);
    CrmTag::factory()->create(['name' => 'Grands comptes']);

    Livewire::actingAs(tagCommercial())
        ->test(ListTags::class)
        ->assertActionVisible('cleanupDuplicates');

    expect(CrmTag::query()->count())->toBe(2);
});

test('l’infolist affiche les étiquettes en badges colorés', function (): void {
    $admin = tagAdmin();
    $account = tagAccount($admin);
    $account->tags()->attach(CrmTag::factory()->create([
        'name' => 'Chaud',
        'color' => CrmTagColor::Danger->value,
    ])->getKey());

    $this->actingAs($admin)
        ->get('/admin/prescriber-accounts/'.$account->getKey())
        ->assertOk()
        ->assertSee('Chaud');
});

test('l’export CSV contient les noms et les couleurs d’étiquettes', function (): void {
    $admin = tagAdmin();
    $account = tagAccount($admin);
    $account->tags()->attach(CrmTag::factory()->create([
        'name' => 'Chaud',
        'color' => CrmTagColor::Danger->value,
    ])->getKey());

    $component = Livewire::actingAs($admin)
        ->test(ListPrescriberAccounts::class)
        ->callAction('exportAccountsCsv');

    $csv = tagDownloadedCsv($component);

    expect($csv)->toContain('Étiquettes')
        ->toContain('Couleurs des étiquettes')
        ->toContain('Chaud')
        ->toContain(CrmTagColor::Danger->getLabel());
});
