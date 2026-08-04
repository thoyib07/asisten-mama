<?php

namespace App\Modules\Cooking\Support;

class RecipeSteps
{
    /**
     * Single guardrail for the `steps` JSON shape: always a list of
     * {text, duration_minutes} entries. Accepts a string (Filament textarea,
     * one line per step, no duration), an array of plain strings (seeder,
     * legacy AI shape), or an array of {text, duration_minutes} objects (AI),
     * so every write path funnels here.
     */
    public static function normalize(mixed $value): array
    {
        if (is_string($value)) {
            // one step per line; fall back to sentence split for single-line blobs
            $parts = preg_split('/\r\n|\r|\n/', $value);
            if (count($parts) <= 1) {
                $parts = preg_split('/(?<=[.!?])\s+/', $value);
            }
        } elseif (is_array($value)) {
            $parts = $value;
        } else {
            return [];
        }

        $steps = [];
        foreach ($parts as $part) {
            $raw = is_array($part) ? ($part['text'] ?? '') : $part;
            // drop leading "1." / "2)" numbering, then trim
            $text = trim(preg_replace('/^\s*\d+[.)]\s*/', '', (string) $raw));
            if ($text === '') {
                continue;
            }

            $duration = null;
            if (is_array($part) && isset($part['duration_minutes']) && $part['duration_minutes'] !== '') {
                $duration = (int) $part['duration_minutes'];
            }

            $steps[] = ['text' => $text, 'duration_minutes' => $duration];
        }

        return $steps;
    }
}
