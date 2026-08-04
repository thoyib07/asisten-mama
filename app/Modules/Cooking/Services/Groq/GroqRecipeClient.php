<?php

namespace App\Modules\Cooking\Services\Groq;

use App\Modules\Cooking\Services\Ai\AiRecipeClient;
use App\Modules\Cooking\Services\Ai\AiResponseParser;
use App\Modules\Cooking\Services\Ai\RecipePrompt;
use App\Modules\Cooking\Support\IngredientNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GroqRecipeClient implements AiRecipeClient
{
    public function __construct(private AiResponseParser $parser = new AiResponseParser) {}

    public function suggest(array $ingredientNames, array $mealCategories = [], array $cuisineTypes = []): array
    {
        $key = 'groq:v3:'.md5(collect($ingredientNames)
            ->map(fn ($n) => IngredientNormalizer::normalize($n))
            ->sort()->implode('|').'|'.collect($mealCategories)->sort()->implode(',').'|'.collect($cuisineTypes)->sort()->implode(','));

        return Cache::remember($key, now()->addHours(6), function () use ($ingredientNames, $mealCategories, $cuisineTypes) {
            return $this->callGroq($ingredientNames, $mealCategories, $cuisineTypes);
        });
    }

    private function callGroq(array $ingredientNames, array $mealCategories, array $cuisineTypes): array
    {
        $endpoint = config('services.groq.endpoint');
        $key = config('services.groq.key');
        $model = config('services.groq.model');
        if (! $endpoint || ! $key) {
            throw new RuntimeException('Groq is not configured.');
        }

        $response = Http::timeout(30)
            ->withToken($key)
            ->post($endpoint, [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => RecipePrompt::build($ingredientNames, $mealCategories, $cuisineTypes)]],
                'max_tokens' => 4000,
            ]);

        if ($response->failed()) {
            \Log::error('Groq failed', ['status' => $response->status(), 'body' => $response->body()]);
            $msg = $response->status() === 429 ? 'Groq rate limit reached. Try again in a moment.' : 'Groq request failed: '.$response->status();
            throw new RuntimeException($msg);
        }

        $text = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($text)) {
            throw new RuntimeException('Unexpected Groq response shape.');
        }

        return $this->parser->parse($text);
    }
}
