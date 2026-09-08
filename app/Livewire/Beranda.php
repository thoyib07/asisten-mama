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
        // Tamu melihat Beranda yang sama, bukan halaman pemasaran terpisah — itulah "mencicipi
        // aplikasinya". Yang beda cuma isinya: nol apa adanya, tanpa satu pun angka karangan.
        // Wajib dicabang sebelum query di bawah, yang semuanya mengasumsikan ada user login
        // (`auth()->user()->currentHousehold` bernilai null untuk tamu).
        //
        // `newRecipeCount` tetap angka sungguhan: katalog resep global/shared, bukan
        // household-scoped, jadi itu memang data yang boleh dilihat tamu — dan Resep satu-satunya
        // ubin yang benar-benar bisa mereka buka.
        if (! auth()->check()) {
            return view('livewire.beranda', [
                'greeting' => 'Selamat datang 👋',
                'members' => collect(),
                'newRecipeCount' => Recipe::where('created_at', '>=', now()->subDays(7))->count(),
                'pendingShoppingCount' => 0,
                'dueBillCount' => 0,
                'todayEventCount' => 0,
                'pendingTaskCount' => 0,
                'taskProgress' => 0,
                'nextTask' => null,
                'nextEvent' => null,
            ]);
        }

        $household = auth()->user()->currentHousehold()->with('users')->first();

        // Lencana ubin Tagihan: yang sudah telat + yang jatuh tempo dalam sepekan. Dihitung
        // dengan mengekspansi rrule tiap tagihan, bukan query kolom — tanggal jatuh tempo
        // tidak disimpan sebagai baris (lihat BillSchedule).
        $soon = now()->addWeek()->toDateString();
        // Eager-load pembayaran tanpa global scope: BillSchedule::paidPeriods() memakai relasi
        // yang sudah dimuat kalau ada, jadi ini menghapus satu query per tagihan.
        $dueBillCount = Bill::active()
            ->with(['payments' => fn ($q) => $q->withoutGlobalScope('household')])
            ->get()
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
