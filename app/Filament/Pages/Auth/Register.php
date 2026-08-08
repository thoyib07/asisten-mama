<?php

namespace App\Filament\Pages\Auth;

use App\Models\Household;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getInviteCodeFormComponent(),
        ]);
    }

    protected function getInviteCodeFormComponent(): Component
    {
        return TextInput::make('invite_code')
            ->label('Kode undangan keluarga (opsional)')
            ->helperText('Sudah punya kode dari anggota keluarga lain? Masukkan di sini untuk gabung ke keluarga yang sama.')
            ->nullable()
            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                if (blank($value)) {
                    return;
                }

                if (! Household::findByInviteCode($value)) {
                    $fail('Kode undangan tidak ditemukan.');
                }
            });
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $inviteCode = $data['invite_code'] ?? null;
        unset($data['invite_code']);

        /** @var User $user */
        $user = User::create($data);

        if ($inviteCode) {
            if (! Household::joinWithCode($user, $inviteCode)) {
                throw ValidationException::withMessages([
                    'data.invite_code' => 'Kode undangan sudah tidak berlaku, coba lagi.',
                ]);
            }
        } else {
            Household::createWithOwner($user, "Keluarga {$user->name}");
        }

        return $user;
    }
}
