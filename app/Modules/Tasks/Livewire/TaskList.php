<?php

namespace App\Modules\Tasks\Livewire;

use App\Modules\Tasks\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class TaskList extends Component
{
    /** semua | saya | keluarga — tiga segmen di frame task-list. */
    public string $filter = 'semua';

    public bool $adding = false;

    public string $title = '';

    public ?int $userId = null;

    public string $dueOn = '';

    public string $priority = 'sedang';

    public function toggleForm(): void
    {
        $this->adding = ! $this->adding;

        if ($this->adding) {
            $this->userId = auth()->id();
        }
    }

    public function addTask(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'userId' => ['nullable', 'integer'],
            'dueOn' => ['nullable', 'date'],
            'priority' => ['required', 'in:'.implode(',', array_keys(Task::PRIORITIES))],
        ]);

        Task::create([
            // Anggota di luar household tidak boleh jadi penerima tugas — validasi 'integer'
            // saja tidak cukup, jadi id-nya dicek terhadap daftar anggota yang sah.
            'user_id' => $this->memberIds()->contains($data['userId']) ? $data['userId'] : null,
            'title' => $data['title'],
            'due_on' => $data['dueOn'] ?: null,
            'priority' => $data['priority'],
        ]);

        $this->reset('title', 'dueOn', 'priority', 'adding');
    }

    public function toggleTask(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $task->update(['is_done' => ! $task->is_done]);
    }

    public function removeTask(int $taskId): void
    {
        Task::where('id', $taskId)->delete();
    }

    private function memberIds()
    {
        return auth()->user()->currentHousehold->users->pluck('id');
    }

    public function render()
    {
        $tasks = Task::with('user')
            ->when($this->filter === 'saya', fn ($q) => $q->where('user_id', auth()->id()))
            // "Keluarga" = tugas yang bukan milik saya sendiri: ditugaskan ke anggota lain,
            // atau tugas rumah tangga yang belum punya penanggung jawab.
            ->when($this->filter === 'keluarga', fn ($q) => $q->where(
                fn ($sub) => $sub->whereNull('user_id')->orWhere('user_id', '!=', auth()->id())
            ))
            ->orderBy('is_done')
            ->orderByRaw('due_on is null, due_on')
            ->orderByDesc('id')
            ->get();

        return view('livewire.tasks.task-list', [
            'tasks' => $tasks,
            'members' => auth()->user()->currentHousehold->users,
            'priorities' => Task::PRIORITIES,
        ]);
    }
}
