<?php

use App\Filament\Resources\Households\HouseholdResource;
use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\SaasOverview;
use App\Models\Household;
use App\Modules\Cooking\Models\Recipe;
use Livewire\Livewire;

it('lists every household across tenants, not just one', function () {
    makeHouseholdUser('Rina');
    makeHouseholdUser('Budi');

    actingAsSaasAdmin();

    Livewire::test(ListHouseholds::class)
        ->assertCanSeeTableRecords(Household::all());
});

it('lists every customer across households', function () {
    $rina = makeHouseholdUser('Rina');
    $budi = makeHouseholdUser('Budi');

    actingAsSaasAdmin();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$rina, $budi]);
});

it('keeps household and user resources read-only', function () {
    expect(HouseholdResource::canCreate())->toBeFalse();
    expect(UserResource::canCreate())->toBeFalse();

    $user = makeHouseholdUser('Rina');
    expect(HouseholdResource::canEdit($user->currentHousehold))->toBeFalse();
    expect(HouseholdResource::canDelete($user->currentHousehold))->toBeFalse();
    expect(UserResource::canEdit($user))->toBeFalse();
    expect(UserResource::canDelete($user))->toBeFalse();
});

it('counts households, customers and AI recipes in the overview widget', function () {
    makeHouseholdUser('Rina');
    makeHouseholdUser('Budi');
    Recipe::create(['name' => 'Nasi Goreng', 'steps' => 'Goreng', 'source' => Recipe::SOURCE_SEED]);
    Recipe::create(['name' => 'Soto Ayam', 'steps' => 'Rebus', 'source' => Recipe::SOURCE_AI]);

    actingAsSaasAdmin();

    Livewire::test(SaasOverview::class)
        ->assertSee('Keluarga terdaftar')
        ->assertSee('Customer terdaftar')
        ->assertSee('1 hasil AI');
});
