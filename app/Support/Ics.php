<?php

namespace App\Support;

/**
 * Utilitas format RFC 5545 yang dipakai bersama oleh feed Tagihan dan feed Kalender Keluarga.
 * Dipisah supaya folding baris — bagian yang paling gampang salah — cuma ada satu salinan.
 */
class Ics
{
    /** Escape nilai TEXT (RFC 5545 §3.3.11). Backslash harus lebih dulu. */
    public static function escape(string $value): string
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
    public static function fold(string $line): string
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

    /**
     * Gabungkan baris-baris VCALENDAR jadi satu dokumen: dilipat, CRLF, dan diakhiri CRLF.
     *
     * @param  array<int, string>  $lines
     */
    public static function document(array $lines): string
    {
        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }
}
