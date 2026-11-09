<?php

namespace App\Providers\Filament;

use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Data\AuthPageConfig;
use Caresome\FilamentAuthDesigner\Enums\MediaPosition;
use Caresome\FilamentAuthDesigner\View\AuthDesignerRenderHook;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBasePlugin;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Base documentaire utilisateurs du CRM.
 *
 * Le paquet guava/filament-knowledge-base impose un panneau Filament dédié :
 * il remplace la navigation du panneau par l'arborescence des fichiers
 * Markdown de `docs/knowledge-base/fr`.
 */
class KnowledgeBasePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('knowledge-base')
            ->path('aide')
            ->login()
            ->brandName('C2ME — Aide')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::hex('#0e8577'),
            ])
            ->plugins([
                KnowledgeBasePlugin::make(),
                AuthDesignerPlugin::make()
                    ->login(fn (AuthPageConfig $config): AuthPageConfig => $config
                        ->media(asset('images/auth/login-bg.webp'), alt: 'Charpente métallique réalisée par C2ME')
                        ->mediaPosition(MediaPosition::Left)
                        ->mediaSize('50%')
                        ->renderHook(
                            AuthDesignerRenderHook::MediaOverlay,
                            fn () => view('filament.auth.branding', ['label' => 'Base documentaire']),
                        ))
                    ->themeToggle(),
            ])
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): View => view('filament.auth.mobile-logo'),
            )
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
