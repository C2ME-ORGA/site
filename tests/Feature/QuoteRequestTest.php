<?php

use App\Enums\QuoteKind;
use App\Enums\QuoteStatus;
use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @param  array<string, mixed>  $overrides
 */
function makeQuoteRequest(array $overrides = []): QuoteRequest
{
    $quote = QuoteRequest::create(array_merge([
        'kind' => QuoteKind::Laser,
        'name' => 'Jean Dupont',
        'company' => 'Charpentes Dupont',
        'email' => 'jean@dupont.fr',
        'phone' => '06 12 34 56 78',
        'city' => 'Montaigu-Vendée',
        'message' => '40 pièces découpées en acier 3 mm.',
        'payload' => [
            'material' => 'Acier',
            'thickness' => '3 mm',
            'quantity' => '40',
        ],
    ], $overrides));

    if (array_key_exists('created_at', $overrides)) {
        $quote->forceFill(['created_at' => $overrides['created_at']])->save();
    }

    return $quote;
}

function quoteAdminUser(): User
{
    return User::factory()->create([
        'email' => config('site.admin.allowed_emails')[0],
    ]);
}

test('une demande démarre au statut nouveau', function (): void {
    $quote = makeQuoteRequest();

    $this->assertSame(QuoteStatus::New, $quote->status);
    $this->assertTrue($quote->isPending());
});

test('le résumé restitue la charge utile du parcours', function (): void {
    $quote = makeQuoteRequest([
        'payload' => ['material' => 'Acier', 'empty' => null, 'tags' => []],
    ]);

    $this->assertSame(['material' => 'Acier'], $quote->summary());
});

test('la purge supprime les demandes expirées et leurs fichiers', function (): void {
    Storage::fake('local');

    $expired = makeQuoteRequest(['created_at' => now()->subMonths(4)]);
    $expired->files()->create([
        'original_name' => 'plan.dxf',
        'path' => 'quote-requests/plan.dxf',
        'mime_type' => 'application/dxf',
        'size' => 1024,
    ]);
    Storage::disk('local')->put('quote-requests/plan.dxf', 'contenu du plan');

    artisanCommand($this, 'quotes:purge')
        ->expectsOutputToContain('1 demande(s) supprimée(s)')
        ->assertSuccessful();

    $this->assertDatabaseMissing('quote_requests', ['id' => $expired->id]);
    Storage::disk('local')->assertMissing('quote-requests/plan.dxf');
});

test('la purge conserve les demandes encore dans les délais', function (): void {
    Storage::fake('local');

    $recent = makeQuoteRequest(['created_at' => now()->subMonths(2)]);

    artisanCommand($this, 'quotes:purge')->assertSuccessful();

    $this->assertDatabaseHas('quote_requests', ['id' => $recent->id]);
});

test('le téléchargement des plans regroupe les fichiers dans une archive', function (): void {
    Storage::fake('local');

    $quote = makeQuoteRequest();

    Storage::disk('local')->put('quote-requests/a.dxf', 'DXF-A');
    Storage::disk('local')->put('quote-requests/b.pdf', 'PDF-B');

    $quote->files()->create(['original_name' => 'a.dxf', 'path' => 'quote-requests/a.dxf', 'size' => 5]);
    $quote->files()->create(['original_name' => 'b.pdf', 'path' => 'quote-requests/b.pdf', 'size' => 5]);

    $response = $quote->downloadArchive();

    $this->assertInstanceOf(StreamedResponse::class, $response);
    $this->assertSame('application/zip', $response->headers->get('Content-Type'));

    ob_start();
    $response->sendContent();
    $binary = ob_get_clean();

    $archive = tempnam(sys_get_temp_dir(), 'c2me-test');
    file_put_contents($archive, $binary);

    $zip = new ZipArchive;
    $zip->open($archive);

    $this->assertSame(2, $zip->numFiles);

    $zip->close();
    unlink($archive);
});

test('un administrateur consulte la liste des demandes de devis', function (): void {
    makeQuoteRequest();

    $this->actingAs(quoteAdminUser())
        ->get('/admin/quote-requests')
        ->assertOk()
        ->assertSee('Jean Dupont')
        ->assertSee('Découpe laser');
});

test('une demande peut être ouverte pour modification', function (): void {
    $quote = makeQuoteRequest();

    $this->actingAs(quoteAdminUser())
        ->get("/admin/quote-requests/{$quote->id}/edit")
        ->assertOk()
        ->assertSee('Demandeur')
        ->assertSee('Note interne');
});

test('la page de création d une demande de devis est accessible', function (): void {
    // Sans enregistrement, $record est null : les fermetures du formulaire
    // doivent l'accepter, y compris celles qui lisent les plans joints.
    $this->actingAs(quoteAdminUser())
        ->get('/admin/quote-requests/create')
        ->assertOk()
        ->assertSee('Demandeur');
});

test('un visiteur est redirigé vers la connexion', function (): void {
    $this->get('/admin/quote-requests')->assertRedirect();
});

test('un compte désactivé ne peut pas atteindre le back-office', function (): void {
    $this->actingAs(User::factory()->create(['is_active' => false]))
        ->get('/admin/quote-requests')
        ->assertForbidden();
});
