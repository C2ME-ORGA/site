<?php

namespace Tests\Feature;

use App\Enums\CrmBook;
use App\Enums\OpportunityStage;
use App\Enums\QuoteDocumentStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CommercialStatsOverview;
use App\Filament\Widgets\MonthlyTargetOverview;
use App\Filament\Widgets\PipelineByBookChart;
use App\Filament\Widgets\QuotesToFollowUp;
use App\Filament\Widgets\TopOpenOpportunities;
use App\Models\CrmAccount;
use App\Models\CrmOpportunity;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function dashboardPageAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function dashboardPageCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

function dashboardPageAccount(User $owner, CrmBook $book = CrmBook::Prescribers): CrmAccount
{
    return CrmAccount::factory()
        ->forBook($book)
        ->create(['owner_id' => $owner->id]);
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 10:00:00'));
});

test('le tableau de bord expose le filtre de période et ses widgets', function (): void {
    $admin = dashboardPageAdmin();
    $account = dashboardPageAccount($admin, CrmBook::BuildingCompanies);

    $opportunity = CrmOpportunity::factory()->create([
        'crm_account_id' => $account->id,
        'owner_id' => $admin->id,
        'title' => 'Affaire Visible Dashboard',
        'stage' => OpportunityStage::Negotiation,
        'amount_estimated' => 45000,
        'expected_close_date' => '2026-12-15',
    ]);

    Quote::factory()->create([
        'crm_account_id' => $account->id,
        'owner_id' => $admin->id,
        'status' => QuoteDocumentStatus::Accepted,
        'issued_at' => '2026-10-01',
        'valid_until' => '2026-10-31',
        'sent_at' => '2026-10-02',
        'accepted_at' => '2026-10-10',
        'amount_ht' => 12000,
    ]);

    $quote = Quote::factory()->create([
        'crm_account_id' => $account->id,
        'owner_id' => $admin->id,
        'reference' => 'DEV-2026-00999',
        'status' => QuoteDocumentStatus::Sent,
        'issued_at' => '2026-10-20',
        'valid_until' => '2026-10-25',
        'sent_at' => '2026-10-20',
        'amount_ht' => 12000,
    ]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Période')
        ->assertSee('Affichage');

    Livewire::actingAs($admin)
        ->test(CommercialStatsOverview::class)
        ->assertSee('CA signé — devis')
        ->assertSee('12 000 €')
        ->assertSee('CA gagné — affaires')
        ->assertSee('Taux de conversion devis')
        ->assertSee('Panier moyen')
        ->assertSee('Année 2026');

    Livewire::actingAs($admin)
        ->test(PipelineByBookChart::class)
        ->assertSee('Pipeline par carnet');

    Livewire::actingAs($admin)
        ->test(TopOpenOpportunities::class)
        ->assertSee('Top 5 des affaires ouvertes')
        ->assertCanSeeTableRecords([$opportunity]);

    Livewire::actingAs($admin)
        ->test(QuotesToFollowUp::class)
        ->assertSee('Devis à relancer ou expirés')
        ->assertCanSeeTableRecords([$quote]);
});

test('les tableaux du tableau de bord appliquent le scoping commercial', function (): void {
    $admin = dashboardPageAdmin();
    $commercial = dashboardPageCommercial();
    $other = dashboardPageCommercial();

    $ownAccount = dashboardPageAccount($commercial);
    $otherAccount = dashboardPageAccount($other);

    $ownOpportunity = CrmOpportunity::factory()->create([
        'crm_account_id' => $ownAccount->id,
        'owner_id' => $commercial->id,
        'title' => 'Affaire Du Commercial',
        'stage' => OpportunityStage::New,
        'amount_estimated' => 10000,
    ]);

    $otherOpportunity = CrmOpportunity::factory()->create([
        'crm_account_id' => $otherAccount->id,
        'owner_id' => $other->id,
        'title' => 'Affaire Dun Autre Commercial',
        'stage' => OpportunityStage::New,
        'amount_estimated' => 90000,
    ]);

    Livewire::actingAs($commercial)
        ->test(TopOpenOpportunities::class)
        ->assertCanSeeTableRecords([$ownOpportunity])
        ->assertCanNotSeeTableRecords([$otherOpportunity]);

    Livewire::actingAs($admin)
        ->test(TopOpenOpportunities::class)
        ->assertCanSeeTableRecords([$ownOpportunity, $otherOpportunity]);
});

test('le filtre de période met à jour les indicateurs des widgets', function (): void {
    $admin = dashboardPageAdmin();

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSet('filters.period', 'year')
        ->assertSet('filters.year', '2026')
        ->set('filters', [
            'period' => 'year',
            'year' => '2025',
        ])
        ->assertSet('filters.year', '2025');
});

test('l’objectif mensuel est masqué sans configuration et affiché lorsqu’il est défini', function (): void {
    $admin = dashboardPageAdmin();
    $account = dashboardPageAccount($admin);

    config()->set('crm.dashboard.monthly_revenue_target', 0);

    Livewire::actingAs($admin)
        ->test(MonthlyTargetOverview::class)
        ->assertSee('Objectif mensuel')
        ->assertSee('Non défini');

    config()->set('crm.dashboard.monthly_revenue_target', 10000);

    Quote::factory()->create([
        'crm_account_id' => $account->id,
        'owner_id' => $admin->id,
        'status' => QuoteDocumentStatus::Accepted,
        'issued_at' => '2026-10-01',
        'valid_until' => '2026-10-31',
        'sent_at' => '2026-10-02',
        'accepted_at' => '2026-10-10',
        'amount_ht' => 4000,
    ]);

    Livewire::actingAs($admin)
        ->test(MonthlyTargetOverview::class)
        ->assertSee('Objectif mensuel — Octobre 2026')
        ->assertSee('4 000 € / 10 000 €')
        ->assertSee('40,0 % atteint')
        ->assertSee('reste 6 000 €');
});
