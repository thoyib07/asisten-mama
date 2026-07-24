<?php

namespace App\Filament\Pages\Auth;

use App\Models\Household;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

class Register extends BaseRegister
{
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        /** @var User $user */
        $user = User::create($data);

        Household::createWithOwner($user, "Keluarga {$user->name}");

        return $user;
    }
}
