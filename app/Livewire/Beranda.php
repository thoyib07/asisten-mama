<?php

namespace App\Livewire;

use App\Modules\Bills\Models\Bill;
use App\Modules\Bills\Services\BillSchedule;
use App\Modules\Calendar\Models\Event;
use App\Modules\Cooking\Models\Recipe;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use App\Modules\Tasks\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class Beranda extends Component
{
    public function render(BillSchedule $schedule)
    {
        $household = auth()->user()->currentHousehold()->with('users')->first();

        // Lencana ubin Tagihan: yang sudah telat + yang jatuh tempo dalam sepekan. Dihitung
        // dengan mengekspansi rrule tiap tagihan, bukan query kolom — tanggal jatuh tempo
        // tidak disimpan sebagai baris (lihat BillSchedule).
        $soon = now()->addWeek()->toDateString();
        $dueBillCount = Bill::active()->get()
            ->filter(fn (Bill $bill) => ($next = $schedule->nextUnpaid($bill, BillSchedule::lookbackFrom()))
                && $next <= $soon)
            ->count();

        $doneTasks = Task::where('is_done', true)->count();
        $pendingTasks = Task::pending()->count();
        $totalTasks = $doneTasks + $pendingTasks;

        return view('livewire.beranda', [
            'greeting' => 'Halo, '.strtok(auth()->user()->name, ' ').'!',
            'members' => $household->users,
            'newRecipeCount' => Recipe::where('created_at', '>=', now()->subDays(7))->count(),
            // ponytail: goes through the parent ShoppingList (household-scoped) rather than
            // querying ShoppingListItem directly — the item itself isn't BelongsToHousehold.
            'pendingShoppingCount' => ShoppingListItem::whereHas('shoppingList')
                ->where('is_checked', false)->count(),
            'dueBillCount' => $dueBillCount,
            'todayEventCount' => Event::whereDate('starts_at', today())->count(),
            'pendingTaskCount' => $pendingTasks,
            'taskProgress' => $totalTasks > 0 ? (int) round($doneTasks / $totalTasks * 100) : 0,
            // Tugas menunggu yang paling mendesak — jadi baris keterangan di kartu "Tugas Hari Ini".
            'nextTask' => Task::with('user')->pending()
                ->orderByRaw('due_on is null, due_on')->first(),
            'nextEvent' => Event::with('user')->upcoming()->first(),
        ]);
    }
}
