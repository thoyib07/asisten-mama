<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\LoginResponse as FilamentLoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Panel `app` cuma pintu auth — customer yang berhasil login dilempar ke Beranda,
 * bukan ke halaman Filament. Panel lain (admin) pakai perilaku bawaan.
 */
class LoginResponse extends FilamentLoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentOrDefaultPanel()?->getId() !== 'app') {
            return parent::toResponse($request);
        }

        return redirect()->intended(route('beranda'));
    }
}
