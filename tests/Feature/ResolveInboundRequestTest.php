<?php

use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Enums\CrmSource;
use App\Enums\QuoteKind;
use App\Models\ContactRequest;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\QuoteRequest;
use App\Services\ResolveInboundRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function resolveQuote(array $overrides = []): QuoteRequest
{
    return QuoteRequest::create(array_merge([
        'kind' => QuoteKind::Laser,
        'name' => 'Jean Dupont',
        'company' => 'Charpentes Dupont',
        'email' => 'jean@dupont.fr',
        'phone' => '06 12 34 56 78',
        'city' => 'Montaigu-Vendée',
        'message' => '40 pièces découpées en acier 3 mm.',
    ], $overrides));
}

test('une demande de devis laser crée un compte, un contact et une affaire', function (): void {
    $request = resolveQuote();

    $result = app(ResolveInboundRequest::class)->resolveQuoteRequest($request);

    $account = CrmAccount::findOrFail($result->crm_account_id);
    $contact = CrmContact::findOrFail($result->crm_contact_id);
    $opportunity = CrmOpportunity::findOrFail($result->crm_opportunity_id);

    expect($account->book)->toBe(CrmBook::IndustrialClients)
        ->and($account->category)->toBe(CrmCategory::Factories)
        ->and($account->source)->toBe(CrmSource::Website)
        ->and($account->name)->toBe('Charpentes Dupont')
        ->and($contact->account->id)->toBe($account->id)
        ->and($contact->first_name)->toBe('Jean')
        ->and($contact->last_name)->toBe('Dupont')
        ->and($contact->is_primary)->toBeTrue()
        ->and($opportunity->account->id)->toBe($account->id)
        ->and($opportunity->quote_request_id)->toBe($request->id)
        ->and($opportunity->title)->toContain('Découpe laser');
});

test('une demande de devis bâtiment vise le carnet des entreprises du bâtiment', function (): void {
    $request = resolveQuote(['kind' => QuoteKind::Building, 'company' => 'BTP Vendée']);

    $result = app(ResolveInboundRequest::class)->resolveQuoteRequest($request);

    $account = CrmAccount::findOrFail($result->crm_account_id);

    expect($account->book)->toBe(CrmBook::BuildingCompanies)
        ->and($account->category)->toBe(CrmCategory::GeneralContractors);
});

test('convertir deux fois une demande ne crée pas de doublons', function (): void {
    $request = resolveQuote();
    $service = app(ResolveInboundRequest::class);

    $first = $service->resolveQuoteRequest($request);
    $second = $service->resolveQuoteRequest($first);

    expect($second->crm_opportunity_id)->toBe($first->crm_opportunity_id)
        ->and(CrmAccount::count())->toBe(1)
        ->and(CrmContact::count())->toBe(1)
        ->and(CrmOpportunity::count())->toBe(1);
});

test('un compte et un contact existants sont réutilisés via l’e-mail', function (): void {
    $account = CrmAccount::factory()->create(['name' => 'Charpentes Dupont']);
    $contact = CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'email' => 'jean@dupont.fr',
    ]);

    $request = resolveQuote();

    $result = app(ResolveInboundRequest::class)->resolveQuoteRequest($request);

    expect($result->crm_account_id)->toBe($account->id)
        ->and($result->crm_contact_id)->toBe($contact->id)
        ->and(CrmAccount::count())->toBe(1)
        ->and(CrmContact::count())->toBe(1);
});

test('un carnet imposé force une catégorie cohérente', function (): void {
    $request = resolveQuote();

    $result = app(ResolveInboundRequest::class)->resolveQuoteRequest(
        $request,
        CrmBook::Prescribers,
        CrmCategory::Factories,
    );

    $account = CrmAccount::findOrFail($result->crm_account_id);

    expect($account->book)->toBe(CrmBook::Prescribers)
        ->and($account->category->book())->toBe(CrmBook::Prescribers);
});

test('une demande de contact crée un compte et un contact puis la marque traitée', function (): void {
    $request = ContactRequest::create([
        'name' => 'Marie Lambert',
        'company' => 'Atelier Lambert',
        'email' => 'marie@lambert.fr',
        'phone' => '02 40 00 00 00',
        'request_type' => ContactRequest::requestTypes()[0],
        'message' => 'Besoin de charpente métallique.',
    ]);

    $result = app(ResolveInboundRequest::class)->resolveContactRequest($request);

    expect($result->crm_account_id)->not->toBeNull()
        ->and($result->crm_contact_id)->not->toBeNull()
        ->and($result->isRead())->toBeTrue()
        ->and(CrmContact::find($result->crm_contact_id)->first_name)->toBe('Marie');
});
