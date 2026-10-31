<?php

use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;

test('seule la base de données est incluse dans les archives', function (): void {
    expect(config('backup.backup.source.databases'))->toBe(['mysql'])
        ->and(config('backup.backup.source.files.include'))->toBe([])
        ->and(config('backup.backup.destination.filename_prefix'))->toBe('c2me-');
});

test('les archives sont stockées sur un disque privé', function (): void {
    $disk = config('backup.backup.destination.disks.0');

    expect($disk)->toBe('backups')
        ->and(config("filesystems.disks.{$disk}.root"))->toBe(storage_path('app/backups'))
        ->and(config('backup.backup.temporary_directory'))->toBe(storage_path('app/backup-temp'));
});

test('le chiffrement des archives dépend du mot de passe', function (): void {
    $password = config('backup.backup.password');
    $encryption = config('backup.backup.encryption');

    expect($encryption)->toBe($password ? 'default' : 'none');
});

test('seuls les échecs de sauvegarde sont notifiés', function (): void {
    $notifications = config('backup.notifications.notifications');

    expect($notifications)->toHaveCount(6)
        ->and($notifications[BackupHasFailedNotification::class])->toBe(['mail'])
        ->and($notifications[BackupWasSuccessfulNotification::class])->toBe([]);
});

test('la sauvegarde quotidienne ne concerne que la base', function (): void {
    artisanCommand($this, 'schedule:list')
        ->expectsOutputToContain('backup:run --only-db')
        ->assertSuccessful();
});
