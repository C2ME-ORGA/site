<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EditProfile;
use App\Filament\Pages\Security\SecurityDashboard;
use App\Filament\Widgets\ActivitiesByTypeChart;
use App\Filament\Widgets\CommercialStatsOverview;
use App\Filament\Widgets\CrmStatsOverview;
use App\Filament\Widgets\MonthlyTargetOverview;
use App\Filament\Widgets\PipelineByBookChart;
use App\Filament\Widgets\QuoteConversionGauge;
use App\Filament\Widgets\QuotesToFollowUp;
use App\Filament\Widgets\RevenueTrendChart;
use App\Filament\Widgets\TopAccountsPipelineChart;
use App\Filament\Widgets\TopOpenOpportunities;
use App\Filament\Widgets\UpcomingActivities;
use Boquizo\FilamentLogViewer\FilamentLogViewerPlugin;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Data\AuthPageConfig;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Caresome\FilamentAuthDesigner\View\AuthDesignerRenderHook;
use Elemind\FilamentECharts\FilamentEChartsPlugin;
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
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBaseCompanionPlugin;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MKWebDesign\FilamentWatchdog\Resources\ActivityLogResource;
use MKWebDesign\FilamentWatchdog\Resources\FileIntegrityResource;
use MKWebDesign\FilamentWatchdog\Resources\MalwareDetectionResource;
use MKWebDesign\FilamentWatchdog\Resources\SecurityAlertResource;
use MortalKiller\FilamentPageHeader\PageHeaderPlugin;
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->databaseNotifications()
            ->colors([
                'primary' => Color::hex('#0e8577'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                SecurityDashboard::class,
            ])
            ->resources([
                SecurityAlertResource::class,
                FileIntegrityResource::class,
                MalwareDetectionResource::class,
                ActivityLogResource::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                CrmStatsOverview::class,
                CommercialStatsOverview::class,
                MonthlyTargetOverview::class,
                RevenueTrendChart::class,
                PipelineByBookChart::class,
                TopAccountsPipelineChart::class,
                QuoteConversionGauge::class,
                ActivitiesByTypeChart::class,
                TopOpenOpportunities::class,
                QuotesToFollowUp::class,
                UpcomingActivities::class,
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.topbar.inbox'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.topbar.changelog'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => (Vite::isRunningHot() || is_file(public_path('build/manifest.json'))
                    ? (string) Vite::__invoke('resources/js/echo.js')
                    : '')
                    .'<link rel="manifest" href="/manifest.webmanifest"><meta name="theme-color" content="#0e8577">',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => '<script src="/js/c2me-pwa.js" defer></script>',
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): View => view('filament.auth.mobile-logo'),
            )
            /*
             * Changelog Olvy : la configuration doit être déclarée avant le
             * script externe (chargé en `defer`). Le widget n'est injecté que
             * pour les utilisateurs connectés, pas sur l'écran de connexion.
             */
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => auth()->check()
                    ? '<script>var OlvyConfig = { workspaceAlias: "c2meapp" };</script>'
                        .'<script type="text/javascript" src="https://app.olvy.co/scriptV3.js" defer></script>'
                    : '',
            )
            ->navigationItems([
                NavigationItem::make('Mode terrain')
                    ->group('CRM')
                    ->sort(90)
                    ->url('/terrain')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile),
                NavigationItem::make('Alertes de sécurité')
                    ->group('Système')
                    ->sort(98)
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                    ->url(fn (): string => SecurityAlertResource::getUrl('index', panel: 'admin')),
                NavigationItem::make('Intégrité des fichiers')
                    ->group('Système')
                    ->sort(99)
                    ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                    ->url(fn (): string => FileIntegrityResource::getUrl('index', panel: 'admin')),
                NavigationItem::make('Détections de malwares')
                    ->group('Système')
                    ->sort(100)
                    ->icon(Heroicon::OutlinedBugAnt)
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                    ->url(fn (): string => MalwareDetectionResource::getUrl('index', panel: 'admin')),
                NavigationItem::make('Journal d’activité')
                    ->group('Système')
                    ->sort(101)
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                    ->url(fn (): string => ActivityLogResource::getUrl('index', panel: 'admin')),
            ])
            /*
             * Page de connexion : photo de chantier à gauche, formulaire à droite,
             * logo de l'entreprise sur la photo et bascule de thème.
             */
            ->plugins([
                AppVersionPlugin::make(),
                AuthDesignerPlugin::make()
                    ->login(fn (AuthPageConfig $config): AuthPageConfig => $config
                        ->media(asset('images/auth/login-bg.webp'), alt: 'Charpente métallique réalisée par C2ME')
                        ->mediaPosition(MediaPosition::Left)
                        ->mediaSize('50%')
                        ->renderHook(
                            AuthDesignerRenderHook::MediaOverlay,
                            fn () => view('filament.auth.branding', ['label' => 'Espace administration']),
                        ))
                    ->themeToggle(),
                FilamentEChartsPlugin::make(),
                PageHeaderPlugin::make()->compact(),
                KnowledgeBaseCompanionPlugin::make()
                    ->knowledgeBasePanelId('knowledge-base')
                    ->modalPreviews(),
                FilamentLogViewerPlugin::make()
                    ->navigationGroup('Système')
                    ->navigationSort(95)
                    ->timezone('Europe/Paris')
                    ->authorize(fn (): bool => auth()->user()?->isAdmin() === true),
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
