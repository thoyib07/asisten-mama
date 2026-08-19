<?php

namespace App\Modules\Bills\Services;

use App\Models\Household;
use App\Modules\Bills\Models\Bill;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Membangun kalender ICS (RFC 5545) berisi pengingat tagihan sebuah household.
 *
 * Occurrence diekspansi di sini oleh BillSchedule, bukan dikirim sebagai RRULE. Alasannya:
 * event ditaruh pada tanggal *pengingat*, bukan tanggal jatuh tempo, dan menggeser DTSTART
 * tidak menggeser occurrence sebuah RRULE (FREQ=MONTHLY;BYMONTHDAY=20 tetap jatuh tanggal 20
 * berapa pun DTSTART-nya). Mengekspansi sendiri juga berarti RRULE bebas yang diketik user
 * tidak pernah diteruskan mentah-mentah ke Google.
 */
class IcsFeed
{
    private const FUTURE_MONTHS = 12;

    private const MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ags', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    public function __construct(private BillSchedule $schedule) {}

    public function forHousehold(Household $household, ?string $from = null, ?string $to = null): string
    {
        $from ??= BillSchedule::lookbackFrom();
        $to ??= Carbon::parse($from)->addMonths(BillSchedule::LOOKBACK_MONTHS + self::FUTURE_MONTHS)->toDateString();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//asisten-mama//Tagihan//ID',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape('Tagihan '.$household->name),
        ];

        foreach ($this->billsFor($household) as $bill) {
            foreach ($this->schedule->unpaidOccurrencesBetween($bill, $from, $to) as $dueOn) {
                $lines = array_merge($lines, $this->event($bill, $dueOn));
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * Feed dilayani lewat token tanpa sesi, jadi global scope BelongsToHousehold tidak bisa
     * diandalkan di sini — tanpa user login ia memfilter `household_id = null` dan hasilnya
     * selalu kosong. Household disaring eksplisit.
     *
     * @return Collection<int, Bill>
     */
    private function billsFor(Household $household)
    {
        return Bill::withoutGlobalScope('household')
            ->where('household_id', $household->id)
            ->active()
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, string> */
    private function event(Bill $bill, string $dueOn): array
    {
        $due = Carbon::parse($dueOn);
        $remindOn = $due->copy()->subDays($bill->reminder_days_before);

        $lines = [
            'BEGIN:VEVENT',
            'UID:bill-'.$bill->id.'-'.$due->format('Ymd').'@asisten-mama',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$remindOn->format('Ymd'),
            // Akhir all-day event bersifat eksklusif — sehari setelahnya = acara sehari penuh.
            'DTEND;VALUE=DATE:'.$remindOn->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.$this->escape($this->summary($bill, $due)),
            'TRANSP:TRANSPARENT',
        ];

        if ($bill->notes) {
            $lines[] = 'DESCRIPTION:'.$this->escape($bill->notes);
        }

        // Bonus kalau Google menghormatinya di feed langganan; fitur ini tidak bergantung
        // padanya — event-nya sendiri sudah jatuh di tanggal pengingat.
        $lines[] = 'BEGIN:VALARM';
        $lines[] = 'TRIGGER:PT9H';
        $lines[] = 'ACTION:DISPLAY';
        $lines[] = 'DESCRIPTION:'.$this->escape($bill->name);
        $lines[] = 'END:VALARM';
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function summary(Bill $bill, Carbon $due): string
    {
        $summary = '🧾 Bayar '.$bill->name.' — jatuh tempo '
            .$due->day.' '.self::MONTHS[$due->month];

        if ($bill->amount_estimate !== null) {
            $summary .= ', ±Rp '.number_format((float) $bill->amount_estimate, 0, ',', '.');
        }

        return $summary;
    }

    /** Escape nilai TEXT (RFC 5545 §3.3.11). Backslash harus lebih dulu. */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value
        );
    }

    /**
     * Lipat baris di 75 oktet (RFC 5545 §3.1). Dihitung per karakter UTF-8, bukan per byte —
     * melipat di tengah karakter multi-byte menghasilkan teks rusak di sisi Google.
     */
    private function fold(string $line): string
    {
        $folded = '';
        $octets = 0;

        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $size = strlen($char);

            if ($octets + $size > 75) {
                $folded .= "\r\n ";
                $octets = 1; // spasi kelanjutan ikut menghabiskan jatah baris berikutnya
            }

            $folded .= $char;
            $octets += $size;
        }

        return $folded;
    }
}
