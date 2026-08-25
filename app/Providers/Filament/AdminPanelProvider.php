<?php

namespace App\Providers\Filament;

use App\Modules\Cooking\Providers\CookingPanelPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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
            ->id('admin')
            // Bukan /admin: URL itu sudah beredar sebagai halaman login CUSTOMER sebelum panel
            // dipisah, dan sekarang jadi redirect ke /login (routes/web.php). Satu URL tidak bisa
            // melayani dua guard sekaligus — customer di sana akan ditolak "credentials do not
            // match" padahal passwordnya benar.
            ->path('backoffice')
            ->authGuard('admin')
            ->login()
            // Admin non-owner tidak punya akses ke AdminResource, jadi halaman profil ini
            // satu-satunya cara dia mengganti nama/passwordnya sendiri.
            ->profile()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->plugins([
                new CookingPanelPlugin,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
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
            ]);
    }
}
