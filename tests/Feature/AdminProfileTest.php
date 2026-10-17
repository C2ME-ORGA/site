<?php

use App\Filament\Pages\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const CURRENT_PASSWORD = 'MotDePasse-Actuel-2026!';
const NEW_PASSWORD = 'Nouveau-MotDePasse-2026!';

/**
 * Compte disposant d'un accès au back-office.
 */
function profileAdmin(): User
{
    return User::factory()->create([
        'email' => config('site.admin.allowed_emails')[0],
        'password' => CURRENT_PASSWORD,
    ]);
}

beforeEach(function (): void {
    // Les composants Filament ont besoin du panneau courant pour monter.
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('un administrateur ouvre sa page de profil', function (): void {
    // Le champ « mot de passe actuel » n'est visible qu'une fois le nouveau
    // mot de passe renseigné : il n'est donc pas rendu au chargement.
    $this->actingAs(profileAdmin())
        ->get('/admin/profile')
        ->assertOk()
        ->assertSee('Nouveau mot de passe')
        ->assertSee('Sauvegarder les modifications');
});

test('un visiteur ne peut pas atteindre le profil', function (): void {
    $this->get('/admin/profile')->assertRedirect();
});

test('un administrateur change son mot de passe depuis le profil', function (): void {
    $user = profileAdmin();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.password', NEW_PASSWORD)
        ->set('data.passwordConfirmation', NEW_PASSWORD)
        ->set('data.currentPassword', CURRENT_PASSWORD)
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check(NEW_PASSWORD, $user->fresh()->password))->toBeTrue();
});

test('un mot de passe actuel erroné est refusé', function (): void {
    $user = profileAdmin();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.password', NEW_PASSWORD)
        ->set('data.passwordConfirmation', NEW_PASSWORD)
        ->set('data.currentPassword', 'Ce-n-est-pas-le-bon')
        ->call('save')
        ->assertHasErrors(['data.currentPassword']);

    // Le mot de passe existant reste intact.
    expect(Hash::check(CURRENT_PASSWORD, $user->fresh()->password))->toBeTrue();
});

test('une confirmation de mot de passe divergente est refusée', function (): void {
    $user = profileAdmin();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.password', NEW_PASSWORD)
        ->set('data.passwordConfirmation', 'Autre-Chaine-2026!')
        ->set('data.currentPassword', CURRENT_PASSWORD)
        ->call('save')
        // La règle « same » vit sur le champ mot de passe, pas sur sa copie.
        ->assertHasErrors(['data.password']);

    expect(Hash::check(CURRENT_PASSWORD, $user->fresh()->password))->toBeTrue();
});

test('une adresse hors liste ne peut pas être enregistrée', function (): void {
    $user = profileAdmin();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.email', 'inconnu@example.com')
        ->set('data.currentPassword', CURRENT_PASSWORD)
        ->call('save')
        ->assertHasErrors(['data.email']);

    expect($user->fresh()->email)->toBe(config('site.admin.allowed_emails')[0]);
});

test('l adresse autorisée peut être modifiée', function (): void {
    $user = profileAdmin();
    $replacement = 'bureau@c2me.fr';

    config(['site.admin.allowed_emails' => [$replacement]]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.email', $replacement)
        ->set('data.currentPassword', CURRENT_PASSWORD)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->email)->toBe($replacement);
});
