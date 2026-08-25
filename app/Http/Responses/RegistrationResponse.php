<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\RegistrationResponse as FilamentRegistrationResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class RegistrationResponse extends FilamentRegistrationResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentOrDefaultPanel()?->getId() !== 'app') {
            return parent::toResponse($request);
        }

        return redirect()->intended(route('beranda'));
    }
}
