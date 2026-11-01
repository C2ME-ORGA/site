<?php

namespace App\Filament\Pages\Security;

use MKWebDesign\FilamentWatchdog\Pages\SecurityDashboard as BaseSecurityDashboard;
use MKWebDesign\FilamentWatchdog\Services\EmergencyLockdownService;

/**
 * Tableau de bord de sécurité (mkwebdesign/filament-watchdog-v5).
 *
 * Le paquet est en anglais et ne contrôle pas les accès : cette page le
 * rattache au groupe « Système » en français et le réserve aux administrateurs,
 * comme le journal d'audit et la visionneuse de logs.
 */
class SecurityDashboard extends BaseSecurityDashboard
{
    protected static ?string $navigationLabel = 'Sécurité';

    protected static ?string $title = 'Sécurité';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Système';
    }

    public static function getNavigationSort(): ?int
    {
        return 97;
    }

    public function getTitle(): string
    {
        return 'Sécurité';
    }

    public function getHeading(): string
    {
        return app(EmergencyLockdownService::class)->isLockdownActive()
            ? 'Sécurité — verrouillage d’urgence actif'
            : 'Sécurité';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $data = parent::getViewData();

        /*
         * Le scan est planifié par l'application (routes/console.php) et non
         * par le paquet : `monitoring.enabled` reste à false pour désactiver
         * sa tâche toutes les minutes, mais la surveillance est bien active.
         */
        $data['systemStatus']['fileMonitoring'] = true;

        return $data;
    }
}
