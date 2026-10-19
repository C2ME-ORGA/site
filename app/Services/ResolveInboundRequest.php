<?php

namespace App\Services;

use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Enums\CrmSource;
use App\Models\ContactRequest;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\QuoteRequest;
use Illuminate\Support\Facades\DB;

/**
 * Transforme une demande déposée sur le site (devis ou contact) en fiches
 * CRM : compte, contact et — pour un devis — affaire rattachée. L'opération
 * est idempotente : une demande déjà convertie n'est jamais dupliquée.
 */
class ResolveInboundRequest
{
    /**
     * Carnet proposé par défaut pour la demande donnée.
     */
    public function defaultBook(QuoteRequest|ContactRequest $request): CrmBook
    {
        return $this->defaults($request)['book'];
    }

    /**
     * Catégorie proposée par défaut pour la demande donnée.
     */
    public function defaultCategory(QuoteRequest|ContactRequest $request): CrmCategory
    {
        return $this->defaults($request)['category'];
    }

    /**
     * Convertit une demande de devis en compte, contact et affaire.
     */
    public function resolveQuoteRequest(
        QuoteRequest $request,
        ?CrmBook $book = null,
        ?CrmCategory $category = null,
    ): QuoteRequest {
        if ($request->crm_opportunity_id !== null) {
            return $request;
        }

        [$book, $category] = $this->resolveTarget($this->defaults($request), $book, $category);

        return DB::transaction(function () use ($request, $book, $category): QuoteRequest {
            $account = $this->findOrCreateAccount($request, $book, $category);
            $contact = $this->findOrCreateContact($request, $account);

            $opportunity = CrmOpportunity::create([
                'crm_account_id' => $account->id,
                'crm_contact_id' => $contact->id,
                'quote_request_id' => $request->id,
                'title' => $this->opportunityTitle($request),
                'description' => $request->message,
                'source' => CrmSource::Website->value,
                'owner_id' => $account->owner_id,
            ]);

            $request->forceFill([
                'crm_account_id' => $account->id,
                'crm_contact_id' => $contact->id,
                'crm_opportunity_id' => $opportunity->id,
                'owner_id' => $account->owner_id,
            ])->save();

            return $request;
        });
    }

    /**
     * Convertit une demande de contact en compte et contact, puis la marque
     * comme traitée.
     */
    public function resolveContactRequest(
        ContactRequest $request,
        ?CrmBook $book = null,
        ?CrmCategory $category = null,
    ): ContactRequest {
        if ($request->crm_contact_id !== null) {
            return $request;
        }

        [$book, $category] = $this->resolveTarget($this->defaults($request), $book, $category);

        return DB::transaction(function () use ($request, $book, $category): ContactRequest {
            $account = $this->findOrCreateAccount($request, $book, $category);
            $contact = $this->findOrCreateContact($request, $account);

            $request->forceFill([
                'crm_account_id' => $account->id,
                'crm_contact_id' => $contact->id,
            ])->save();

            $request->markAsRead();

            return $request;
        });
    }

    /**
     * Réutilise un compte existant : d'abord par e-mail (compte ou contact),
     * puis par nom de société.
     */
    private function matchAccount(QuoteRequest|ContactRequest $request): ?CrmAccount
    {
        $email = mb_strtolower(trim($request->email));

        $account = CrmAccount::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($account instanceof CrmAccount) {
            return $account;
        }

        $contactAccount = CrmContact::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first()
            ?->account;

        if ($contactAccount instanceof CrmAccount) {
            return $contactAccount;
        }

        if (filled($request->company)) {
            return CrmAccount::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $request->company))])
                ->first();
        }

        return null;
    }

    private function findOrCreateAccount(
        QuoteRequest|ContactRequest $request,
        CrmBook $book,
        CrmCategory $category,
    ): CrmAccount {
        $account = $this->matchAccount($request);

        if ($account instanceof CrmAccount) {
            return $account;
        }

        return CrmAccount::create([
            'book' => $book,
            'category' => $category,
            'name' => $this->accountName($request),
            'legal_name' => $request->company,
            'email' => $request->email,
            'phone' => $request->phone,
            'city' => $request instanceof QuoteRequest ? $request->city : null,
            'source' => CrmSource::Website,
            'owner_id' => $request instanceof QuoteRequest ? $request->owner_id : null,
        ]);
    }

    private function findOrCreateContact(
        QuoteRequest|ContactRequest $request,
        CrmAccount $account,
    ): CrmContact {
        $email = mb_strtolower(trim($request->email));

        $contact = CrmContact::query()
            ->where('crm_account_id', $account->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($contact instanceof CrmContact) {
            return $contact;
        }

        [$firstName, $lastName] = $this->splitName($request->name);

        return CrmContact::create([
            'crm_account_id' => $account->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->email,
            'phone' => $request->phone,
            'is_primary' => ! $account->contacts()->exists(),
            'last_contacted_at' => now(),
        ]);
    }

    /**
     * Carnet et catégorie déclarés dans la configuration pour la demande.
     *
     * @return array{book: CrmBook, category: CrmCategory}
     */
    private function defaults(QuoteRequest|ContactRequest $request): array
    {
        if ($request instanceof QuoteRequest) {
            $prefix = 'crm.requests.quote.'.$request->kind->value.'.';
            $book = config($prefix.'book', CrmBook::IndustrialClients->value);
            $category = config($prefix.'category', CrmCategory::Factories->value);
        } else {
            $book = config('crm.requests.contact.book', CrmBook::IndustrialClients->value);
            $category = config('crm.requests.contact.category', CrmCategory::Factories->value);
        }

        $resolvedBook = CrmBook::tryFrom((string) $book) ?? CrmBook::IndustrialClients;
        $resolvedCategory = CrmCategory::tryFrom((string) $category) ?? CrmCategory::Factories;

        if ($resolvedCategory->book() !== $resolvedBook) {
            $resolvedCategory = CrmCategory::forBook($resolvedBook)[0];
        }

        return ['book' => $resolvedBook, 'category' => $resolvedCategory];
    }

    /**
     * Applique les éventuelles valeurs fournies, en garantissant la cohérence
     * entre le carnet et la catégorie.
     *
     * @param  array{book: CrmBook, category: CrmCategory}  $defaults
     * @return array{0: CrmBook, 1: CrmCategory}
     */
    private function resolveTarget(array $defaults, ?CrmBook $book, ?CrmCategory $category): array
    {
        $book ??= $defaults['book'];
        $category ??= $defaults['category'];

        if ($category->book() !== $book) {
            $category = CrmCategory::forBook($book)[0];
        }

        return [$book, $category];
    }

    private function accountName(QuoteRequest|ContactRequest $request): string
    {
        return filled($request->company)
            ? trim((string) $request->company)
            : trim($request->name);
    }

    private function opportunityTitle(QuoteRequest $request): string
    {
        return sprintf('%s — %s', $request->kind->getLabel(), $this->accountName($request));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        if ($parts === false) {
            return [trim($name), ''];
        }

        return [$parts[0], $parts[1] ?? ''];
    }
}
