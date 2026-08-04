<?php

use App\Modules\Finance\Models\Category;
use App\Modules\Finance\Models\Transaction;
use App\Modules\ShoppingList\Models\ShoppingList;

it('creates a household with default categories when a user registers', function () {
    $user = makeHouseholdUser('Andi');

    expect($user->current_household_id)->not->toBeNull();
    expect($user->households()->count())->toBe(1);
    expect($user->households()->first()->pivot->role)->toBe('owner');

    // Category is household-scoped: only visible while authenticated as a
    // member of that household, by design (see BelongsToHousehold).
    auth()->login($user);
    $household = $user->currentHousehold;
    expect($household->categories()->count())->toBeGreaterThan(0);
});

it('never leaks shopping lists across households', function () {
    $userA = makeHouseholdUser('Budi');
    $userB = makeHouseholdUser('Citra');

    auth()->login($userA);
    $listA = ShoppingList::create(['name' => 'Belanja Budi']);

    auth()->login($userB);
    $listB = ShoppingList::create(['name' => 'Belanja Citra']);

    auth()->login($userA);
    $visible = ShoppingList::all();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($listA->id);

    auth()->login($userB);
    $visible = ShoppingList::all();
    expect($visible)->toHaveCount(1);
    expect($visible->first()->id)->toBe($listB->id);
});

it('never leaks transactions or categories across households', function () {
    $userA = makeHouseholdUser('Dewi');
    $userB = makeHouseholdUser('Eka');

    auth()->login($userA);
    $categoryA = Category::first();
    Transaction::create([
        'category_id' => $categoryA->id,
        'user_id' => $userA->id,
        'type' => Transaction::TYPE_EXPENSE,
        'amount' => 50000,
        'occurred_on' => now(),
    ]);

    auth()->login($userB);
    expect(Category::count())->toBeGreaterThan(0);
    expect(Category::whereKey($categoryA->id)->exists())->toBeFalse();
    expect(Transaction::count())->toBe(0);

    auth()->login($userA);
    expect(Transaction::count())->toBe(1);
});
