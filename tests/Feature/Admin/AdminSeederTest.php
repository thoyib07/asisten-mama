<?php

use App\Models\Admin;
use Database\Seeders\AdminSeeder;

it('creates the first owner from environment variables', function () {
    putenv('ADMIN_NAME=Ops SaaS');
    putenv('ADMIN_EMAIL=ops@asisten.test');
    putenv('ADMIN_PASSWORD=rahasia123');

    $this->seed(AdminSeeder::class);

    $admin = Admin::where('email', 'ops@asisten.test')->firstOrFail();

    expect($admin->name)->toBe('Ops SaaS');
    expect($admin->role)->toBe(Admin::ROLE_OWNER);
    expect(auth('admin')->validate(['email' => 'ops@asisten.test', 'password' => 'rahasia123']))->toBeTrue();
});

it('does nothing when an admin already exists, so it is safe on every boot', function () {
    Admin::factory()->create(['email' => 'lama@asisten.test']);

    putenv('ADMIN_EMAIL=baru@asisten.test');
    putenv('ADMIN_PASSWORD=rahasia123');

    $this->seed(AdminSeeder::class);

    expect(Admin::count())->toBe(1);
    expect(Admin::first()->email)->toBe('lama@asisten.test');
});

it('does nothing when the environment variables are absent', function () {
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');

    $this->seed(AdminSeeder::class);

    expect(Admin::count())->toBe(0);
});

afterEach(function () {
    putenv('ADMIN_NAME');
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
});
