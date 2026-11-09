<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Calendar;
use App\Filament\Pages\FollowUps;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Emails\EmailMessageResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\User;
use Filament\Facades\Filament;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBaseCompanionPlugin;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBasePlugin;

function knowledgeBaseAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function knowledgeBaseCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

test('le panneau de la base documentaire est enregistré avec son thème', function (): void {
    $panel = Filament::getPanel('knowledge-base');

    expect($panel->getId())->toBe('knowledge-base')
        ->and($panel->getPath())->toBe('aide')
        ->and($panel->hasPlugin(KnowledgeBasePlugin::ID))->toBeTrue()
        ->and($panel->getViteTheme())->toBe('resources/css/filament/admin/theme.css');
});

test('le plugin compagnon est enregistré sur le panneau d’administration', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->hasPlugin(KnowledgeBaseCompanionPlugin::ID))->toBeTrue();
});

test('un visiteur non connecté est redirigé vers la connexion', function (): void {
    $this->get('/aide')->assertRedirect();
});

test('la racine de la base documentaire redirige vers le premier article', function (): void {
    $response = $this->actingAs(knowledgeBaseAdmin())->get('/aide');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/aide/prologue');
});

test('un administrateur consulte un article', function (): void {
    $this->actingAs(knowledgeBaseAdmin())
        ->get('/aide/prologue')
        ->assertOk()
        ->assertSee('Bien démarrer avec le CRM C2ME');
});

test('un commercial consulte un article imbriqué', function (): void {
    $this->actingAs(knowledgeBaseCommercial())
        ->get('/aide/crm/comptes')
        ->assertOk()
        ->assertSee('Un compte représente une société');
});

test('un article inexistant renvoie une erreur 404', function (): void {
    $this->actingAs(knowledgeBaseAdmin())
        ->get('/aide/article-inexistant')
        ->assertNotFound();
});

test('un utilisateur désactivé n’accède pas à la base documentaire', function (): void {
    $inactive = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => false,
    ]);

    $this->actingAs($inactive)
        ->get('/aide/prologue')
        ->assertForbidden();
});

test('les ressources et pages documentées pointent vers un article existant', function (string $class): void {
    expect(is_a($class, HasKnowledgeBase::class, true))->toBeTrue();

    $documentation = (array) $class::getDocumentation();

    expect($documentation)->not->toBeEmpty();

    foreach ($documentation as $id) {
        expect(is_string($id))->toBeTrue();

        $path = base_path('docs/knowledge-base/fr/'.str_replace('.', '/', $id).'.md');

        expect(is_file($path))->toBeTrue();
    }
})->with([
    'comptes' => [AccountResource::class],
    'devis' => [QuoteResource::class],
    'activités' => [ActivityResource::class],
    'e-mails' => [EmailMessageResource::class],
    'relances' => [FollowUps::class],
    'calendrier' => [Calendar::class],
]);
