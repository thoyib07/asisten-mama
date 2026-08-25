<?php

use App\Filament\Resources\Admins\AdminResource;
use App\Models\Admin;

it('lets only an owner manage admin accounts', function () {
    $owner = actingAsSaasAdmin(Admin::ROLE_OWNER);
    $other = Admin::factory()->create();

    expect(AdminResource::getViewAnyAuthorizationResponse()->allowed())->toBeTrue();
    expect(AdminResource::getCreateAuthorizationResponse()->allowed())->toBeTrue();
    expect(AdminResource::getEditAuthorizationResponse($other)->allowed())->toBeTrue();
    expect(AdminResource::getDeleteAuthorizationResponse($other)->allowed())->toBeTrue();
    expect(AdminResource::getDeleteAuthorizationResponse($owner)->allowed())->toBeFalse();
});

it('blocks a plain admin from every admin-management action, not just create', function () {
    actingAsSaasAdmin(Admin::ROLE_ADMIN);
    $owner = Admin::factory()->create(['role' => Admin::ROLE_OWNER]);

    // Menggate create saja tidak cukup: admin yang masih boleh menyunting bisa mengganti
    // password akun owner lalu login sebagai owner.
    expect(AdminResource::getViewAnyAuthorizationResponse()->allowed())->toBeFalse();
    expect(AdminResource::getCreateAuthorizationResponse()->allowed())->toBeFalse();
    expect(AdminResource::getEditAuthorizationResponse($owner)->allowed())->toBeFalse();
    expect(AdminResource::getDeleteAuthorizationResponse($owner)->allowed())->toBeFalse();
    expect(AdminResource::shouldRegisterNavigation())->toBeFalse();
});

it('keeps the admin list route unreachable for a plain admin', function () {
    $admin = actingAsSaasAdmin(Admin::ROLE_ADMIN);

    $this->actingAs($admin, 'admin')->get('/backoffice/admins')->assertForbidden();
});

it('still lets a plain admin reach the panel and their own profile', function () {
    $admin = actingAsSaasAdmin(Admin::ROLE_ADMIN);

    // Satu-satunya cara admin non-owner mengganti nama/passwordnya sendiri.
    $this->actingAs($admin, 'admin')->get('/backoffice')->assertOk();
    $this->actingAs($admin, 'admin')->get('/backoffice/profile')->assertOk();
});
