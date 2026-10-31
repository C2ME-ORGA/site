<?php

use App\Enums\CrmActivityType;
use App\Enums\CrmBook;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\User;
use App\Services\IcsCalendar;

function calendarFeedUser(UserRole $role = UserRole::Commercial, bool $active = true): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => $active,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function calendarFeedAccount(User $owner, array $attributes = []): CrmAccount
{
    return CrmAccount::factory()->create(array_merge([
        'owner_id' => $owner->id,
        'book' => CrmBook::BuildingCompanies,
        'name' => 'Chantier Calendrier',
        'address_line1' => '12 rue du Test',
        'address_line2' => null,
        'postal_code' => '59000',
        'city' => 'Lille',
        'country' => 'FR',
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function calendarFeedActivity(CrmAccount $account, User $owner, array $attributes = []): CrmActivity
{
    return CrmActivity::factory()->create(array_merge([
        'crm_account_id' => $account->id,
        'crm_contact_id' => null,
        'user_id' => $owner->id,
        'type' => CrmActivityType::Visit,
        'subject' => 'Visite calendrier',
        'body' => 'Compte rendu calendrier',
        'occurred_at' => null,
        'due_at' => now()->addDay()->setTime(14, 0),
        'remind_at' => now()->addDay()->setTime(13, 30),
        'completed_at' => null,
    ], $attributes));
}

test('le flux calendrier expose les activités et les relances avec un jeton valide', function (): void {
    $user = calendarFeedUser();
    $followUpAt = now()->addDays(2)->setTime(10, 0);
    $dueAt = now()->addDay()->setTime(14, 0);
    $remindAt = now()->addDay()->setTime(13, 30);

    $account = calendarFeedAccount($user, [
        'next_follow_up_at' => $followUpAt,
        'notes' => 'Relancer le bureau d’études',
    ]);
    $activity = calendarFeedActivity($account, $user, [
        'due_at' => $dueAt,
        'remind_at' => $remindAt,
    ]);

    calendarFeedActivity($account, $user, [
        'subject' => 'Activité terminée',
        'completed_at' => now(),
    ]);

    $token = $user->ensureCalendarToken();
    $response = $this->get('/calendrier/'.$token.'.ics');

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('BEGIN:VCALENDAR', false)
        ->assertSee('UID:activity-'.$activity->id.'@c2me.fr', false)
        ->assertSee('SUMMARY:Visite : Visite calendrier', false)
        ->assertSee('LOCATION:12 rue du Test', false)
        ->assertSee('BEGIN:VALARM', false)
        ->assertSee('TRIGGER;VALUE=DATE-TIME:'.$remindAt->copy()->utc()->format('Ymd\THis\Z'), false)
        ->assertSee('UID:follow-up-'.$account->id.'@c2me.fr', false)
        ->assertSee('SUMMARY:Relance : Chantier Calendrier', false)
        ->assertSee('DTSTART;VALUE=DATE:'.$followUpAt->format('Ymd'), false)
        ->assertSee('URL:'.route('filament.admin.resources.activities.edit', $activity), false)
        ->assertDontSee('Activité terminée');

    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('max-age=300');
});

test('le flux répond 304 lorsque le client possède déjà la version courante', function (): void {
    $user = calendarFeedUser();
    calendarFeedActivity(calendarFeedAccount($user), $user);
    $token = $user->ensureCalendarToken();

    $etag = $this->get('/calendrier/'.$token.'.ics')->headers->get('ETag');

    expect($etag)->toBeString();

    $this->get('/calendrier/'.$token.'.ics', ['If-None-Match' => (string) $etag])
        ->assertStatus(304);
});

test('un jeton inconnu ou un compte inactif renvoie 404', function (): void {
    $this->get('/calendrier/'.str_repeat('a', 48).'.ics')->assertNotFound();

    $inactive = calendarFeedUser(active: false);
    $token = $inactive->ensureCalendarToken();

    $this->get('/calendrier/'.$token.'.ics')->assertNotFound();
});

test('la régénération du jeton invalide immédiatement l’ancien flux', function (): void {
    $user = calendarFeedUser();
    $oldToken = $user->ensureCalendarToken();

    $this->get('/calendrier/'.$oldToken.'.ics')->assertOk();

    $newToken = $user->regenerateCalendarToken();

    expect($newToken)->not->toBe($oldToken);

    $this->get('/calendrier/'.$oldToken.'.ics')->assertNotFound();
    $this->get('/calendrier/'.$newToken.'.ics')->assertOk();
});

test('le flux respecte le périmètre des commerciaux', function (): void {
    $owner = calendarFeedUser();
    $other = calendarFeedUser();

    $ownerAccount = calendarFeedAccount($owner, ['name' => 'Compte A']);
    $otherAccount = calendarFeedAccount($other, ['name' => 'Compte B']);

    calendarFeedActivity($ownerAccount, $owner, ['subject' => 'Visite A']);
    calendarFeedActivity($otherAccount, $other, ['subject' => 'Visite B']);

    $this->get('/calendrier/'.$owner->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertSee('Visite A')
        ->assertDontSee('Visite B');

    $admin = calendarFeedUser(UserRole::Admin);

    $this->get('/calendrier/'.$admin->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertSee('Visite A')
        ->assertSee('Visite B');
});

test('les lignes ICS sont échappées et repliées à 75 octets', function (): void {
    $user = calendarFeedUser();
    $account = calendarFeedAccount($user);
    calendarFeedActivity($account, $user, [
        'subject' => str_repeat('Très longue activité, avec des virgules; et des points. ', 10),
        'body' => "Première ligne\nDeuxième ligne, avec des virgules",
    ]);

    $content = app(IcsCalendar::class)->forUser($user);

    expect($content)
        ->toContain('\\,')
        ->toContain('\\;')
        ->toContain('\\n');

    foreach (explode("\r\n", trim($content)) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});

test('la page calendrier propose l’abonnement et l’export', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin/calendar')
        ->assertOk()
        ->assertSee('S’abonner')
        ->assertSee('Exporter (.ics)')
        ->assertSee('Régénérer le lien');
});
