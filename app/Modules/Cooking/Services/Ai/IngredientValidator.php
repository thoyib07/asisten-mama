<?php

namespace App\Modules\Cooking\Services\Ai;

use App\Modules\Cooking\Support\IngredientNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class IngredientValidator
{
    /**
     * Last-resort AI check for ingredient names that pass neither the local
     * heuristic nor the whitelist. Results are cached forever per normalized
     * name so the same word is never re-checked (across users, across time).
     *
     * If Groq is unreachable, defaults to plausible=true rather than blocking
     * the user — the subsequent AI recipe call will fail with its own
     * "AI unavailable" error anyway. This fail-open verdict is never cached,
     * so a transient outage doesn't permanently whitelist a word.
     */
    public function isPlausible(string $rawName): bool
    {
        $name = IngredientNormalizer::normalize($rawName);
        if ($name === '') {
            return false;
        }

        $cacheKey = 'ingredient-valid:v1:'.md5($name);
        $cached = Cache::get($cacheKey);
        if (is_bool($cached)) {
            return $cached;
        }

        $verdict = $this->askGroq($name);
        if ($verdict === null) {
            return true;
        }

        Cache::forever($cacheKey, $verdict);

        return $verdict;
    }

    private function askGroq(string $name): ?bool
    {
        try {
            $endpoint = config('services.groq.endpoint');
            $key = config('services.groq.key');
            $model = config('services.groq.model');
            if (! $endpoint || ! $key) {
                throw new RuntimeException('Groq is not configured.');
            }

            $response = Http::timeout(10)
                ->withToken($key)
                ->post($endpoint, [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $this->prompt($name)]],
                    'max_tokens' => 5,
                ]);

            if ($response->failed()) {
                throw new RuntimeException('Groq validator request failed: '.$response->status());
            }

            return $this->parseAnswer(data_get($response->json(), 'choices.0.message.content'));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function prompt(string $name): string
    {
        return "Apakah \"{$name}\" adalah nama bahan makanan/masakan yang masuk akal, dalam bahasa apa pun? ".
            'Balas HANYA dengan satu kata: YA atau TIDAK.';
    }

    private function parseAnswer(mixed $text): bool
    {
        if (! is_string($text)) {
            throw new RuntimeException('Unexpected validator response shape.');
        }

        return (bool) preg_match('/\bYA\b/i', $text) && ! preg_match('/\bTIDAK\b/i', $text);
    }
}
