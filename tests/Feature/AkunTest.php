<?php

use App\Livewire\Akun;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('lets a customer rename themselves', function () {
    $user = makeHouseholdUser('Rina');

    Livewire::actingAs($user)
        ->test(Akun::class)
        ->set('name', 'Rina Wijaya')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Rina Wijaya');
});

it('lets a customer change their password', function () {
    $user = makeHouseholdUser('Rina');
    $user->forceFill(['password' => 'lama12345'])->save();

    Livewire::actingAs($user)
        ->test(Akun::class)
        ->set('current_password', 'lama12345')
        ->set('password', 'baru123456')
        ->set('password_confirmation', 'baru123456')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('baru123456', $user->fresh()->password))->toBeTrue();
});

it('rejects a password change with the wrong current password', function () {
    $user = makeHouseholdUser('Rina');
    $user->forceFill(['password' => 'lama12345'])->save();

    Livewire::actingAs($user)
        ->test(Akun::class)
        ->set('current_password', 'salah12345')
        ->set('password', 'baru123456')
        ->set('password_confirmation', 'baru123456')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);

    expect(Hash::check('lama12345', $user->fresh()->password))->toBeTrue();
});

it('logs the customer out to the app panel login, not the admin one', function () {
    $user = makeHouseholdUser('Rina');

    Livewire::actingAs($user)
        ->test(Akun::class)
        ->call('logout')
        ->assertRedirect('/login');

    expect(auth()->check())->toBeFalse();
});

it('requires authentication', function () {
    $this->get('/akun')->assertRedirect('/login');
});
