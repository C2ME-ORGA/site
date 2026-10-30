<?php

use App\Models\ContactRequest;
use App\Models\User;

/**
 * @param  array<string, mixed>  $overrides
 */
function seedContactRequest(array $overrides = []): ContactRequest
{
    return ContactRequest::create(array_merge([
        'name' => 'Jean Dupont',
        'company' => 'Charpentes Dupont',
        'email' => 'jean@dupont.fr',
        'phone' => '06 12 34 56 78',
        'request_type' => ContactRequest::requestTypes()[0],
        'message' => 'Demande de devis pour 40 pièces découpées.',
    ], $overrides));
}

/**
 * Crée un compte disposant d'un accès au back-office.
 */
function adminUser(): User
{
    return User::factory()->create([
        'email' => config('site.admin.allowed_emails')[0],
    ]);
}

test('un compte désactivé ne peut pas atteindre le back-office', function (): void {
    $this->actingAs(User::factory()->create(['is_active' => false]))
        ->get('/admin/contact-requests')
        ->assertForbidden();
});

test('un administrateur consulte la liste des demandes', function (): void {
    seedContactRequest();

    $this->actingAs(adminUser())
        ->get('/admin/contact-requests')
        ->assertOk()
        ->assertSee('Jean Dupont')
        ->assertSee('Charpentes Dupont');
});

test('les demandes non traitées remontent un badge de navigation', function (): void {
    $this->actingAs(adminUser())
        ->get('/admin')
        ->assertOk()
        ->assertSee('1', false);
});

test('un visiteur est redirigé vers la connexion', function (): void {
    $this->get('/admin/contact-requests')->assertRedirect();
});

test('une demande de contact dispose d une fiche détaillée avec ses actions', function (): void {
    $contact = seedContactRequest();

    $this->actingAs(adminUser())
        ->get("/admin/contact-requests/{$contact->id}")
        ->assertOk()
        ->assertSee('Demande')
        ->assertSee('Jean Dupont')
        ->assertSee('Charpentes Dupont')
        ->assertSee('jean@dupont.fr')
        ->assertSee('Demande de devis pour 40 pièces découpées.')
        ->assertSee('Non traitée')
        ->assertSee('Répondre')
        ->assertSee('Créer dans le CRM')
        ->assertSee('Marquer comme traitée')
        ->assertSee('Modifier');
});

test('une demande peut être ouverte pour modification', function (): void {
    $contact = seedContactRequest();

    $this->actingAs(adminUser())
        ->get("/admin/contact-requests/{$contact->id}/edit")
        ->assertOk()
        ->assertSee('Demandeur')
        ->assertSee('jean@dupont.fr');
});
