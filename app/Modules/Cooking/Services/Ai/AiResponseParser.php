<?php

namespace App\Modules\Cooking\Services\Ai;

use InvalidArgumentException;

class AiResponseParser
{
    public function parse(string $rawText): array
    {
        $clean = trim($rawText);
        $clean = preg_replace('/^```(?:json)?|```$/m', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);
        if (! is_array($data)) {
            $data = $this->extractJsonArray($clean);
        }
        if (! is_array($data)) {
            throw new InvalidArgumentException('AI response is not valid JSON.');
        }

        $recipes = array_is_list($data) ? $data : ($data['recipes'] ?? null);
        if (! is_array($recipes)) {
            throw new InvalidArgumentException('No recipe list found in response.');
        }

        // Sumbernya probabilistik: satu entri cacat tidak boleh membuang dua entri yang baik.
        // Dulu array_map di sini membiarkan exception dari satu resep menggagalkan seluruh respons.
        $parsed = [];

        foreach ($recipes as $r) {
            try {
                $parsed[] = $this->parseRecipe($r);
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        if ($parsed === []) {
            throw new InvalidArgumentException('No usable recipe entry in response.');
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseRecipe(mixed $r): array
    {
        if (! isset($r['name'], $r['ingredients'], $r['steps']) || ! is_array($r['ingredients']) || ! is_array($r['steps'])) {
            throw new InvalidArgumentException('Recipe entry missing required fields.');
        }

        return [
            'name' => (string) $r['name'],
            'ingredients' => array_values(array_map(function ($ing) {
                if (! is_array($ing) || ! isset($ing['name'])) {
                    throw new InvalidArgumentException('Recipe ingredient entry missing name.');
                }

                return [
                    'name' => (string) $ing['name'],
                    'is_primary' => (bool) ($ing['is_primary'] ?? false),
                    'quantity' => (isset($ing['quantity']) && $ing['quantity'] !== '') ? (string) $ing['quantity'] : null,
                ];
            }, $r['ingredients'])),
            'steps' => array_values(array_map(function ($step) {
                $text = trim((string) (is_array($step) ? ($step['text'] ?? '') : $step));
                if ($text === '') {
                    throw new InvalidArgumentException('Recipe step entry missing text.');
                }

                $duration = is_array($step) && isset($step['duration_minutes']) && $step['duration_minutes'] !== null && $step['duration_minutes'] !== ''
                    ? (int) $step['duration_minutes']
                    : null;

                return ['text' => $text, 'duration_minutes' => $duration];
            }, $r['steps'])),
            'servings' => isset($r['servings']) ? (int) $r['servings'] : null,
            'duration_minutes' => (isset($r['duration_minutes']) && $r['duration_minutes'] !== '') ? (int) $r['duration_minutes'] : null,
            'nutrition' => $this->parseNutrition($r['nutrition'] ?? null),
        ];
    }

    private function parseNutrition(mixed $nutrition): ?array
    {
        if (! is_array($nutrition)) {
            return null;
        }

        $result = [];
        foreach (['calories', 'protein', 'carbs', 'fat'] as $field) {
            $result[$field] = (isset($nutrition[$field]) && $nutrition[$field] !== '') ? (int) $nutrition[$field] : null;
        }

        return array_filter($result, fn ($v) => $v !== null) === [] ? null : $result;
    }

    /**
     * Scans for a top-level `[...]` span and validates it looks like a recipe
     * list before accepting it, so stray brackets in surrounding prose
     * (e.g. "[1]" citations added by search grounding) aren't mistaken for
     * the JSON payload.
     */
    private function extractJsonArray(string $text): ?array
    {
        $offset = 0;
        $length = strlen($text);

        while (($start = strpos($text, '[', $offset)) !== false) {
            $depth = 0;
            for ($i = $start; $i < $length; $i++) {
                if ($text[$i] === '[') {
                    $depth++;
                } elseif ($text[$i] === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $candidate = json_decode(substr($text, $start, $i - $start + 1), true);
                        if (is_array($candidate) && isset($candidate[0]['name'])) {
                            return $candidate;
                        }
                        break;
                    }
                }
            }
            $offset = $start + 1;
        }

        return null;
    }
}
