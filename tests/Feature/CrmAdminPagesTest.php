<?php

use App\Enums\CrmActivityType;
use App\Enums\CrmBook;
use App\Enums\UserRole;
use App\Filament\Resources\PrescriberAccounts\PrescriberAccountResource;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crmAdmin(): User
{
    return User::factory()->create([
        'email' => config('site.admin.allowed_emails')[0],
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

test('les pages du CRM s’ouvrent pour un administrateur', function (string $url): void {
    $this->actingAs(crmAdmin())
        ->get($url)
        ->assertOk();
})->with([
    '/admin/users',
    '/admin/mailboxes',
    '/admin/calendar',
    '/admin/prescriber-accounts',
    '/admin/industrial-accounts',
    '/admin/building-company-accounts',
    '/admin/quotes',
    '/admin/activities',
    '/admin/emails',
]);

test('le calendrier affiche les relances du mois', function (): void {
    $admin = crmAdmin();

    $account = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $admin->id,
    ]);

    CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'type' => CrmActivityType::FollowUp,
        'subject' => 'Relancer le bureau d’études',
        'due_at' => now()->startOfMonth()->addDays(3)->setTime(9, 0),
        'completed_at' => null,
    ]);

    $this->actingAs($admin)
        ->get('/admin/calendar?month='.now()->format('Y-m'))
        ->assertOk()
        ->assertSee('Relancer le bureau d’études');
});

test('la page utilisateurs s’ouvre et liste les comptes', function (): void {
    $admin = crmAdmin();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertSee($admin->name);
});

test('le calendrier bascule entre les vues mois, semaine et jour', function (string $mode): void {
    $admin = crmAdmin();

    $account = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $admin->id,
    ]);

    $dueAt = now()->setTime(10, 30);

    CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'type' => CrmActivityType::FollowUp,
        'subject' => 'Relancer le client Alpha',
        'due_at' => $dueAt,
        'completed_at' => null,
    ]);

    $this->actingAs($admin)
        ->get('/admin/calendar?view='.$mode.'&date='.$dueAt->format('Y-m-d'))
        ->assertOk()
        ->assertSee('Relancer le client Alpha');
})->with(['month', 'week', 'day']);

test('un carnet expose ses contacts, affaires et devis', function (): void {
    $admin = crmAdmin();

    $account = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $admin->id,
    ]);

    CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'first_name' => 'Jean',
        'last_name' => 'Martinet',
    ]);

    CrmOpportunity::factory()->create(['crm_account_id' => $account->id]);
    Quote::factory()->create(['crm_account_id' => $account->id]);

    // Les relation managers sont déclarés sur la ressource abstraite des carnets.
    expect(PrescriberAccountResource::getRelations())
        ->toHaveCount(3);

    // Sur la page d'édition, chaque onglet est présent et le premier est rendu.
    $this->actingAs($admin)
        ->get("/admin/prescriber-accounts/{$account->id}/edit")
        ->assertOk()
        ->assertSee('Contacts')
        ->assertSee('Affaires')
        ->assertSee('Devis')
        ->assertSee('Martinet');
});

test('la fiche d’un compte s’ouvre en lecture', function (): void {
    $admin = crmAdmin();

    $account = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $admin->id,
        'name' => 'Cabinet Archi Ouest',
    ]);

    $this->actingAs($admin)
        ->get("/admin/prescriber-accounts/{$account->id}")
        ->assertOk()
        ->assertSee('Cabinet Archi Ouest')
        ->assertSee('Identité')
        ->assertSee('Coordonnées');
});

test('la fiche affiche le logo de la société', function (): void {
    config()->set('services.logo_dev.publishable_key', 'pk_test_123');

    $admin = crmAdmin();

    $account = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $admin->id,
        'name' => 'Cabinet Archi Ouest',
        'website' => 'https://www.archi-ouest.fr',
    ]);

    $this->actingAs($admin)
        ->get("/admin/prescriber-accounts/{$account->id}")
        ->assertOk()
        ->assertSee('img.logo.dev/archi-ouest.fr', false);
});

test('la boîte de réception liste les messages entrants', function (): void {
    $admin = crmAdmin();

    $mailbox = EmailMailbox::create([
        'name' => 'Commercial',
        'email' => 'commercial@c2me.fr',
        'is_shared' => true,
        'imap_host' => 'imap.orange.fr',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'commercial@c2me.fr',
        'imap_password' => 'secret',
        'smtp_host' => 'smtp.orange.fr',
        'smtp_port' => 465,
        'smtp_encryption' => 'ssl',
        'smtp_username' => 'commercial@c2me.fr',
        'smtp_password' => 'secret',
        'is_active' => true,
    ]);

    EmailMessage::create([
        'email_mailbox_id' => $mailbox->id,
        'uid' => '1',
        'from_email' => 'jean@usine.fr',
        'from_name' => 'Jean Martin',
        'to_recipients' => [['email' => 'commercial@c2me.fr', 'name' => null]],
        'subject' => 'Objet du message',
        'body_text' => 'Contenu du message',
        'received_at' => now(),
        'is_read' => false,
        'is_sent' => false,
        'folder' => 'INBOX',
    ]);

    $this->actingAs($admin)
        ->get('/admin/emails')
        ->assertOk()
        ->assertSee('Objet du message');
});

test('un commercial ne voit pas la fiche d’un compte qui n’est pas le sien', function (): void {
    $owner = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    $other = User::factory()->create(['role' => UserRole::Commercial, 'is_active' => true]);

    $account = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $owner->id,
    ]);

    $this->actingAs($other)
        ->get("/admin/prescriber-accounts/{$account->id}")
        ->assertNotFound();
});

test('les boutons de navigation du calendrier sont cohérents avec la vue', function (): void {
    $this->actingAs(crmAdmin())
        ->get('/admin/calendar?view=day&date=2026-10-19')
        ->assertOk()
        ->assertSee('Aujourd’hui')
        ->assertSee('view=month', false)
        ->assertSee('view=week', false)
        ->assertSee('view=day', false);
});
