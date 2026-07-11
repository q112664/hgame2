<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Games\GameResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('hgame')
            ->homeUrl(fn (): string => GameResource::getUrl(panel: 'admin'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigationGroups([
                NavigationGroup::make('Content'),
                NavigationGroup::make('Taxonomy')->collapsed(),
                NavigationGroup::make('Download settings')->collapsed(),
                NavigationGroup::make('Settings')->collapsed(),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            ])
            ->renderHook(
                'panels::head.end',
                fn (): string => <<<'HTML'
                    <style>
                        .screenshots-upload-grid .filepond--item {
                            width: calc(20% - 0.5em);
                        }

                        @media (max-width: 1024px) {
                            .screenshots-upload-grid .filepond--item {
                                width: calc(25% - 0.5em);
                            }
                        }

                        @media (max-width: 640px) {
                            .screenshots-upload-grid .filepond--item {
                                width: calc(50% - 0.5em);
                            }
                        }
                    </style>
                    HTML,
            );
    }
}
