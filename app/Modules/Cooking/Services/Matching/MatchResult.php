<?php

namespace App\Modules\Cooking\Services\Matching;

use App\Modules\Cooking\Models\Recipe;

class MatchResult
{
    public function __construct(
        public readonly Recipe $recipe,
        public readonly float $score,
        public readonly array $matched,
        public readonly array $missing,
    ) {}
}
