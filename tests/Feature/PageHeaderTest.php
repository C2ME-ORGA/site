<?php

use App\Enums\CrmBook;
use App\Enums\UserRole;
use App\Filament\Resources\Activities\Pages\EditActivity;
use App\Filament\Resources\ContactRequests\Pages\EditContactRequest;
use App\Filament\Resources\Mailboxes\Pages\EditMailbox;
use App\Filament\Resources\PrescriberAccounts\Pages\ViewPrescriberAccount;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\ContactRequest;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\EmailMailbox;
use App\Models\User;
use App\Support\FilamentAccountUrl;
use Filament\Facades\Filament;
use Livewire\Livewire;
use MortalKiller\FilamentPageHeader\PageHeaderPlugin;

function pageHeaderUser(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

test('le panel admin active le plugin d’en-tête de page', function (): void {
    expect(Filament::getPanel('admin')->hasPlugin(PageHeaderPlugin::ID))->toBeTrue();
});

test('l’en-tête d’une activité rend le compte cliquable', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = pageHeaderUser();
    $account = CrmAccount::factory()
        ->forBook(CrmBook::Prescribers)
        ->create(['name' => 'Atelier du Métal Nantais']);

    $activity = CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'crm_contact_id' => null,
        'subject' => 'Relance — reprise d’atelier',
    ]);

    Livewire::actingAs($admin)
        ->test(EditActivity::class, ['record' => $activity->getKey()])
        ->assertSee('Atelier du Métal Nantais')
        ->assertSeeHtml('href="'.FilamentAccountUrl::view($account).'"');
});

test('la fiche compte utilise l’en-tête partagé des trois carnets', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = pageHeaderUser();
    $account = CrmAccount::factory()
        ->forBook(CrmBook::Prescribers)
        ->create([
            'name' => 'Atelier Test',
            'owner_id' => $admin->id,
        ]);

    Livewire::actingAs($admin)
        ->test(ViewPrescriberAccount::class, ['record' => $account->getRouteKey()])
        ->assertSee('Atelier Test')
        ->assertSee('Commercial')
        ->assertSee('Dernier contact');
});

test('les en-têtes utilisateur et boîte e-mail se rendent', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = pageHeaderUser();
    $user = User::factory()->create([
        'name' => 'Sophie Martin',
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
    $mailbox = EmailMailbox::factory()->create([
        'name' => 'Boîte support',
        'is_shared' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $user->getKey()])
        ->assertSee('Sophie Martin')
        ->assertSee('Dernière connexion');

    Livewire::actingAs($admin)
        ->test(EditMailbox::class, ['record' => $mailbox->getKey()])
        ->assertSee('Boîte support')
        ->assertSee('Partagée')
        ->assertSee('Dernière synchronisation');
});

test('l’en-tête d’une demande de contact suit son état de lecture', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = pageHeaderUser();
    $request = ContactRequest::create([
        'name' => 'Camille Test',
        'company' => 'Test SARL',
        'email' => 'camille@example.test',
        'request_type' => 'Autre demande',
        'message' => 'Bonjour, une question.',
        'read_at' => null,
    ]);

    Livewire::actingAs($admin)
        ->test(EditContactRequest::class, ['record' => $request->getKey()])
        ->assertSee('Camille Test')
        ->assertSee('Nouvelle');

    $request->forceFill(['read_at' => now()])->save();

    Livewire::actingAs($admin)
        ->test(EditContactRequest::class, ['record' => $request->getKey()])
        ->assertSee('Traitée');
});
