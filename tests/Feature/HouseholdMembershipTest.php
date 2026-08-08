<?php

use App\Filament\Pages\Auth\Register;
use App\Models\Household;
use App\Models\User;
use App\Modules\Household\Livewire\HouseholdPage;
use App\Modules\ShoppingList\Models\ShoppingList;
use Livewire\Livewire;

it('joins an existing household via a valid invite code during registration', function () {
    $owner = makeHouseholdUser('Rina');
    $household = $owner->currentHousehold;

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Sari',
            'email' => 'sari@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'invite_code' => $household->invite_code,
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $newUser = User::where('email', 'sari@example.com')->firstOrFail();
    expect($newUser->current_household_id)->toBe($household->id);
    expect($household->fresh()->roleFor($newUser))->toBe('member');
});

it('rejects registration with an invalid invite code and does not create an extra household', function () {
    $countBefore = Household::count();

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Tono',
            'email' => 'tono@example.com',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
            'invite_code' => 'NOTREAL1',
        ])
        ->call('register')
        ->assertHasFormErrors(['invite_code']);

    expect(Household::count())->toBe($countBefore);
    expect(User::where('email', 'tono@example.com')->exists())->toBeFalse();
});

it('lets two members of the same household see the same shopping list', function () {
    $owner = makeHouseholdUser('Wati');
    $household = $owner->currentHousehold;

    $member = User::factory()->create(['name' => 'Yusuf']);
    Household::joinWithCode($member, $household->invite_code);

    auth()->login($owner);
    $list = ShoppingList::create(['name' => 'Belanja Bersama']);

    auth()->login($member);
    $visible = ShoppingList::all();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($list->id);
});

it('lets an owner remove a member, who then gets their own new household', function () {
    $owner = makeHouseholdUser('Agus');
    $household = $owner->currentHousehold;

    $member = User::factory()->create(['name' => 'Budi']);
    Household::joinWithCode($member, $household->invite_code);

    $household->removeMember($owner, $member);

    $member->refresh();
    expect($member->current_household_id)->not->toBeNull();
    expect($member->current_household_id)->not->toBe($household->id);
    expect($household->fresh()->roleFor($member))->toBeNull();
});

it('lets a non-owner member leave the household on their own', function () {
    $owner = makeHouseholdUser('Dian');
    $household = $owner->currentHousehold;

    $member = User::factory()->create(['name' => 'Eko']);
    Household::joinWithCode($member, $household->invite_code);

    $household->removeMember($member, $member);

    expect($member->fresh()->current_household_id)->not->toBe($household->id);
});

it('never lets the owner be removed or leave via removeMember', function () {
    $owner = makeHouseholdUser('Fani');
    $household = $owner->currentHousehold;

    expect(fn () => $household->removeMember($owner, $owner))
        ->toThrow(RuntimeException::class);
});

it('never lets a non-owner remove someone else', function () {
    $owner = makeHouseholdUser('Gita');
    $household = $owner->currentHousehold;

    $memberA = User::factory()->create(['name' => 'Hadi']);
    Household::joinWithCode($memberA, $household->invite_code);

    $memberB = User::factory()->create(['name' => 'Ida']);
    Household::joinWithCode($memberB, $household->invite_code);

    expect(fn () => $household->removeMember($memberA, $memberB))
        ->toThrow(RuntimeException::class);
});

it('gates invite code regeneration to the owner only, server-side', function () {
    $owner = makeHouseholdUser('Joko');
    $household = $owner->currentHousehold;

    $member = User::factory()->create(['name' => 'Kiki']);
    Household::joinWithCode($member, $household->invite_code);

    auth()->login($member);

    Livewire::test(HouseholdPage::class)
        ->call('regenerateInviteCode')
        ->assertForbidden();
});

it('invalidates the old invite code after regenerating', function () {
    $owner = makeHouseholdUser('Lina');
    $household = $owner->currentHousehold;
    $oldCode = $household->invite_code;

    $household->regenerateInviteCode();

    expect($household->fresh()->invite_code)->not->toBe($oldCode);
    expect(Household::joinWithCode(User::factory()->create(), $oldCode))->toBeNull();
});
