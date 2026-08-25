<?php

namespace App\Modules\Calendar\Services;

use App\Models\User;
use App\Modules\Calendar\Models\Event;
use App\Modules\Tasks\Models\Task;
use App\Support\Ics;
use Illuminate\Database\Eloquent\Collection;

/**
 * Membangun kalender ICS (RFC 5545) pribadi seorang anggota keluarga: seluruh agenda
 * household-nya, ditambah tugas yang di-assign ke dia sendiri.
 *
 * Feed ini terpisah dari feed Tagihan supaya di Google jadi kalender sendiri — bisa
 * dimatikan/diberi warna sendiri, dan tugas orang lain tidak ikut memenuhi kalender.
 */
class FamilyIcsFeed
{
    private const PAST_MONTHS = 1;

    private const FUTURE_MONTHS = 12;

    public function forUser(User $user): string
    {
        $householdId = $user->current_household_id;
        $from = now()->subMonths(self::PAST_MONTHS)->startOfDay();
        $to = now()->addMonths(self::FUTURE_MONTHS)->endOfDay();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//asisten-mama//Kalender Keluarga//ID',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            // Nama household apa adanya — registrasi selalu memberinya awalan "Keluarga"
            // (Register.php), jadi menambahkan awalan lagi di sini jadi "Keluarga Keluarga Budi".
            'X-WR-CALNAME:'.Ics::escape($user->currentHousehold?->name ?? 'Kalender Keluarga'),
        ];

        foreach ($this->events($householdId, $from, $to) as $event) {
            $lines = array_merge($lines, $this->eventLines($event));
        }

        foreach ($this->tasks($householdId, $user, $to) as $task) {
            $lines = array_merge($lines, $this->taskLines($task));
        }

        $lines[] = 'END:VCALENDAR';

        return Ics::document($lines);
    }

    /**
     * Feed dilayani lewat token tanpa sesi, jadi global scope BelongsToHousehold memfilter
     * `household_id = null` di sini dan hasilnya selalu kosong. Household disaring eksplisit.
     *
     * @return Collection<int, Event>
     */
    private function events(?int $householdId, $from, $to)
    {
        return Event::withoutGlobalScope('household')
            ->where('household_id', $householdId)
            ->whereBetween('starts_at', [$from, $to])
            ->with('user')
            ->orderBy('starts_at')
            ->get();
    }

    /** @return Collection<int, Task> */
    private function tasks(?int $householdId, User $user, $to)
    {
        return Task::withoutGlobalScope('household')
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('is_done', false)
            ->whereNotNull('due_on')
            // Sengaja tanpa batas bawah, beda dari agenda: feed ini adalah keadaan lengkap
            // kalender setiap kali diambil, jadi lantai jendela akan menghilangkan justru
            // tugas yang paling tidak boleh hilang — yang sudah lama lewat dan belum selesai.
            ->whereDate('due_on', '<=', $to)
            ->orderBy('due_on')
            ->get();
    }

    /** @return array<int, string> */
    private function eventLines(Event $event): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:event-'.$event->id.'@asisten-mama',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            // ponytail: waktu floating (tanpa Z, tanpa TZID) — app.timezone masih UTC sementara
            // user mengetik jam lokal, jadi menandainya UTC akan menggeser acara 7 jam di Google.
            // Floating berarti Google merender apa adanya di zona pembaca. Ganti ke TZID begitu
            // ada kolom timezone per household.
            'DTSTART:'.$event->starts_at->format('Ymd\THis'),
            'DTEND:'.($event->ends_at ?? $event->starts_at->copy()->addHour())->format('Ymd\THis'),
            'SUMMARY:'.Ics::escape($this->eventSummary($event)),
            'END:VEVENT',
        ];

        return $lines;
    }

    /** @return array<int, string> */
    private function taskLines(Task $task): array
    {
        return [
            'BEGIN:VEVENT',
            'UID:task-'.$task->id.'@asisten-mama',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$task->due_on->format('Ymd'),
            // Akhir all-day event bersifat eksklusif — sehari setelahnya = acara sehari penuh.
            'DTEND;VALUE=DATE:'.$task->due_on->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.Ics::escape('✅ '.$task->title.' ('.$task->priorityLabel().')'),
            'TRANSP:TRANSPARENT',
            'BEGIN:VALARM',
            'TRIGGER:PT7H',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.Ics::escape($task->title),
            'END:VALARM',
            'END:VEVENT',
        ];
    }

    private function eventSummary(Event $event): string
    {
        $summary = '📅 '.$event->title;

        // Nama penanggung jawab ikut di judul: di Google tidak ada kolom "assignee", dan
        // anggota lain tidak jadi attendee (butuh OAuth) — jadi judul satu-satunya tempatnya.
        if ($event->user) {
            $summary .= ' — '.$event->user->name;
        }

        return $summary;
    }
}
