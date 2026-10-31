<?php

namespace App\Services;

use App\Enums\CrmActivityType;
use App\Enums\CrmBook;
use App\Enums\OpportunityStage;
use App\Enums\QuoteDocumentStatus;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\Quote;
use App\Models\User;
use App\Support\DashboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class DashboardMetricsService
{
    public function signedQuoteRevenue(User $user, DashboardPeriod $period): float
    {
        return (float) $this->quotes($user)
            ->where('status', QuoteDocumentStatus::Accepted->value)
            ->whereBetween('accepted_at', [$period->start, $period->end])
            ->sum('amount_ht');
    }

    public function wonOpportunityRevenue(User $user, DashboardPeriod $period): float
    {
        return (float) $this->opportunities($user)
            ->where('stage', OpportunityStage::Won->value)
            ->whereBetween('won_at', [$period->start, $period->end])
            ->sum('amount_estimated');
    }

    public function acceptedQuotesCount(User $user, DashboardPeriod $period): int
    {
        return $this->quotes($user)
            ->where('status', QuoteDocumentStatus::Accepted->value)
            ->whereBetween('accepted_at', [$period->start, $period->end])
            ->count();
    }

    public function sentQuotesCount(User $user, DashboardPeriod $period): int
    {
        return $this->quotes($user)
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$period->start, $period->end])
            ->count();
    }

    public function quoteConversionRate(User $user, DashboardPeriod $period): ?float
    {
        $sent = $this->sentQuotesCount($user, $period);

        if ($sent === 0) {
            return null;
        }

        $accepted = $this->quotes($user)
            ->where('status', QuoteDocumentStatus::Accepted->value)
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$period->start, $period->end])
            ->count();

        return ($accepted / $sent) * 100;
    }

    public function averageSignedQuote(User $user, DashboardPeriod $period): ?float
    {
        $count = $this->acceptedQuotesCount($user, $period);

        if ($count === 0) {
            return null;
        }

        return $this->signedQuoteRevenue($user, $period) / $count;
    }

    public function wonOpportunitiesCount(User $user, DashboardPeriod $period): int
    {
        return $this->opportunities($user)
            ->where('stage', OpportunityStage::Won->value)
            ->whereBetween('won_at', [$period->start, $period->end])
            ->count();
    }

    public function averageWonOpportunity(User $user, DashboardPeriod $period): ?float
    {
        $count = $this->wonOpportunitiesCount($user, $period);

        if ($count === 0) {
            return null;
        }

        return $this->wonOpportunityRevenue($user, $period) / $count;
    }

    /**
     * Chiffre d'affaires signé (devis acceptés) et gagné (affaires remportées)
     * sur les derniers mois, mois par mois.
     *
     * @return array{labels: list<string>, quotes: list<float>, opportunities: list<float>}
     */
    public function revenueByMonth(User $user, int $months = 12): array
    {
        $months = max(1, min(24, $months));
        $end = CarbonImmutable::now(config('app.timezone'))->endOfMonth();
        $start = $end->startOfMonth()->subMonths($months - 1);

        $quotes = $this->quotes($user)
            ->where('status', QuoteDocumentStatus::Accepted->value)
            ->whereBetween('accepted_at', [$start, $end])
            ->get(['accepted_at', 'amount_ht']);

        $opportunities = $this->opportunities($user)
            ->where('stage', OpportunityStage::Won->value)
            ->whereBetween('won_at', [$start, $end])
            ->get(['won_at', 'amount_estimated']);

        $quoteTotals = [];
        $opportunityTotals = [];

        foreach ($quotes as $quote) {
            $key = $quote->accepted_at?->format('Y-m');

            if ($key !== null) {
                $quoteTotals[$key] = ($quoteTotals[$key] ?? 0.0) + (float) $quote->amount_ht;
            }
        }

        foreach ($opportunities as $opportunity) {
            $key = $opportunity->won_at?->format('Y-m');

            if ($key !== null) {
                $opportunityTotals[$key] = ($opportunityTotals[$key] ?? 0.0) + (float) $opportunity->amount_estimated;
            }
        }

        $labels = [];
        $quoteValues = [];
        $opportunityValues = [];

        for ($index = 0; $index < $months; $index++) {
            $month = $start->addMonths($index);
            $key = $month->format('Y-m');

            $labels[] = ucfirst($month->translatedFormat('M Y'));
            $quoteValues[] = round($quoteTotals[$key] ?? 0.0);
            $opportunityValues[] = round($opportunityTotals[$key] ?? 0.0);
        }

        return [
            'labels' => $labels,
            'quotes' => $quoteValues,
            'opportunities' => $opportunityValues,
        ];
    }

    /**
     * Nombre d'activités par type sur la période sélectionnée.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    public function activitiesByType(User $user, DashboardPeriod $period): array
    {
        $counts = CrmActivity::query()
            ->forUser($user)
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $labels = [];
        $values = [];

        foreach (CrmActivityType::cases() as $type) {
            $count = (int) ($counts[$type->value] ?? 0);

            if ($count === 0) {
                continue;
            }

            $labels[] = $type->getLabel();
            $values[] = $count;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Comptes portant le plus gros pipeline ouvert.
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    public function topAccountsByPipeline(User $user, int $limit = 5): array
    {
        $openStages = array_map(
            fn (OpportunityStage $stage): string => $stage->value,
            OpportunityStage::openCases(),
        );

        $rows = CrmOpportunity::query()
            ->from('crm_opportunities')
            ->join('crm_accounts', 'crm_accounts.id', '=', 'crm_opportunities.crm_account_id')
            ->whereNull('crm_accounts.deleted_at')
            ->whereIn('crm_opportunities.stage', $openStages)
            ->when(! $user->isAdmin(), fn (Builder $query): Builder => $query->where('crm_opportunities.owner_id', $user->id))
            ->selectRaw('crm_accounts.name as account_name')
            ->selectRaw('COALESCE(SUM(crm_opportunities.amount_estimated), 0) as pipeline_raw')
            ->groupBy('crm_accounts.id', 'crm_accounts.name')
            ->orderByDesc('pipeline_raw')
            ->limit($limit)
            ->get();

        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $labels[] = (string) data_get($row, 'account_name', '—');
            $values[] = round((float) data_get($row, 'pipeline_raw', 0));
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array<int, array{book: CrmBook, count: int, raw: float, weighted: float, share: float}>
     */
    public function pipelineByBook(User $user): array
    {
        $openStages = array_map(
            fn (OpportunityStage $stage): string => $stage->value,
            OpportunityStage::openCases(),
        );

        // Les libellés ci-dessous reprennent les valeurs de OpportunityStage::openCases()
        // et leurs probabilités par défaut, pour calculer le pondéré directement en SQL.
        $rows = CrmOpportunity::query()
            ->from('crm_opportunities')
            ->join('crm_accounts', 'crm_accounts.id', '=', 'crm_opportunities.crm_account_id')
            ->whereNull('crm_accounts.deleted_at')
            ->whereIn('crm_opportunities.stage', $openStages)
            ->when(! $user->isAdmin(), fn (Builder $query): Builder => $query->where('crm_opportunities.owner_id', $user->id))
            ->selectRaw('crm_accounts.book as book')
            ->selectRaw('COUNT(*) as opportunities_count')
            ->selectRaw('COALESCE(SUM(crm_opportunities.amount_estimated), 0) as pipeline_raw')
            ->selectRaw("COALESCE(SUM(COALESCE(crm_opportunities.amount_estimated, 0) * COALESCE(crm_opportunities.probability, CASE crm_opportunities.stage WHEN 'nouveau' THEN 10 WHEN 'qualifie' THEN 25 WHEN 'etude' THEN 40 WHEN 'devis_envoye' THEN 60 WHEN 'negociation' THEN 75 ELSE 0 END) / 100), 0) as pipeline_weighted")
            ->groupBy('crm_accounts.book')
            ->get()
            ->keyBy('book');

        $totalRaw = $rows->sum(fn (object $row): float => (float) data_get($row, 'pipeline_raw', 0));

        return collect(CrmBook::cases())
            ->map(function (CrmBook $book) use ($rows, $totalRaw): array {
                $row = $rows->get($book->value);

                $raw = $row !== null ? (float) data_get($row, 'pipeline_raw', 0) : 0.0;

                return [
                    'book' => $book,
                    'count' => $row !== null ? (int) data_get($row, 'opportunities_count', 0) : 0,
                    'raw' => $raw,
                    'weighted' => $row !== null ? (float) data_get($row, 'pipeline_weighted', 0) : 0.0,
                    'share' => $totalRaw > 0 ? ($raw / $totalRaw) * 100 : 0.0,
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, CrmOpportunity>
     */
    public function topOpenOpportunities(User $user, int $limit = 5): Collection
    {
        return $this->topOpenOpportunitiesQuery($user)
            ->with(['account', 'owner'])
            ->orderByDesc('amount_estimated')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Builder<CrmOpportunity>
     */
    public function topOpenOpportunitiesQuery(User $user): Builder
    {
        return $this->opportunities($user)
            ->open()
            ->whereHas('account');
    }

    /**
     * @return Collection<int, Quote>
     */
    public function quotesToFollowUp(User $user, int $days = 7): Collection
    {
        return $this->quotesToFollowUpQuery($user, $days)
            ->with(['account', 'owner'])
            ->limit(20)
            ->get();
    }

    /**
     * @return Builder<Quote>
     */
    public function quotesToFollowUpQuery(User $user, int $days = 7): Builder
    {
        $limitDate = now()->addDays($days)->endOfDay();

        return $this->quotes($user)
            ->where(function (Builder $query) use ($limitDate): void {
                $query
                    ->where(function (Builder $query) use ($limitDate): void {
                        $query
                            ->where('status', QuoteDocumentStatus::Sent->value)
                            ->whereNotNull('valid_until')
                            ->whereDate('valid_until', '<=', $limitDate->toDateString());
                    })
                    ->orWhere('status', QuoteDocumentStatus::Expired->value);
            })
            ->orderByRaw('CASE WHEN valid_until IS NULL THEN 1 ELSE 0 END')
            ->orderBy('valid_until')
            ->orderByDesc('amount_ht');
    }

    /**
     * @return array{revenue: float, target: float, progress: ?float, remaining: float, period: DashboardPeriod}
     */
    public function monthlyTargetProgress(User $user): array
    {
        $period = DashboardPeriod::month((int) now()->year, (int) now()->month);
        $revenue = $this->signedQuoteRevenue($user, $period);
        $target = (float) config('crm.dashboard.monthly_revenue_target', 0);

        return [
            'revenue' => $revenue,
            'target' => $target,
            'progress' => $target > 0 ? ($revenue / $target) * 100 : null,
            'remaining' => max(0.0, $target - $revenue),
            'period' => $period,
        ];
    }

    /**
     * @return Builder<Quote>
     */
    private function quotes(User $user): Builder
    {
        return Quote::query()
            ->when(! $user->isAdmin(), fn (Builder $query): Builder => $query->where('owner_id', $user->id));
    }

    /**
     * @return Builder<CrmOpportunity>
     */
    private function opportunities(User $user): Builder
    {
        return CrmOpportunity::query()
            ->when(! $user->isAdmin(), fn (Builder $query): Builder => $query->where('owner_id', $user->id));
    }
}
