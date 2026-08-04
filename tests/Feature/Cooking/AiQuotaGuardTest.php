<?php

use App\Modules\Cooking\Services\AiQuotaGuard;

it('blocks further AI calls once the household daily cap is reached', function () {
    $guard = new AiQuotaGuard;
    $householdId = 1;

    // Household cap is 7/day — the first 7 calls must pass.
    for ($i = 0; $i < 7; $i++) {
        expect($guard->check($householdId))->toBeNull();
    }

    // The 8th call for the same household must be blocked.
    expect($guard->check($householdId))->not->toBeNull();

    // A different household is unaffected by household A's cap.
    expect($guard->check(2))->toBeNull();
});

it('blocks further AI calls once the app-wide daily cap is reached', function () {
    $guard = new AiQuotaGuard;

    // App-wide cap is 25/day, spread across many households so no single
    // household cap (7/day) trips first.
    for ($i = 0; $i < 25; $i++) {
        expect($guard->check(1000 + $i))->toBeNull();
    }

    expect($guard->check(9999))->not->toBeNull();
});
