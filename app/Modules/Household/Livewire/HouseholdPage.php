<?php

namespace App\Modules\Household\Livewire;

use App\Models\Household;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

#[Layout('components.layout')]
class HouseholdPage extends Component
{
    public function regenerateInviteCode(): void
    {
        $household = $this->household();
        abort_unless($household->roleFor(auth()->user()) === 'owner', 403);

        $household->regenerateInviteCode();
    }

    public function removeMember(int $userId): void
    {
        $household = $this->household();
        $target = $household->users->firstWhere('id', $userId);

        if (! $target) {
            return;
        }

        try {
            $household->removeMember(auth()->user(), $target);

            if ($target->id === auth()->id()) {
                Auth::setUser($target);
            }
        } catch (RuntimeException $e) {
            $this->addError('member', $e->getMessage());
        }
    }

    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirect(route('filament.admin.auth.login'), navigate: false);
    }

    private function household(): Household
    {
        $household = auth()->user()->currentHousehold()->with('users')->first();

        abort_if(is_null($household), 500, 'Akun ini tidak terhubung ke keluarga mana pun.');

        return $household;
    }

    public function render()
    {
        $household = $this->household();

        return view('livewire.household.household-page', [
            'household' => $household,
            'members' => $household->users,
            'role' => $household->roleFor(auth()->user()),
        ]);
    }
}
