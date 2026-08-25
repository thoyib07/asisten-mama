<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('authenticates an admin on the admin guard only', function () {
    $admin = Admin::factory()->create(['email' => 'ops@asisten.test']);

    expect(Auth::guard('admin')->attempt([
        'email' => 'ops@asisten.test',
        'password' => 'password',
    ]))->toBeTrue();

    expect(Auth::guard('admin')->id())->toBe($admin->id);
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('does not let a customer authenticate on the admin guard', function () {
    User::factory()->create(['email' => 'mama@asisten.test', 'password' => 'password']);

    expect(Auth::guard('admin')->attempt([
        'email' => 'mama@asisten.test',
        'password' => 'password',
    ]))->toBeFalse();
});

it('hashes the admin password on create', function () {
    $admin = Admin::create([
        'name' => 'Ops',
        'email' => 'ops2@asisten.test',
        'password' => 'rahasia123',
        'role' => 'admin',
    ]);

    expect($admin->password)->not->toBe('rahasia123');
    expect(Auth::guard('admin')->validate(['email' => 'ops2@asisten.test', 'password' => 'rahasia123']))->toBeTrue();
});
