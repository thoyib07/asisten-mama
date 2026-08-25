<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Register;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel ini sengaja kosong dari resource dan page — tugasnya cuma satu: jadi pintu
 * auth customer (/login, /register, /password-reset). Halaman customer sendiri adalah
 * Livewire biasa di luar Filament, dan LoginResponse melempar ke sana.
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            // Path kosong supaya URL-nya /login dan /register, bukan /masuk/login. Aman karena
            // Filament (Laravel 13+) melewatkan route home panel kalau GET / sudah terdaftar —
            // dan Beranda sudah memegangnya lewat routes/web.php.
            ->path('')
            ->authGuard('web')
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->colors([
                'primary' => Color::hex('#00B14F'),
            ])
            ->pages([])
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
            ]);
    }
}
