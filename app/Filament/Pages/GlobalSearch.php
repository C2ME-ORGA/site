<?php

namespace App\Filament\Pages;

use App\Enums\CrmBook;
use App\Filament\Services\GlobalSearchService;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\Quote;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class GlobalSearch extends Page
{
    protected string $view = 'filament.pages.global-search';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Recherche globale';

    protected static ?string $title = 'Recherche globale';

    protected static ?string $slug = 'global-search';

    public string $searchQuery = '';

    public ?string $book = null;

    public ?string $stage = null;

    public ?string $status = null;

    /**
     * Groupes dépliés au-delà des 10 premiers résultats.
     *
     * @var array<string, bool>
     */
    public array $expandedGroups = [];

    /**
     * Résultats groupés par type, filtrés selon les autorisations de l'utilisateur.
     *
     * @return array<string, array{items: array<int, array{name: string, description: string|null, url: string}>, total: int, expanded: bool}>
     */
    public function getResults(): array
    {
        $query = trim($this->searchQuery);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $service = app(GlobalSearchService::class);

        $groups = [];

        $accountModels = $service->searchAccounts($query, $this->book)
            ->filter(fn (CrmAccount $account): bool => $service->canView($account));

        $total = $accountModels->count();

        if ($total > 0) {
            $expanded = $this->expandedGroups['Comptes'] ?? false;
            $visible = $expanded ? $accountModels : $accountModels->take(10);

            $groups['Comptes'] = [
                'items' => $visible->map(fn (CrmAccount $account): array => [
                    'name' => $account->name,
                    'description' => $account->book->getLabel(),
                    'url' => $this->accountUrl($account),
                ])->values()->all(),
                'total' => $total,
                'expanded' => $expanded,
            ];
        }

        $contactModels = $service->searchContacts($query, $this->book)
            ->filter(fn (CrmContact $contact): bool => $service->canView($contact));

        $total = $contactModels->count();

        if ($total > 0) {
            $expanded = $this->expandedGroups['Contacts'] ?? false;
            $visible = $expanded ? $contactModels : $contactModels->take(10);

            $groups['Contacts'] = [
                'items' => $visible->map(fn (CrmContact $contact): array => [
                    'name' => $contact->fullName(),
                    'description' => collect([$contact->email, $contact->account?->name])
                        ->filter()
                        ->implode(' · '),
                    'url' => $contact->account !== null
                        ? $this->accountUrl($contact->account)
                        : route('filament.admin.resources.prescriber-accounts.index'),
                ])->values()->all(),
                'total' => $total,
                'expanded' => $expanded,
            ];
        }

        $opportunityModels = $service->searchOpportunities($query, $this->book, $this->stage)
            ->filter(fn (CrmOpportunity $opportunity): bool => $service->canView($opportunity));

        $total = $opportunityModels->count();

        if ($total > 0) {
            $expanded = $this->expandedGroups['Affaires'] ?? false;
            $visible = $expanded ? $opportunityModels : $opportunityModels->take(10);

            $groups['Affaires'] = [
                'items' => $visible->map(fn (CrmOpportunity $opportunity): array => [
                    'name' => $opportunity->title,
                    'description' => collect([$opportunity->reference, $opportunity->stage->getLabel()])
                        ->filter()
                        ->implode(' · '),
                    'url' => $opportunity->account !== null
                        ? $this->accountUrl($opportunity->account)
                        : route('filament.admin.resources.prescriber-accounts.index'),
                ])->values()->all(),
                'total' => $total,
                'expanded' => $expanded,
            ];
        }

        $quoteModels = $service->searchQuotes($query, $this->book, $this->status)
            ->filter(fn (Quote $quote): bool => $service->canView($quote));

        $total = $quoteModels->count();

        if ($total > 0) {
            $expanded = $this->expandedGroups['Devis'] ?? false;
            $visible = $expanded ? $quoteModels : $quoteModels->take(10);

            $groups['Devis'] = [
                'items' => $visible->map(fn (Quote $quote): array => [
                    'name' => $quote->reference,
                    'description' => collect([$quote->subject, $quote->status->getLabel()])
                        ->filter()
                        ->implode(' · '),
                    'url' => route('filament.admin.resources.quotes.edit', ['record' => $quote]),
                ])->values()->all(),
                'total' => $total,
                'expanded' => $expanded,
            ];
        }

        return $groups;
    }

    public function toggleGroup(string $group): void
    {
        $this->expandedGroups[$group] = ! ($this->expandedGroups[$group] ?? false);
    }

    public function resetFilters(): void
    {
        $this->book = null;
        $this->stage = null;
        $this->status = null;
        $this->expandedGroups = [];
    }

    /**
     * Met en évidence le terme recherché, en échappant le HTML.
     */
    public function highlight(?string $text): HtmlString
    {
        $text = (string) $text;
        $escaped = e($text);
        $term = trim($this->searchQuery);

        if ($term === '') {
            return new HtmlString($escaped);
        }

        $pattern = '/('.preg_quote(e($term), '/').')/iu';
        $result = preg_replace($pattern, '<mark>$1</mark>', $escaped);

        return new HtmlString($result ?? $escaped);
    }

    /**
     * URL de la fiche compte, selon le carnet auquel il appartient.
     */
    private function accountUrl(CrmAccount $account): string
    {
        $resource = match ($account->book) {
            CrmBook::Prescribers => 'prescriber-accounts',
            CrmBook::IndustrialClients => 'industrial-accounts',
            CrmBook::BuildingCompanies => 'building-company-accounts',
        };

        return route("filament.admin.resources.{$resource}.edit", ['record' => $account]);
    }
}
