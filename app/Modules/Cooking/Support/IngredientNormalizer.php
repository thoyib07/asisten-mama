<?php

namespace App\Modules\Cooking\Support;

class IngredientNormalizer
{
    /**
     * Trailing spec/quantity qualifier, e.g. "alkohol 30%" -> "alkohol",
     * "tepung tinggi protein 30%" -> "tepung tinggi protein". The catalog
     * stores generic ingredient names, not specific variants.
     */
    private const TRAILING_QUALIFIER_PATTERN = '/\s+\d+(?:[.,]\d+)?\s*%?\s*$/u';

    public static function normalize(string $raw): string
    {
        $lower = mb_strtolower(trim($raw));
        $noPunct = trim($lower, " \t\n\r\0\x0B.,;:");
        $noQualifier = trim(preg_replace(self::TRAILING_QUALIFIER_PATTERN, '', $noPunct));

        return preg_replace('/\s+/', ' ', $noQualifier);
    }
}
