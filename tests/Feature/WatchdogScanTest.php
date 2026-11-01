<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use MKWebDesign\FilamentWatchdog\Models\FileIntegrityCheck;
use MKWebDesign\FilamentWatchdog\Models\MalwareDetection;
use MKWebDesign\FilamentWatchdog\Models\SecurityAlert;
use MKWebDesign\FilamentWatchdog\Services\AlertService;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;

/**
 * Dossier de travail isolé : on ne hache pas tout le projet dans les tests.
 */
const WATCHDOG_FIXTURE_DIR = 'storage/framework/testing/watchdog';

/**
 * Le paquet calcule le chemin stocké avec `str_replace(base_path().'/', ...)` :
 * sur Linux il est relatif, sur Windows il reste absolu (séparateurs inclus).
 * On reproduit sa conversion pour que les assertions valent sur les deux
 * systèmes.
 */
function watchdogStoredPath(string $absolutePath): string
{
    return str_replace(base_path().'/', '', (string) realpath($absolutePath));
}

/**
 * @return Collection<int, SentMessage>
 */
function watchdogSentMessages(): Collection
{
    $transport = Mail::mailer()->getSymfonyTransport();

    if (! $transport instanceof ArrayTransport) {
        throw new RuntimeException('Le transport de test n’est pas le transport « array ».');
    }

    return $transport->messages();
}

function watchdogFixturePath(string $name): string
{
    return WATCHDOG_FIXTURE_DIR.'/'.$name;
}

beforeEach(function (): void {
    File::deleteDirectory(base_path(WATCHDOG_FIXTURE_DIR));
    File::ensureDirectoryExists(base_path(WATCHDOG_FIXTURE_DIR));

    config([
        'filament-watchdog.monitoring.enabled' => false,
        'filament-watchdog.monitoring.monitored_paths' => [WATCHDOG_FIXTURE_DIR],
        'filament-watchdog.monitoring.excluded_paths' => [],
        'filament-watchdog.malware_detection.enabled' => true,
        'filament-watchdog.alerts.enabled' => true,
        'filament-watchdog.alerts.email_enabled' => false,
        'filament-watchdog.alerts.email_recipients' => [],
        'filament-watchdog.alerts.rate_limiting.enabled' => false,
    ]);

    Cache::flush();
});

afterEach(function (): void {
    File::deleteDirectory(base_path(WATCHDOG_FIXTURE_DIR));
});

test('le scan ne fait rien sans --force quand la surveillance est désactivée', function (): void {
    $file = base_path(watchdogFixturePath('nouveau.php'));
    File::put($file, '<?php // fichier de test');

    artisanCommand($this, 'watchdog:scan')->assertExitCode(1);

    expect(FileIntegrityCheck::count())->toBe(0)
        ->and(SecurityAlert::count())->toBe(0)
        ->and(MalwareDetection::count())->toBe(0);
});

test('le scan avec --force contourne la désactivation et enregistre un nouveau fichier', function (): void {
    $file = base_path(watchdogFixturePath('nouveau.php'));
    File::put($file, '<?php // fichier de test');

    artisanCommand($this, 'watchdog:scan', ['--force' => true])->assertExitCode(0);

    $record = FileIntegrityCheck::query()->where('file_path', watchdogStoredPath($file))->first();

    expect($record)->not->toBeNull()
        ->and($record->getAttribute('status'))->toBe('new')
        ->and($record->getAttribute('file_hash'))->toBe(hash_file('sha256', $file))
        ->and(SecurityAlert::query()->where('alert_type', 'new_file_detected')->count())->toBe(1);
});

test('une modification de fichier est détectée', function (): void {
    $file = base_path(watchdogFixturePath('modifie.php'));
    File::put($file, '<?php // version 1');

    artisanCommand($this, 'watchdog:scan', ['--force' => true])->assertExitCode(0);

    File::put($file, '<?php // version 2 — contenu modifié');

    artisanCommand($this, 'watchdog:scan', ['--force' => true])->assertExitCode(0);

    $record = FileIntegrityCheck::query()->where('file_path', watchdogStoredPath($file))->first();

    expect($record?->getAttribute('status'))->toBe('modified')
        ->and(SecurityAlert::query()->where('alert_type', 'file_modified')->count())->toBe(1);
});

test('une suppression de fichier est détectée', function (): void {
    $relativePath = watchdogFixturePath('supprime.php');
    File::put(base_path($relativePath), '<?php // sera supprimé');

    FileIntegrityCheck::create([
        'file_path' => $relativePath,
        'file_hash' => hash('sha256', '<?php // sera supprimé'),
        'file_size' => 22,
        'last_modified' => now()->toDateTimeString(),
        'status' => 'clean',
    ]);

    File::delete(base_path($relativePath));

    artisanCommand($this, 'watchdog:scan', ['--force' => true])->assertExitCode(0);

    $record = FileIntegrityCheck::query()->where('file_path', $relativePath)->first();

    expect($record?->getAttribute('status'))->toBe('deleted')
        ->and(SecurityAlert::query()->where('alert_type', 'file_deleted')->count())->toBe(1);
});

test('une signature suspecte est détectée et alertée', function (): void {
    $file = base_path(watchdogFixturePath('suspect.php'));
    File::put($file, '<?php eval($_POST["code"]);');

    artisanCommand($this, 'watchdog:scan', ['--force' => true])->assertExitCode(0);

    $detection = MalwareDetection::query()->first();

    expect($detection)->not->toBeNull()
        ->and($detection->getAttribute('threat_type'))->toBe('php_eval')
        ->and($detection->getAttribute('status'))->toBe('detected')
        ->and($detection->getAttribute('risk_level'))->toBeIn(['high', 'critical'])
        ->and(SecurityAlert::query()->where('alert_type', 'malware_detected')->where('severity', 'critical')->count())->toBe(1);
});

test('le baseline enregistre les fichiers surveillés et fonctionne sans surveillance planifiée', function (): void {
    File::put(base_path(watchdogFixturePath('un.php')), '<?php // un');
    File::put(base_path(watchdogFixturePath('deux.php')), '<?php // deux');

    artisanCommand($this, 'watchdog:baseline')->assertExitCode(0);

    expect(FileIntegrityCheck::count())->toBe(2)
        ->and(FileIntegrityCheck::query()->where('status', 'clean')->count())->toBe(2)
        ->and(SecurityAlert::count())->toBe(0);
});

test('le baseline échoue explicitement quand aucun fichier n’est surveillé', function (): void {
    artisanCommand($this, 'watchdog:baseline')
        ->expectsOutputToContain('Aucun fichier surveillé')
        ->assertExitCode(1);

    expect(FileIntegrityCheck::count())->toBe(0);
});

test('les alertes respectent les destinataires configurés', function (): void {
    config([
        'filament-watchdog.alerts.email_enabled' => true,
        'filament-watchdog.alerts.email_recipients' => ['securite@example.test'],
    ]);

    app(AlertService::class)->createAlert('test', 'Alerte de test', 'Description', 'high');

    $messages = watchdogSentMessages();

    $recipients = array_map(
        fn ($address): string => $address->getAddress(),
        $messages->first()->getEnvelope()->getRecipients(),
    );

    expect($messages)->toHaveCount(1)
        ->and($recipients)->toContain('securite@example.test');
});

test('la limitation à une alerte par heure est appliquée', function (): void {
    config([
        'filament-watchdog.alerts.email_enabled' => true,
        'filament-watchdog.alerts.email_recipients' => ['securite@example.test'],
        'filament-watchdog.alerts.rate_limiting.enabled' => true,
        'filament-watchdog.alerts.rate_limiting.max_alerts_per_hour' => 1,
    ]);

    $alerts = app(AlertService::class);

    $alerts->createAlert('test', 'Première alerte', 'Description', 'high');
    $alerts->createAlert('test', 'Deuxième alerte', 'Description', 'critical');

    expect(watchdogSentMessages())->toHaveCount(1)
        ->and(SecurityAlert::count())->toBe(2);
});

test('aucun e-mail n’est envoyé quand les notifications sont désactivées', function (): void {
    config([
        'filament-watchdog.alerts.email_enabled' => false,
        'filament-watchdog.alerts.email_recipients' => ['securite@example.test'],
    ]);

    app(AlertService::class)->createAlert('test', 'Alerte silencieuse', 'Description', 'critical');

    expect(watchdogSentMessages())->toHaveCount(0);
});
