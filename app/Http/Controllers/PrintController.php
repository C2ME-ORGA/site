<?php

namespace App\Http\Controllers;

use App\Enums\CrmBook;
use App\Enums\CrmSource;
use App\Models\CrmAccount;
use App\Models\CrmOpportunity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class PrintController extends Controller
{
    public function account(CrmAccount $account): View
    {
        $this->authorizeActiveUser();
        Gate::authorize('view', $account);

        $account->load([
            'owner',
            'tags',
            'contacts' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('last_name')
                ->orderBy('first_name'),
            'opportunities' => fn ($query) => $query
                ->with('owner')
                ->orderByDesc('created_at'),
            'quotes' => fn ($query) => $query
                ->orderByDesc('issued_at'),
            'activities' => fn ($query) => $query
                ->with('user')
                ->latest('occurred_at')
                ->limit(10),
        ]);

        return view('print.account', [
            'account' => $account,
            'company' => config('site.company'),
            'legal' => config('site.legal.editor'),
            'metadata' => $this->metadataRows($account),
        ]);
    }

    public function opportunity(CrmOpportunity $opportunity): View
    {
        $this->authorizeActiveUser();
        Gate::authorize('view', $opportunity);

        $opportunity->load([
            'account.owner',
            'contact',
            'owner',
            'quotes' => fn ($query) => $query
                ->orderByDesc('issued_at'),
            'activities' => fn ($query) => $query
                ->with('user')
                ->latest('occurred_at')
                ->limit(10),
        ]);

        return view('print.opportunity', [
            'opportunity' => $opportunity,
            'company' => config('site.company'),
            'legal' => config('site.legal.editor'),
        ]);
    }

    private function authorizeActiveUser(): void
    {
        abort_unless(auth()->user()?->is_active, 403);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function metadataRows(CrmAccount $account): array
    {
        /** @var array<int, array<string, mixed>> $fields */
        $fields = config('crm.fields.'.$account->book->value, []);

        return array_map(function (array $field) use ($account): array {
            $value = data_get($account->metadata, $field['key'] ?? '');

            if (($field['type'] ?? null) === 'select') {
                /** @var array<string, string> $options */
                $options = $field['options'] ?? [];
                $value = is_scalar($value) ? ($options[(string) $value] ?? $value) : null;
            }

            return [
                'label' => (string) ($field['label'] ?? $field['key'] ?? ''),
                'value' => $this->displayValue($value),
            ];
        }, $fields);
    }

    private function displayValue(mixed $value): string
    {
        if ($value instanceof CrmBook) {
            return $value->getLabel();
        }

        if ($value instanceof CrmSource) {
            return $value->getLabel();
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
