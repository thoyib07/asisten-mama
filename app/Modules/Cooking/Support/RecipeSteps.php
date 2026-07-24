<?php

namespace App\Modules\Cooking\Support;

class RecipeSteps
{
    /**
     * Single guardrail for the `steps` JSON shape: always a list of clean,
     * non-empty step strings. Accepts an array (AI parser, seeder) or a
     * string (Filament textarea, backfill) so every write path funnels here.
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

        return array_values(array_filter(array_map(
            // drop leading "1." / "2)" numbering, then trim
            fn ($p) => trim(preg_replace('/^\s*\d+[.)]\s*/', '', (string) $p)),
            $parts
        ), fn ($p) => $p !== ''));
    }
}
