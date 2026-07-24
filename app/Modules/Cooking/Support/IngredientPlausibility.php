<?php

namespace App\Modules\Cooking\Support;

class IngredientPlausibility
{
    private const MIN_LENGTH = 2;

    private const MAX_LENGTH = 40;

    private const MAX_CONSONANT_RUN = 4;

    private const VOWEL_PATTERN = '/[aiueoàáâãäåèéêëìíîïòóôõöùúûü]/ui';

    /**
     * Cheap, local check for "does this look like a real word" — catches
     * obvious keyboard-mash input (e.g. "asdrtey12hdj") without needing a
     * dictionary. Real ingredient names sometimes fail this (e.g. "msg"),
     * so callers should also consult a whitelist before rejecting.
     */
    public static function looksLikeWord(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        $length = mb_strlen($normalized);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return false;
        }

        if (preg_match('/\d/', $normalized)) {
            return false;
        }

        if (! preg_match(self::VOWEL_PATTERN, $normalized)) {
            return false;
        }

        foreach (explode(' ', $normalized) as $word) {
            if (self::hasLongConsonantRun($word)) {
                return false;
            }
        }

        return true;
    }

    private static function hasLongConsonantRun(string $word): bool
    {
        $run = 0;
        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if (! preg_match('/\p{L}/u', $char)) {
                $run = 0;

                continue;
            }

            if (preg_match(self::VOWEL_PATTERN, $char)) {
                $run = 0;

                continue;
            }

            $run++;
            if ($run > self::MAX_CONSONANT_RUN) {
                return true;
            }
        }

        return false;
    }
}
