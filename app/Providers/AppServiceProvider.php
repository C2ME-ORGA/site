<?php

namespace App\Providers;

use App\Contracts\InboundFetcher;
use App\Observers\AuditObserver;
use App\Policies\ActivityLogPolicy;
use App\Policies\FileIntegrityCheckPolicy;
use App\Policies\MalwareDetectionPolicy;
use App\Policies\SecurityAlertPolicy;
use App\Services\Mail\ImapInboundFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use MKWebDesign\FilamentWatchdog\Models\ActivityLog;
use MKWebDesign\FilamentWatchdog\Models\FileIntegrityCheck;
use MKWebDesign\FilamentWatchdog\Models\MalwareDetection;
use MKWebDesign\FilamentWatchdog\Models\SecurityAlert;
use MKWebDesign\FilamentWatchdog\Services\ActivityMonitoringService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InboundFetcher::class, ImapInboundFetcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureWatchdog();

        foreach (array_keys(AuditObserver::AUDITED) as $model) {
            $model::observe(AuditObserver::class);
        }
    }

    /**
     * Surveillance de sécurité : accès réservé aux administrateurs et journal
     * des connexions.
     *
     * Les politiques rendent les écrans du paquet en lecture seule ; le journal
     * d'activité n'est alimenté par aucun listener dans le paquet, d'où le
     * branchement explicite sur les événements d'authentification.
     */
    protected function configureWatchdog(): void
    {
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::policy(FileIntegrityCheck::class, FileIntegrityCheckPolicy::class);
        Gate::policy(MalwareDetection::class, MalwareDetectionPolicy::class);
        Gate::policy(SecurityAlert::class, SecurityAlertPolicy::class);

        Event::listen(Login::class, function (Login $event): void {
            // Hors requête web (CLI, file d'attente), ni session ni IP : rien à
            // journaliser, et la colonne `ip_address` est obligatoire.
            if (! request()->hasSession() || request()->ip() === null) {
                return;
            }

            $email = data_get($event->user, 'email');

            if (is_string($email) && $email !== '') {
                app(ActivityMonitoringService::class)->logSuccessfulLogin($email);
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            if (! request()->hasSession() || request()->ip() === null) {
                return;
            }

            $email = data_get($event->credentials, 'email');

            app(ActivityMonitoringService::class)->logFailedLogin(
                is_string($email) ? $email : '',
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
