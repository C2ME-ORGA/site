<?php

namespace Tests\Feature;

use App\Enums\CrmBook;
use App\Enums\OpportunityStage;
use App\Enums\QuoteDocumentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Accounts\RelationManagers\OpportunitiesRelationManager;
use App\Filament\Resources\PrescriberAccounts\Pages\ListPrescriberAccounts;
use App\Filament\Resources\PrescriberAccounts\Pages\ViewPrescriberAccount;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function exportAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function exportCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function exportAccount(CrmBook $book, User $owner, array $attributes = []): CrmAccount
{
    return CrmAccount::factory()
        ->forBook($book)
        ->create([
            'owner_id' => $owner->id,
            ...$attributes,
        ]);
}

/**
 * @template TComponent of \Livewire\Component
 *
 * @param  Testable<TComponent>  $component
 */
function downloadedCsv(Testable $component): string
{
    $encoded = data_get($component->__get('effects'), 'download.content');

    expect($encoded)->toBeString();

    return base64_decode((string) $encoded);
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 10:00:00'));
});

test('les comptes se téléchargent en CSV dans le carnet et avec les filtres actifs', function (): void {
    $admin = exportAdmin();

    exportAccount(CrmBook::Prescribers, $admin, [
        'code' => 'PRE-001',
        'name' => 'Alpha Prescripteur',
        'email' => 'alpha@example.fr',
        'city' => 'Nantes',
        'metadata' => ['specialty' => 'Bureau d’études'],
    ]);

    exportAccount(CrmBook::Prescribers, $admin, [
        'code' => 'PRE-002',
        'name' => 'Beta Prescripteur',
    ]);

    exportAccount(CrmBook::IndustrialClients, $admin, [
        'code' => 'IND-001',
        'name' => 'Alpha Industriel',
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListPrescriberAccounts::class)
        ->searchTable('Alpha Prescripteur')
        ->callAction('exportAccountsCsv')
        ->assertFileDownloaded('c2me-comptes-prescripteurs-2026-10-22.csv');

    $content = downloadedCsv($component);

    expect($content)
        ->toStartWith("\xEF\xBB\xBF")
        ->toContain('Code;')
        ->toContain('Spécialité')
        ->toContain('PRE-001')
        ->toContain('Alpha Prescripteur')
        ->toContain('alpha@example.fr')
        ->toContain('Bureau d’études')
        ->not->toContain('Beta Prescripteur')
        ->not->toContain('Alpha Industriel');
});

test('un commercial n’exporte que ses propres comptes', function (): void {
    $commercial = exportCommercial();
    $other = exportCommercial();

    exportAccount(CrmBook::Prescribers, $commercial, [
        'name' => 'Compte Du Commercial',
    ]);

    exportAccount(CrmBook::Prescribers, $other, [
        'name' => 'Compte Dun Autre Commercial',
    ]);

    $component = Livewire::actingAs($commercial)
        ->test(ListPrescriberAccounts::class)
        ->callAction('exportAccountsCsv');

    $content = downloadedCsv($component);

    expect($content)
        ->toContain('Compte Du Commercial')
        ->not->toContain('Compte Dun Autre Commercial');
});

test('les affaires se téléchargent en CSV pour les comptes filtrés du carnet', function (): void {
    $admin = exportAdmin();

    $alpha = exportAccount(CrmBook::Prescribers, $admin, [
        'code' => 'PRE-001',
        'name' => 'Alpha Prescripteur',
    ]);

    $beta = exportAccount(CrmBook::Prescribers, $admin, [
        'code' => 'PRE-002',
        'name' => 'Beta Prescripteur',
    ]);

    $industrial = exportAccount(CrmBook::IndustrialClients, $admin, [
        'code' => 'IND-001',
        'name' => 'Alpha Industriel',
    ]);

    CrmOpportunity::factory()->create([
        'crm_account_id' => $alpha->id,
        'title' => 'Affaire Alpha',
        'stage' => OpportunityStage::Negotiation,
        'amount_estimated' => 15000,
        'probability' => 75,
        'source' => 'site_web',
    ]);

    CrmOpportunity::factory()->create([
        'crm_account_id' => $beta->id,
        'title' => 'Affaire Beta',
        'stage' => OpportunityStage::New,
        'amount_estimated' => 5000,
    ]);

    CrmOpportunity::factory()->create([
        'crm_account_id' => $industrial->id,
        'title' => 'Affaire Industriel',
        'stage' => OpportunityStage::New,
        'amount_estimated' => 9000,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(ListPrescriberAccounts::class)
        ->searchTable('Alpha Prescripteur')
        ->callAction('exportOpportunitiesCsv')
        ->assertFileDownloaded('c2me-affaires-prescripteurs-2026-10-22.csv');

    $content = downloadedCsv($component);

    expect($content)
        ->toContain('Référence;')
        ->toContain('Affaire Alpha')
        ->toContain('15000,00')
        ->toContain('Site web')
        ->not->toContain('Affaire Beta')
        ->not->toContain('Affaire Industriel');
});

test('les affaires d’un compte se téléchargent depuis sa fiche', function (): void {
    $admin = exportAdmin();
    $account = exportAccount(CrmBook::Prescribers, $admin, [
        'code' => 'PRE-001',
        'name' => 'Alpha Prescripteur',
    ]);

    $opportunity = CrmOpportunity::factory()->create([
        'crm_account_id' => $account->id,
        'title' => 'Affaire Du Compte',
        'stage' => OpportunityStage::Study,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(OpportunitiesRelationManager::class, [
            'ownerRecord' => $account,
            'pageClass' => ViewPrescriberAccount::class,
        ]);

    $component
        ->assertActionExists(TestAction::make('print')->table($opportunity))
        ->assertActionHasUrl(
            TestAction::make('print')->table($opportunity),
            route('crm.print.opportunity', $opportunity),
        )
        ->callAction(TestAction::make('exportCsv')->table())
        ->assertFileDownloaded('c2me-affaires-PRE-001-2026-10-22.csv');

    expect(downloadedCsv($component))->toContain('Affaire Du Compte');
});

test('la fiche compte imprimable contient les coordonnées, contacts, affaires et devis', function (): void {
    $admin = exportAdmin();
    $account = exportAccount(CrmBook::Prescribers, $admin, [
        'code' => 'PRE-001',
        'name' => 'Alpha Prescripteur',
        'legal_name' => 'Alpha Prescripteur SARL',
        'email' => 'contact@alpha.fr',
        'notes' => 'Note importante pour la fiche.',
    ]);

    CrmContact::factory()->primary()->create([
        'crm_account_id' => $account->id,
        'first_name' => 'Camille',
        'last_name' => 'Martin',
        'email' => 'camille@alpha.fr',
    ]);

    CrmOpportunity::factory()->create([
        'crm_account_id' => $account->id,
        'title' => 'Affaire Imprimée',
        'stage' => OpportunityStage::Qualified,
        'amount_estimated' => 12000,
    ]);

    Quote::factory()->create([
        'crm_account_id' => $account->id,
        'reference' => 'DEV-2026-00123',
        'status' => QuoteDocumentStatus::Sent,
        'amount_ht' => 8000,
    ]);

    $this->actingAs($admin)
        ->get(route('crm.print.account', $account))
        ->assertOk()
        ->assertSee('Fiche compte')
        ->assertSee('Alpha Prescripteur')
        ->assertSee('Alpha Prescripteur SARL')
        ->assertSee('Camille Martin')
        ->assertSee('Affaire Imprimée')
        ->assertSee('DEV-2026-00123')
        ->assertSee('Note importante pour la fiche.');

    Livewire::actingAs($admin)
        ->test(ViewPrescriberAccount::class, ['record' => $account->getRouteKey()])
        ->assertActionExists('print')
        ->assertActionHasUrl('print', route('crm.print.account', $account))
        ->assertActionShouldOpenUrlInNewTab('print');
});

test('la fiche affaire imprimable contient le compte, le contact et les devis liés', function (): void {
    $admin = exportAdmin();
    $account = exportAccount(CrmBook::IndustrialClients, $admin, [
        'code' => 'IND-001',
        'name' => 'Compte Industriel',
    ]);

    $contact = CrmContact::factory()->primary()->create([
        'crm_account_id' => $account->id,
        'first_name' => 'Alex',
        'last_name' => 'Durand',
        'email' => 'alex@industriel.fr',
    ]);

    $opportunity = CrmOpportunity::factory()->create([
        'crm_account_id' => $account->id,
        'crm_contact_id' => $contact->id,
        'reference' => 'AFF-2026-00042',
        'title' => 'Affaire Imprimable',
        'stage' => OpportunityStage::QuoteSent,
        'amount_estimated' => 25000,
        'probability' => 60,
        'description' => '<p>Description de l’affaire.</p>',
    ]);

    Quote::factory()->create([
        'crm_account_id' => $account->id,
        'crm_opportunity_id' => $opportunity->id,
        'reference' => 'DEV-2026-00456',
        'status' => QuoteDocumentStatus::Sent,
        'amount_ht' => 25000,
    ]);

    $this->actingAs($admin)
        ->get(route('crm.print.opportunity', $opportunity))
        ->assertOk()
        ->assertSee('Fiche affaire')
        ->assertSee('AFF-2026-00042')
        ->assertSee('Affaire Imprimable')
        ->assertSee('Compte Industriel')
        ->assertSee('Alex Durand')
        ->assertSee('DEV-2026-00456')
        ->assertSee('Description de l’affaire.');
});

test('les fiches imprimables respectent le scoping commercial', function (): void {
    $commercial = exportCommercial();
    $other = exportCommercial();

    $ownAccount = exportAccount(CrmBook::Prescribers, $commercial);
    $otherAccount = exportAccount(CrmBook::Prescribers, $other);

    $ownOpportunity = CrmOpportunity::factory()->create([
        'crm_account_id' => $ownAccount->id,
        'owner_id' => $commercial->id,
    ]);

    $otherOpportunity = CrmOpportunity::factory()->create([
        'crm_account_id' => $otherAccount->id,
        'owner_id' => $other->id,
    ]);

    $this->actingAs($commercial)
        ->get(route('crm.print.account', $ownAccount))
        ->assertOk();

    $this->actingAs($commercial)
        ->get(route('crm.print.account', $otherAccount))
        ->assertForbidden();

    $this->actingAs($commercial)
        ->get(route('crm.print.opportunity', $ownOpportunity))
        ->assertOk();

    $this->actingAs($commercial)
        ->get(route('crm.print.opportunity', $otherOpportunity))
        ->assertForbidden();
});
