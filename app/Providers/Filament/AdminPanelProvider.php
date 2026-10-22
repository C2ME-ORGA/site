<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EditProfile;
use App\Filament\Widgets\CommercialStatsOverview;
use App\Filament\Widgets\CrmStatsOverview;
use App\Filament\Widgets\MonthlyTargetOverview;
use App\Filament\Widgets\PipelineByBookChart;
use App\Filament\Widgets\QuotesToFollowUp;
use App\Filament\Widgets\TopOpenOpportunities;
use App\Filament\Widgets\UpcomingActivities;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Vaslv\FilamentAppVersion\AppVersionPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(EditProfile::class)
            ->brandName('C2ME - Administration')
            ->databaseNotifications()
            ->colors([
                'primary' => Color::hex('#0e8577'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                CrmStatsOverview::class,
                CommercialStatsOverview::class,
                MonthlyTargetOverview::class,
                PipelineByBookChart::class,
                TopOpenOpportunities::class,
                QuotesToFollowUp::class,
                UpcomingActivities::class,
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.topbar.inbox'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="manifest" href="/manifest.webmanifest"><meta name="theme-color" content="#0e8577">',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => '<script src="/js/c2me-pwa.js" defer></script>',
            )
            ->navigationItems([
                NavigationItem::make('Mode terrain')
                    ->group('CRM')
                    ->sort(90)
                    ->url('/terrain')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile),
            ])
            ->plugins([
                AppVersionPlugin::make(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
