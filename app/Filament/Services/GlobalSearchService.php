<?php

namespace App\Filament\Services;

use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GlobalSearchService
{
    /**
     * Recherche des comptes (tous carnets confondus).
     *
     * @return Collection<int, CrmAccount>
     */
    public function searchAccounts(string $query, ?string $book = null): Collection
    {
        return CrmAccount::query()
            ->when($book, fn (Builder $builder, string $book) => $builder->where('book', $book))
            ->where(function (Builder $builder) use ($query): void {
                $builder
                    ->where('name', 'like', '%'.$query.'%')
                    ->orWhere('legal_name', 'like', '%'.$query.'%')
                    ->orWhere('code', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%')
                    ->orWhere('city', 'like', '%'.$query.'%')
                    ->orWhere('siret', 'like', '%'.$query.'%');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Recherche des contacts.
     *
     * @return Collection<int, CrmContact>
     */
    public function searchContacts(string $query, ?string $book = null): Collection
    {
        return CrmContact::query()
            ->with('account')
            ->when(
                $book,
                fn (Builder $builder, string $book) => $builder
                    ->whereHas('account', fn (Builder $account) => $account->where('book', $book)),
            )
            ->where(function (Builder $builder) use ($query): void {
                $builder
                    ->where('first_name', 'like', '%'.$query.'%')
                    ->orWhere('last_name', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%')
                    ->orWhere('job_title', 'like', '%'.$query.'%');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Recherche des affaires (opportunités).
     *
     * @return Collection<int, CrmOpportunity>
     */
    public function searchOpportunities(
        string $query,
        ?string $book = null,
        ?string $stage = null,
    ): Collection {
        return CrmOpportunity::query()
            ->with('account')
            ->when(
                $book,
                fn (Builder $builder, string $book) => $builder
                    ->whereHas('account', fn (Builder $account) => $account->where('book', $book)),
            )
            ->when($stage, fn (Builder $builder, string $stage) => $builder->where('stage', $stage))
            ->where(function (Builder $builder) use ($query): void {
                $builder
                    ->where('title', 'like', '%'.$query.'%')
                    ->orWhere('reference', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%');
            })
            ->orderBy('reference')
            ->get();
    }

    /**
     * Recherche des devis.
     *
     * @return Collection<int, Quote>
     */
    public function searchQuotes(
        string $query,
        ?string $book = null,
        ?string $status = null,
    ): Collection {
        return Quote::query()
            ->with('account')
            ->when(
                $book,
                fn (Builder $builder, string $book) => $builder
                    ->whereHas('account', fn (Builder $account) => $account->where('book', $book)),
            )
            ->when($status, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->where(function (Builder $builder) use ($query): void {
                $builder
                    ->where('reference', 'like', '%'.$query.'%')
                    ->orWhere('subject', 'like', '%'.$query.'%');
            })
            ->orderBy('reference')
            ->get();
    }

    /**
     * Nom lisible d'un enregistrement.
     */
    public function getDisplayName(CrmAccount|CrmContact|CrmOpportunity|Quote $model): string
    {
        return match (true) {
            $model instanceof CrmAccount => $model->name.' — '.$model->book->getLabel(),
            $model instanceof CrmContact => $model->fullName(),
            $model instanceof CrmOpportunity => $model->title.' — '.$model->stage->getLabel(),
            $model instanceof Quote => $model->reference.' — '.$model->status->getLabel(),
        };
    }

    /**
     * Vérifie que l'utilisateur courant peut consulter l'enregistrement.
     */
    public function canView(CrmAccount|CrmContact|CrmOpportunity|Quote $model): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return Gate::allows('view', $model);
    }
}
