<?php

use App\Livewire\Beranda;
use App\Models\Admin;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

it('sends a customer to Beranda after logging in on the app panel', function () {
    $user = makeHouseholdUser('Nia');
    $user->forceFill(['password' => 'rahasia123'])->save();

    Filament::setCurrentPanel('app');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'rahasia123'])
        ->call('authenticate')
        ->assertRedirect(route('beranda'));

    expect(auth('web')->id())->toBe($user->id);
});

it('keeps a logged-in customer out of the admin panel', function () {
    $user = makeHouseholdUser('Nia');

    $this->actingAs($user)
        ->get('/backoffice')
        ->assertRedirect('/backoffice/login');

    $this->actingAs($user)
        ->get('/backoffice/recipes')
        ->assertRedirect('/backoffice/login');
});

it('refuses a customer trying to log in on the admin panel', function () {
    $user = makeHouseholdUser('Nia');
    $user->forceFill(['password' => 'rahasia123'])->save();

    Filament::setCurrentPanel('admin');

    Livewire::test(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'rahasia123'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(auth('admin')->check())->toBeFalse();
    expect(auth('web')->check())->toBeFalse();
});

it('sends an admin to the admin panel after logging in, and keeps the session on later requests', function () {
    $admin = Admin::factory()->create(['password' => 'rahasia123']);

    Filament::setCurrentPanel('admin');

    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'rahasia123'])
        ->call('authenticate')
        ->assertRedirect('/backoffice');

    expect(auth('admin')->id())->toBe($admin->id);

    // Catatan: actingAs($admin, 'admin') memanggil Auth::shouldUse('admin'), jadi ini TIDAK
    // membuktikan apa pun soal AuthenticateSession di produksi (di sana guard default masih
    // `web` saat middleware panel jalan). Yang dibuktikan hanya: halaman panel merender untuk
    // Admin yang terautentikasi.
    $this->actingAs($admin, 'admin')->get('/backoffice')->assertOk();
    $this->actingAs($admin, 'admin')->get('/backoffice/recipes')->assertOk();
});

it('lets a guest reach the customer auth pages and the landing page', function () {
    // `/` tidak lagi memantulkan tamu ke login: Beranda mencabang sendiri jadi halaman
    // perkenalan. Yang dijaga di sini cuma pemisahan panel — isi halamannya diuji di BerandaTest.
    $this->get('/')->assertOk();
    $this->get('/login')->assertOk();
    $this->get('/register')->assertOk();
});

it('keeps / on Beranda instead of the app panel home route', function () {
    // Panel `app` dipasang di path kosong. Filament melewatkan route home-nya hanya selama
    // routes/web.php sudah mendaftarkan GET / lebih dulu — kalau urutannya berubah, test ini
    // yang jatuh duluan, bukan produksi.
    expect(Route::has('filament.app.home'))->toBeFalse();
    expect(Route::getRoutes()->getByName('beranda')->getController())->toBeInstanceOf(Beranda::class);

    $user = makeHouseholdUser('Nia');
    $this->actingAs($user)->get('/')->assertOk()->assertSee('Beranda', false);
});
