<?php

use App\Models\Household;
use App\Models\User;
use App\Modules\Tasks\Livewire\TaskList;
use App\Modules\Tasks\Models\Task;
use Livewire\Livewire;

function memberOf(Household $household, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    $household->users()->attach($user, ['role' => 'member']);
    $user->forceFill(['current_household_id' => $household->id])->save();

    return $user->fresh();
}

it('adds a task assigned to a household member', function () {
    $owner = makeHouseholdUser('Budi');
    auth()->login($owner);
    $siti = memberOf($owner->currentHousehold, 'Siti');

    Livewire::test(TaskList::class)
        ->set('title', 'Cuci mobil')
        ->set('userId', $siti->id)
        ->set('priority', 'tinggi')
        ->call('addTask')
        ->assertHasNoErrors();

    $task = Task::first();
    expect($task->title)->toBe('Cuci mobil');
    expect($task->user_id)->toBe($siti->id);
    expect($task->household_id)->toBe($owner->current_household_id);
});

it('refuses to assign a task to someone outside the household', function () {
    $owner = makeHouseholdUser('Budi');
    $outsider = makeHouseholdUser('Orang Lain');

    auth()->login($owner);
    Livewire::test(TaskList::class)
        ->set('title', 'Tugas nyasar')
        ->set('userId', $outsider->id)
        ->call('addTask');

    expect(Task::first()->user_id)->toBeNull();
});

it('splits Saya and Keluarga by assignee', function () {
    $owner = makeHouseholdUser('Budi');
    auth()->login($owner);
    $siti = memberOf($owner->currentHousehold, 'Siti');

    Task::create(['title' => 'Punya saya', 'user_id' => $owner->id]);
    Task::create(['title' => 'Punya Siti', 'user_id' => $siti->id]);
    Task::create(['title' => 'Belum ditugaskan']);

    Livewire::test(TaskList::class)
        ->assertSee(['Punya saya', 'Punya Siti', 'Belum ditugaskan'])
        ->set('filter', 'saya')
        ->assertSee('Punya saya')
        ->assertDontSee('Punya Siti')
        ->set('filter', 'keluarga')
        ->assertSee(['Punya Siti', 'Belum ditugaskan'])
        ->assertDontSee('Punya saya');
});

it('toggles a task done and back', function () {
    $owner = makeHouseholdUser('Budi');
    auth()->login($owner);
    $task = Task::create(['title' => 'Buang sampah']);

    Livewire::test(TaskList::class)->call('toggleTask', $task->id);
    expect($task->fresh()->is_done)->toBeTrue();

    Livewire::test(TaskList::class)->call('toggleTask', $task->id);
    expect($task->fresh()->is_done)->toBeFalse();
});
