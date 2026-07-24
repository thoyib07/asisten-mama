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

        return array_map(function ($r) {
            if (! isset($r['name'], $r['ingredients'], $r['steps']) || ! is_array($r['ingredients']) || ! is_array($r['steps'])) {
                throw new InvalidArgumentException('Recipe entry missing required fields.');
            }

            return [
                'name' => (string) $r['name'],
                'ingredients' => array_values(array_map('strval', $r['ingredients'])),
                'steps' => array_values(array_map('strval', $r['steps'])),
                'servings' => isset($r['servings']) ? (int) $r['servings'] : null,
            ];
        }, $recipes);
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
