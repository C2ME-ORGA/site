<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\FollowUpReminderStage;
use App\Enums\UserRole;
use App\Mail\FollowUpDigestMail;
use App\Models\CrmAccount;
use App\Models\CrmFollowUpReminder;
use App\Models\User;
use App\Notifications\FollowUpDigest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 08:00:00'));
});

test('la veille, le propriétaire et les administrateurs reçoivent un rappel', function (): void {
    Notification::fake();
    Mail::fake();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $account = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->addDay()->setTime(9, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'day-before'])
        ->assertSuccessful();

    $reminders = CrmFollowUpReminder::query()->get();

    expect($reminders)->toHaveCount(2)
        ->and($reminders->pluck('user_id')->all())->toContain($admin->id)
        ->and($reminders->pluck('user_id')->all())->toContain($commercial->id)
        ->and($reminders->every(fn (CrmFollowUpReminder $reminder): bool => $reminder->stage === FollowUpReminderStage::DayBefore))->toBeTrue()
        ->and($reminders->every(fn (CrmFollowUpReminder $reminder): bool => $reminder->database_sent_at !== null && $reminder->mail_sent_at !== null))->toBeTrue();

    expect($reminders->pluck('crm_account_id')->unique()->all())->toBe([$account->id]);

    Notification::assertSentTo($commercial, FollowUpDigest::class);
    Notification::assertSentTo($admin, FollowUpDigest::class);
    Mail::assertSentCount(2);
    Mail::assertSent(FollowUpDigestMail::class, fn (FollowUpDigestMail $mail): bool => $mail->hasTo($commercial->email));
    Mail::assertSent(FollowUpDigestMail::class, fn (FollowUpDigestMail $mail): bool => $mail->hasTo($admin->email));
});

test('un rappel déjà envoyé n’est jamais dupliqué', function (): void {
    Notification::fake();
    Mail::fake();

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->setTime(14, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(1);
    Notification::assertSentTimes(FollowUpDigest::class, 1);
    Mail::assertSentCount(1);
});

test('les comptes inactifs et supprimés sont ignorés', function (): void {
    Notification::fake();
    Mail::fake();

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'status' => AccountStatus::Inactive,
        'next_follow_up_at' => now()->addDay()->setTime(9, 0),
    ]);

    $deleted = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->addDay()->setTime(10, 0),
    ]);
    $deleted->delete();

    $valid = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->addDay()->setTime(11, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'day-before'])
        ->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(1)
        ->and(CrmFollowUpReminder::query()->first()?->crm_account_id)->toBe($valid->id);
});

test('un compte sans propriétaire notifie les administrateurs', function (): void {
    Notification::fake();
    Mail::fake();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => null,
        'next_follow_up_at' => now()->setTime(14, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    $reminder = CrmFollowUpReminder::query()->sole();

    expect($reminder->user_id)->toBe($admin->id)
        ->and($reminder->stage)->toBe(FollowUpReminderStage::DueDay);

    Notification::assertSentTo($admin, FollowUpDigest::class);
});

test('un propriétaire inactif bascule vers les administrateurs', function (): void {
    Notification::fake();
    Mail::fake();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $inactiveOwner = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => false,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $inactiveOwner->id,
        'next_follow_up_at' => now()->setTime(14, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(1)
        ->and(CrmFollowUpReminder::query()->first()?->user_id)->toBe($admin->id);

    Notification::assertNotSentTo($inactiveOwner, FollowUpDigest::class);
});

test('le commercial ne reçoit que ses comptes et l’admin reçoit le récapitulatif global', function (): void {
    Notification::fake();
    Mail::fake();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $firstCommercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $secondCommercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $firstAccount = CrmAccount::factory()->create([
        'owner_id' => $firstCommercial->id,
        'next_follow_up_at' => now()->setTime(9, 0),
    ]);

    $secondAccount = CrmAccount::factory()->create([
        'owner_id' => $secondCommercial->id,
        'next_follow_up_at' => now()->setTime(10, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(4);

    Notification::assertSentTo(
        $firstCommercial,
        FollowUpDigest::class,
        fn (FollowUpDigest $notification): bool => $notification->reminders->count() === 1
            && $notification->reminders->first()?->crm_account_id === $firstAccount->id,
    );

    Notification::assertSentTo(
        $admin,
        FollowUpDigest::class,
        fn (FollowUpDigest $notification): bool => $notification->reminders->count() === 2
            && $notification->reminders->pluck('crm_account_id')->sort()->values()->all()
                === collect([$firstAccount->id, $secondAccount->id])->sort()->values()->all(),
    );

    Notification::assertSentTimes(FollowUpDigest::class, 3);
});

test('le mode simulation ne crée ni ligne ni envoi', function (): void {
    Notification::fake();
    Mail::fake();

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->addDay()->setTime(9, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', [
        '--stage' => 'day-before',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(0);
    Notification::assertNothingSent();
    Mail::assertNothingSent();
});

test('un compte en retard est rattrapé une seule fois', function (): void {
    Notification::fake();
    Mail::fake();

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->subDays(2)->setTime(14, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(1);
    Notification::assertSentTimes(FollowUpDigest::class, 1);
});

test('une nouvelle échéance génère un nouveau rappel', function (): void {
    Notification::fake();
    Mail::fake();

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $account = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->addDay()->setTime(9, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'day-before'])
        ->assertSuccessful();

    $this->travelTo(CarbonImmutable::parse('2026-10-22 17:00:00'));

    $account->update([
        'next_follow_up_at' => now()->addDay()->setTime(9, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'day-before'])
        ->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(2)
        ->and(CrmFollowUpReminder::query()->distinct()->count('due_at'))->toBe(2);
});

test('l’option user limite le digest à cet utilisateur', function (): void {
    Notification::fake();
    Mail::fake();

    $firstAdmin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $secondAdmin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->addDay()->setTime(9, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', [
        '--stage' => 'day-before',
        '--user' => (string) $firstAdmin->id,
    ])->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(1)
        ->and(CrmFollowUpReminder::query()->sole()->user_id)->toBe($firstAdmin->id);

    Notification::assertSentTo($firstAdmin, FollowUpDigest::class);
    Notification::assertNotSentTo($secondAdmin, FollowUpDigest::class);
    Notification::assertNotSentTo($commercial, FollowUpDigest::class);
});

test('force renvoie un rappel déjà envoyé sans créer de doublon', function (): void {
    Notification::fake();
    Mail::fake();

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->setTime(14, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    artisanCommand($this, 'crm:send-follow-up-reminders', [
        '--stage' => 'due-day',
        '--force' => true,
    ])->assertSuccessful();

    expect(CrmFollowUpReminder::query()->count())->toBe(1);
    Notification::assertSentTimes(FollowUpDigest::class, 2);
    Mail::assertSentCount(2);
});

test('le digest e-mail contient le compte et le lien vers les relances', function (): void {
    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $account = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->setTime(14, 0),
    ]);

    $reminder = CrmFollowUpReminder::query()->create([
        'crm_account_id' => $account->id,
        'user_id' => $commercial->id,
        'due_at' => $account->next_follow_up_at,
        'stage' => FollowUpReminderStage::DueDay->value,
    ]);

    $reminder->load(['account.owner', 'account.contacts', 'user']);

    $mail = new FollowUpDigestMail($commercial, collect([$reminder]), FollowUpReminderStage::DueDay);
    $html = $mail->render();

    expect($html)->toContain($account->name)
        ->toContain('/admin/relances?stage=due_day');
});

test('un échec e-mail conserve la notification in-app et permet le retry', function (): void {
    Notification::fake();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP indisponible'));

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->setTime(14, 0),
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', ['--stage' => 'due-day'])
        ->assertSuccessful();

    $reminder = CrmFollowUpReminder::query()->sole();

    expect($reminder->database_sent_at)->not->toBeNull()
        ->and($reminder->mail_sent_at)->toBeNull()
        ->and($reminder->attempts)->toBe(1)
        ->and($reminder->last_error)->toContain('SMTP indisponible');

    Notification::assertSentTo($commercial, FollowUpDigest::class);
});

test('le mode retry ne renvoie que le canal encore en échec', function (): void {
    Notification::fake();
    Mail::fake();

    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $account = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->subDay()->setTime(14, 0),
    ]);

    $reminder = CrmFollowUpReminder::query()->create([
        'crm_account_id' => $account->id,
        'user_id' => $commercial->id,
        'due_at' => $account->next_follow_up_at,
        'stage' => FollowUpReminderStage::DueDay->value,
        'database_sent_at' => now()->subHour(),
        'mail_sent_at' => null,
        'attempts' => 1,
        'last_error' => 'SMTP indisponible',
    ]);

    artisanCommand($this, 'crm:send-follow-up-reminders', [
        '--stage' => 'due-day',
        '--retry' => true,
    ])->assertSuccessful();

    $reminder->refresh();

    expect($reminder->mail_sent_at)->not->toBeNull()
        ->and($reminder->attempts)->toBe(1);

    Notification::assertNothingSent();
    Mail::assertSentCount(1);
});
