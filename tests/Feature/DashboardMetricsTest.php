<?php

namespace Tests\Feature;

use App\Enums\CrmBook;
use App\Enums\OpportunityStage;
use App\Enums\QuoteDocumentStatus;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmOpportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\DashboardPeriod;
use Carbon\CarbonImmutable;

function dashboardAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function dashboardCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

function dashboardAccount(?User $owner = null, CrmBook $book = CrmBook::Prescribers): CrmAccount
{
    return CrmAccount::factory()
        ->forBook($book)
        ->create(['owner_id' => $owner?->id]);
}

function dashboardQuote(
    CrmAccount $account,
    QuoteDocumentStatus $status,
    string $issuedAt,
    ?string $sentAt = null,
    ?string $acceptedAt = null,
    float $amount = 10000,
): Quote {
    return Quote::factory()->create([
        'crm_account_id' => $account->id,
        'owner_id' => $account->owner_id,
        'status' => $status,
        'issued_at' => $issuedAt,
        'sent_at' => $sentAt,
        'accepted_at' => $acceptedAt,
        'amount_ht' => $amount,
        'valid_until' => CarbonImmutable::parse($issuedAt)->addDays(7),
    ]);
}

function dashboardOpportunity(
    CrmAccount $account,
    OpportunityStage $stage,
    float $amount,
    ?int $probability = null,
    ?string $wonAt = null,
): CrmOpportunity {
    return CrmOpportunity::factory()->create([
        'crm_account_id' => $account->id,
        'owner_id' => $account->owner_id,
        'stage' => $stage,
        'amount_estimated' => $amount,
        'probability' => $probability,
        'won_at' => $wonAt,
        'expected_close_date' => '2026-12-31',
    ]);
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-22 10:00:00'));
});

test('le service calcule le CA devis et le CA affaires sur la période', function (): void {
    $admin = dashboardAdmin();
    $account = dashboardAccount($admin);
    $period = DashboardPeriod::year(2026);

    dashboardQuote($account, QuoteDocumentStatus::Accepted, '2026-03-01', '2026-03-02', '2026-03-15', 12000);
    dashboardQuote($account, QuoteDocumentStatus::Sent, '2026-04-01', '2026-04-02', null, 8000);
    dashboardQuote($account, QuoteDocumentStatus::Accepted, '2025-03-01', '2025-03-02', '2025-03-15', 99999);

    dashboardOpportunity($account, OpportunityStage::Won, 25000, 100, '2026-04-10');
    dashboardOpportunity($account, OpportunityStage::New, 50000, 10);

    $service = app(DashboardMetricsService::class);

    expect($service->signedQuoteRevenue($admin, $period))->toBe(12000.0)
        ->and($service->acceptedQuotesCount($admin, $period))->toBe(1)
        ->and($service->wonOpportunityRevenue($admin, $period))->toBe(25000.0)
        ->and($service->wonOpportunitiesCount($admin, $period))->toBe(1);
});

test('le taux de conversion porte sur la cohorte des devis envoyés', function (): void {
    $admin = dashboardAdmin();
    $account = dashboardAccount($admin);
    $period = DashboardPeriod::year(2026);

    dashboardQuote($account, QuoteDocumentStatus::Accepted, '2026-01-01', '2026-01-02', '2026-01-10');
    dashboardQuote($account, QuoteDocumentStatus::Sent, '2026-02-01', '2026-02-02');
    dashboardQuote($account, QuoteDocumentStatus::Refused, '2026-03-01', '2026-03-02');
    dashboardQuote($account, QuoteDocumentStatus::Accepted, '2025-12-01', '2025-12-02', '2026-01-05');

    $service = app(DashboardMetricsService::class);

    expect($service->sentQuotesCount($admin, $period))->toBe(3)
        ->and($service->quoteConversionRate($admin, $period))->toBe(33.33333333333333);
});

test('les paniers moyens et les cas vides sont gérés sans division par zéro', function (): void {
    $admin = dashboardAdmin();
    $account = dashboardAccount($admin);
    $period = DashboardPeriod::year(2026);

    $service = app(DashboardMetricsService::class);

    expect($service->quoteConversionRate($admin, $period))->toBeNull()
        ->and($service->averageSignedQuote($admin, $period))->toBeNull()
        ->and($service->averageWonOpportunity($admin, $period))->toBeNull();

    dashboardQuote($account, QuoteDocumentStatus::Accepted, '2026-01-01', '2026-01-02', '2026-01-10', 10000);
    dashboardOpportunity($account, OpportunityStage::Won, 30000, 100, '2026-02-01');

    expect($service->averageSignedQuote($admin, $period))->toBe(10000.0)
        ->and($service->averageWonOpportunity($admin, $period))->toBe(30000.0);
});

test('le pipeline est agrégé par carnet en brut et pondéré', function (): void {
    $admin = dashboardAdmin();

    $prescribers = dashboardAccount($admin, CrmBook::Prescribers);
    $industrial = dashboardAccount($admin, CrmBook::IndustrialClients);
    $building = dashboardAccount($admin, CrmBook::BuildingCompanies);

    dashboardOpportunity($prescribers, OpportunityStage::New, 1000, 10);
    dashboardOpportunity($prescribers, OpportunityStage::Qualified, 2000, 25);
    dashboardOpportunity($industrial, OpportunityStage::Study, 3000, 50);
    dashboardOpportunity($building, OpportunityStage::QuoteSent, 4000, null);
    dashboardOpportunity($building, OpportunityStage::Won, 9999, 100, '2026-02-01');

    $rows = collect(app(DashboardMetricsService::class)->pipelineByBook($admin))->keyBy(
        fn (array $row): string => $row['book']->value,
    );

    expect($rows[CrmBook::Prescribers->value]['raw'])->toBe(3000.0)
        ->and($rows[CrmBook::Prescribers->value]['weighted'])->toBe(600.0)
        ->and($rows[CrmBook::Prescribers->value]['count'])->toBe(2)
        ->and($rows[CrmBook::IndustrialClients->value]['raw'])->toBe(3000.0)
        ->and($rows[CrmBook::IndustrialClients->value]['weighted'])->toBe(1500.0)
        ->and($rows[CrmBook::BuildingCompanies->value]['raw'])->toBe(4000.0)
        ->and($rows[CrmBook::BuildingCompanies->value]['weighted'])->toBe(2400.0);
});

test('le service applique le scoping commercial sur les devis et affaires', function (): void {
    $admin = dashboardAdmin();
    $commercial = dashboardCommercial();
    $other = dashboardCommercial();

    $ownAccount = dashboardAccount($commercial);
    $otherAccount = dashboardAccount($other);

    dashboardQuote($ownAccount, QuoteDocumentStatus::Accepted, '2026-01-01', '2026-01-02', '2026-01-10', 10000);
    dashboardQuote($otherAccount, QuoteDocumentStatus::Accepted, '2026-01-01', '2026-01-02', '2026-01-10', 90000);
    dashboardOpportunity($ownAccount, OpportunityStage::Won, 20000, 100, '2026-02-01');
    dashboardOpportunity($otherAccount, OpportunityStage::Won, 80000, 100, '2026-02-01');

    $period = DashboardPeriod::year(2026);
    $service = app(DashboardMetricsService::class);

    expect($service->signedQuoteRevenue($commercial, $period))->toBe(10000.0)
        ->and($service->wonOpportunityRevenue($commercial, $period))->toBe(20000.0)
        ->and($service->signedQuoteRevenue($admin, $period))->toBe(100000.0)
        ->and($service->wonOpportunityRevenue($admin, $period))->toBe(100000.0);
});

test('le top 5 et les devis à relancer sont filtrés et triés', function (): void {
    $admin = dashboardAdmin();
    $account = dashboardAccount($admin);

    foreach ([1000, 5000, 3000, 4000, 2000, 9000] as $amount) {
        dashboardOpportunity($account, OpportunityStage::New, $amount, 10);
    }

    dashboardQuote($account, QuoteDocumentStatus::Sent, '2026-10-20', '2026-10-20', null, 1000);
    dashboardQuote($account, QuoteDocumentStatus::Expired, '2026-09-01', '2026-09-01', null, 5000);
    dashboardQuote($account, QuoteDocumentStatus::Sent, '2026-12-01', '2026-12-01', null, 2000);

    $service = app(DashboardMetricsService::class);
    $top = $service->topOpenOpportunities($admin);
    $followUps = $service->quotesToFollowUp($admin);

    expect($top)->toHaveCount(5)
        ->and($top->first()->amount_estimated)->toBe('9000.00')
        ->and($followUps)->toHaveCount(2)
        ->and($followUps->pluck('amount_ht')->all())->toBe(['5000.00', '1000.00']);
});

test('les enregistrements supprimés logiquement sont exclus', function (): void {
    $admin = dashboardAdmin();
    $account = dashboardAccount($admin);
    $period = DashboardPeriod::year(2026);

    $quote = dashboardQuote($account, QuoteDocumentStatus::Accepted, '2026-01-01', '2026-01-02', '2026-01-10', 10000);
    $opportunity = dashboardOpportunity($account, OpportunityStage::Won, 20000, 100, '2026-02-01');

    $quote->delete();
    $opportunity->delete();

    $service = app(DashboardMetricsService::class);

    expect($service->signedQuoteRevenue($admin, $period))->toBe(0.0)
        ->and($service->wonOpportunityRevenue($admin, $period))->toBe(0.0);
});

test('l’objectif mensuel calcule la progression et le reste à faire', function (): void {
    $admin = dashboardAdmin();
    $account = dashboardAccount($admin);

    config()->set('crm.dashboard.monthly_revenue_target', 5000);

    dashboardQuote($account, QuoteDocumentStatus::Accepted, '2026-10-01', '2026-10-02', '2026-10-10', 6000);

    $data = app(DashboardMetricsService::class)->monthlyTargetProgress($admin);

    expect($data['revenue'])->toBe(6000.0)
        ->and($data['target'])->toBe(5000.0)
        ->and($data['progress'])->toBe(120.0)
        ->and($data['remaining'])->toBe(0.0);

    config()->set('crm.dashboard.monthly_revenue_target', 0);

    $data = app(DashboardMetricsService::class)->monthlyTargetProgress($admin);

    expect($data['progress'])->toBeNull();
});

test('la période du tableau de bord est construite depuis les filtres', function (): void {
    $month = DashboardPeriod::fromFilters([
        'period' => 'month',
        'year' => 2026,
        'month' => 2,
    ]);

    expect($month->start->toDateString())->toBe('2026-02-01')
        ->and($month->end->toDateString())->toBe('2026-02-28')
        ->and($month->label)->toBe('Février 2026');

    $quarter = DashboardPeriod::fromFilters([
        'period' => 'quarter',
        'year' => 2026,
        'quarter' => 4,
    ]);

    expect($quarter->start->toDateString())->toBe('2026-10-01')
        ->and($quarter->end->toDateString())->toBe('2026-12-31')
        ->and($quarter->label)->toBe('T4 2026');

    $custom = DashboardPeriod::fromFilters([
        'period' => 'custom',
        'from' => '2026-03-01',
        'to' => '2026-03-31',
    ]);

    expect($custom->start->toDateString())->toBe('2026-03-01')
        ->and($custom->end->toDateString())->toBe('2026-03-31')
        ->and($custom->label)->toBe('01/03/2026 → 31/03/2026');

    $fallback = DashboardPeriod::fromFilters([
        'period' => 'custom',
        'from' => null,
        'to' => null,
    ]);

    expect($fallback->start->toDateString())->toBe('2026-01-01')
        ->and($fallback->end->toDateString())->toBe('2026-12-31');
});
