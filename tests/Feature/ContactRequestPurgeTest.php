<?php

use App\Models\ContactRequest;

/**
 * Crée une demande de contact, avec la date de dépôt si elle est imposée.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeContactRequest(array $overrides = []): ContactRequest
{
    $contact = ContactRequest::create(array_merge([
        'name' => 'Jean Dupont',
        'company' => 'Charpentes Dupont',
        'email' => 'jean@dupont.fr',
        'phone' => '06 12 34 56 78',
        'request_type' => ContactRequest::requestTypes()[0],
        'message' => 'Demande de renseignement sur la découpe laser.',
    ], $overrides));

    if (array_key_exists('created_at', $overrides)) {
        $contact->forceFill(['created_at' => $overrides['created_at']])->save();
    }

    return $contact;
}

test('la purge supprime les demandes dépassant la durée de conservation', function (): void {
    $expired = makeContactRequest(['created_at' => now()->subMonths(13)]);

    artisanCommand($this, 'contacts:purge')
        ->expectsOutputToContain('1 demande(s) supprimée(s)')
        ->assertSuccessful();

    $this->assertDatabaseMissing('contact_requests', ['id' => $expired->id]);
});

test('la purge conserve les demandes encore dans les délais', function (): void {
    $recent = makeContactRequest(['created_at' => now()->subMonths(2)]);

    artisanCommand($this, 'contacts:purge')->assertSuccessful();

    $this->assertDatabaseHas('contact_requests', ['id' => $recent->id]);
});

test('la durée de conservation est lue dans la configuration', function (): void {
    config(['site.contact.retention_months' => 1]);

    $expired = makeContactRequest(['created_at' => now()->subMonths(2)]);
    $kept = makeContactRequest(['created_at' => now()->subDays(10)]);

    artisanCommand($this, 'contacts:purge')
        ->expectsOutputToContain('conservation de 1 mois')
        ->assertSuccessful();

    $this->assertDatabaseMissing('contact_requests', ['id' => $expired->id]);
    $this->assertDatabaseHas('contact_requests', ['id' => $kept->id]);
});
