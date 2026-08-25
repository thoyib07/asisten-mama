<?php

use App\Models\Admin;
use App\Models\Household;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function makeHouseholdUser(string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    Household::createWithOwner($user, "Keluarga {$name}");

    return $user->fresh();
}

/**
 * Filament resource di panel admin butuh dua hal: guard `admin` yang terautentikasi,
 * dan panel `admin` sebagai panel aktif (panel default sekarang `app`).
 */
function actingAsSaasAdmin(string $role = Admin::ROLE_OWNER): Admin
{
    $admin = Admin::factory()->create(['role' => $role]);

    auth('admin')->login($admin);
    Filament::setCurrentPanel('admin');

    return $admin;
}
