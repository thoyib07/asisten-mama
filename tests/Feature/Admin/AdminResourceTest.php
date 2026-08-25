<?php

use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Models\Admin;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('creates an admin with a hashed password', function () {
    actingAsSaasAdmin();

    Livewire::test(CreateAdmin::class)
        ->fillForm([
            'name' => 'Dewi',
            'email' => 'dewi@asisten.test',
            'password' => 'rahasia123',
            'role' => Admin::ROLE_ADMIN,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Admin::where('email', 'dewi@asisten.test')->firstOrFail();

    expect($created->role)->toBe(Admin::ROLE_ADMIN);
    expect(Hash::check('rahasia123', $created->password))->toBeTrue();
});

it('rejects a duplicate email', function () {
    actingAsSaasAdmin();
    Admin::factory()->create(['email' => 'dewi@asisten.test']);

    Livewire::test(CreateAdmin::class)
        ->fillForm([
            'name' => 'Dewi Kedua',
            'email' => 'dewi@asisten.test',
            'password' => 'rahasia123',
            'role' => Admin::ROLE_ADMIN,
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);
});

it('keeps the existing password when the field is left blank on edit', function () {
    actingAsSaasAdmin();
    $target = Admin::factory()->create(['password' => 'lama12345']);

    Livewire::test(EditAdmin::class, ['record' => $target->getKey()])
        ->fillForm(['name' => 'Nama Baru', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    $target->refresh();

    expect($target->name)->toBe('Nama Baru');
    expect(Hash::check('lama12345', $target->password))->toBeTrue();
});

it('replaces the password when a new one is typed on edit', function () {
    actingAsSaasAdmin();
    $target = Admin::factory()->create(['password' => 'lama12345']);

    Livewire::test(EditAdmin::class, ['record' => $target->getKey()])
        ->fillForm(['password' => 'baru123456'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('baru123456', $target->refresh()->password))->toBeTrue();
});

it('refuses to let an admin delete themselves through the real row action', function () {
    $me = actingAsSaasAdmin();

    // Menguji lewat aksi tabel sungguhan, bukan AdminResource::canDelete(). canDelete() hanya
    // turunan dari getDeleteAuthorizationResponse() dan tidak pernah dipanggil DeleteAction —
    // assertion terhadapnya lulus hijau sementara UI tetap menghapus barisnya.
    Livewire::test(ListAdmins::class)
        ->assertTableActionHidden(DeleteAction::class, $me);

    expect(Admin::find($me->getKey()))->not->toBeNull();
});

it('still lets an admin delete a different admin', function () {
    actingAsSaasAdmin();
    $other = Admin::factory()->create();

    Livewire::test(ListAdmins::class)
        ->callTableAction(DeleteAction::class, $other);

    expect(Admin::find($other->getKey()))->toBeNull();
});

it('exposes no bulk delete that could wipe every admin', function () {
    actingAsSaasAdmin();
    Admin::factory()->count(2)->create();

    // Bulk delete diotorisasi lewat getDeleteAnyAuthorizationResponse() tanpa cek per-record,
    // jadi satu aksi bisa menghapus semua admin. Tombolnya harus benar-benar tidak ada.
    // Dicek lewat instance tabelnya, bukan assertTableBulkActionDoesNotExist() — helper itu
    // meresolusi DeleteBulkAction jadi nama 'delete', yang bentrok dengan DeleteAction per-baris.
    $toolbar = Livewire::test(ListAdmins::class)->instance()->getTable()->getToolbarActions();

    expect($toolbar)->toBeEmpty();
});

it('is unreachable for a customer', function () {
    $user = makeHouseholdUser('Rina');

    $this->actingAs($user)->get('/backoffice/admins')->assertRedirect('/backoffice/login');
});
