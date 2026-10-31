<?php

use App\Enums\UserRole;
use App\Filament\Pages\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function calendarEventUser(UserRole $role = UserRole::Commercial): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function calendarEventPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Rendez-vous perso',
        'all_day' => false,
        'starts_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
        'reminder_minutes' => 15,
        'location' => 'Bureau',
        'description' => 'Notes personnelles',
        'recurrence' => '',
        'recurrence_until' => null,
        'visibility' => 'private',
        'shared_user_ids' => [],
        'crm_account_id' => null,
        'crm_contact_id' => null,
    ], $overrides);
}

test('un événement privé n’apparaît que dans le flux de son créateur', function (): void {
    $owner = calendarEventUser();
    $other = calendarEventUser();

    CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Déjeuner privé',
        'starts_at' => now()->addDay()->setTime(12, 0),
        'ends_at' => now()->addDay()->setTime(13, 0),
        'all_day' => false,
        'share_with_all' => false,
    ]);

    $this->get('/calendrier/'.$owner->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertSee('SUMMARY:Déjeuner privé');

    $this->get('/calendrier/'.$other->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertDontSee('Déjeuner privé');
});

test('un événement partagé à tous ou à des utilisateurs choisis est visible', function (): void {
    $owner = calendarEventUser();
    $colleague = calendarEventUser();
    $outsider = calendarEventUser();

    CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Réunion équipe',
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(10, 0),
        'share_with_all' => true,
    ]);

    $selected = CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Point commercial',
        'starts_at' => now()->addDay()->setTime(11, 0),
        'ends_at' => now()->addDay()->setTime(12, 0),
        'share_with_all' => false,
    ]);

    $selected->sharedUsers()->sync([$colleague->id]);

    $this->get('/calendrier/'.$colleague->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertSee('Réunion équipe')
        ->assertSee('Point commercial');

    $this->get('/calendrier/'.$outsider->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertSee('Réunion équipe')
        ->assertDontSee('Point commercial');
});

test('les événements personnels sont enrichis dans le flux ICS', function (): void {
    $owner = calendarEventUser();
    $startsAt = now()->addDay()->setTime(14, 0);
    $endsAt = $startsAt->copy()->addMinutes(90);

    $event = CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Visite libre',
        'description' => 'Préparer les plans',
        'location' => '12 rue du Test, Lille',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'all_day' => false,
        'reminder_minutes' => 30,
        'share_with_all' => false,
    ]);

    $url = route('filament.admin.pages.calendar', ['date' => $startsAt->format('Y-m-d')]);

    $this->get('/calendrier/'.$owner->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertSee('UID:event-'.$event->id.'@c2me.fr', false)
        ->assertSee('SUMMARY:Visite libre', false)
        ->assertSee('TRIGGER:-PT30M', false)
        ->assertSee('LOCATION:12 rue du Test', false)
        ->assertSee('URL:'.$url, false)
        ->assertSee('DESCRIPTION:Préparer les plans', false);
});

test('les récurrences simples sont exportées en RRULE et apparaissent dans le calendrier', function (): void {
    $owner = calendarEventUser();
    $startsAt = now()->setTime(9, 0);

    $event = CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Point hebdomadaire',
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addHour(),
        'all_day' => false,
        'recurrence' => 'weekly',
        'recurrence_until' => $startsAt->copy()->addWeeks(3)->toDateString(),
    ]);

    $occurrences = $event->occurrencesBetween(
        $startsAt->toImmutable()->startOfWeek(CarbonImmutable::MONDAY),
        $startsAt->toImmutable()->addMonths(2),
    );

    expect($occurrences)->toHaveCount(4);

    $this->get('/calendrier/'.$owner->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertSee('RRULE:FREQ=WEEKLY;UNTIL='.$startsAt->copy()->addWeeks(3)->endOfDay()->utc()->format('Ymd\THis\Z'), false);
});

test('la page calendrier permet de créer, modifier et supprimer un événement', function (): void {
    $owner = calendarEventUser();

    $this->actingAs($owner);
    $this->get('/admin/calendar')->assertOk()->assertSee('Nouvel événement');

    Livewire::test(Calendar::class)
        ->call('createCalendarEvent', calendarEventPayload([
            'title' => 'Créé depuis le calendrier',
            'visibility' => 'selected',
            'shared_user_ids' => [calendarEventUser()->id],
        ]));

    $event = CalendarEvent::query()->sole();

    expect($event->title)->toBe('Créé depuis le calendrier')
        ->and($event->sharedUsers()->count())->toBe(1);

    Livewire::test(Calendar::class)
        ->call('editCalendarEvent', $event->id)
        ->assertSet('mountedActions.0.data.title', 'Créé depuis le calendrier');
    Livewire::test(Calendar::class)
        ->call('updateCalendarEvent', $event->id, calendarEventPayload([
            'title' => 'Modifié depuis le calendrier',
            'visibility' => 'private',
        ]));

    expect($event->refresh()->title)->toBe('Modifié depuis le calendrier')
        ->and($event->sharedUsers()->count())->toBe(0);

    $this->get('/admin/calendar?date='.$event->starts_at->format('Y-m-d'))
        ->assertOk()
        ->assertSee('Modifié depuis le calendrier');

    Livewire::test(Calendar::class)->call('deleteCalendarEvent', $event->id);

    expect(CalendarEvent::query()->count())->toBe(0);
});

test('les récurrences terminées ne sont pas chargées par le calendrier', function (): void {
    $owner = calendarEventUser();

    CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Ancienne récurrence terminée',
        'starts_at' => now()->subMonths(3)->setTime(9, 0),
        'ends_at' => now()->subMonths(3)->setTime(9, 30),
        'recurrence' => 'weekly',
        'recurrence_until' => now()->subMonths(2)->toDateString(),
    ]);

    CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Récurrence active',
        'starts_at' => now()->setTime(9, 0),
        'ends_at' => now()->setTime(9, 30),
        'recurrence' => 'weekly',
        'recurrence_until' => now()->addMonths(2)->toDateString(),
    ]);

    $this->actingAs($owner)
        ->get('/admin/calendar?date='.now()->format('Y-m-d'))
        ->assertOk()
        ->assertSee('Récurrence active')
        ->assertDontSee('Ancienne récurrence terminée');
});

test('le nombre d’occurrences calculées est plafonné par la configuration', function (): void {
    config(['crm.calendar.max_occurrences' => 3]);

    $owner = calendarEventUser();

    $event = CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Récurrence longue',
        'starts_at' => now()->setTime(9, 0),
        'ends_at' => now()->setTime(9, 30),
        'recurrence' => 'weekly',
    ]);

    $occurrences = $event->occurrencesBetween(
        now()->startOfDay()->toImmutable(),
        now()->addYears(2)->toImmutable(),
    );

    expect($occurrences)->toHaveCount(3);
});

test('les événements en journée entière utilisent des dates ICS', function (): void {
    $owner = calendarEventUser();
    $startDate = now()->addDays(3)->startOfDay();

    $event = CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Salon professionnel',
        'starts_at' => $startDate,
        'ends_at' => $startDate->copy()->addDay()->endOfDay(),
        'all_day' => true,
        'share_with_all' => true,
    ]);

    $this->get('/calendrier/'.$owner->ensureCalendarToken().'.ics')
        ->assertOk()
        ->assertSee('DTSTART;VALUE=DATE:'.$startDate->format('Ymd'), false)
        ->assertSee('DTEND;VALUE=DATE:'.$startDate->copy()->addDays(2)->format('Ymd'), false)
        ->assertSee('UID:event-'.$event->id.'@c2me.fr', false);
});

test('un événement partagé ne peut pas être modifié par un autre utilisateur', function (): void {
    $owner = calendarEventUser();
    $colleague = calendarEventUser();

    $event = CalendarEvent::query()->create([
        'user_id' => $owner->id,
        'title' => 'Partagé en lecture',
        'starts_at' => now()->addDay()->setTime(9, 0),
        'ends_at' => now()->addDay()->setTime(10, 0),
        'share_with_all' => true,
    ]);

    $this->actingAs($colleague);

    Livewire::test(Calendar::class)->call('deleteCalendarEvent', $event->id);

    expect(CalendarEvent::query()->find($event->id))->not->toBeNull();

    $this->get('/admin/calendar?date='.$event->starts_at->format('Y-m-d'))
        ->assertOk()
        ->assertSee('Partagé en lecture')
        ->assertDontSee('wire:click="deleteCalendarEvent('.$event->id.')"', false);
});
