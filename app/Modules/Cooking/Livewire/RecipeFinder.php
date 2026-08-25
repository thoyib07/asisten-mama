<?php

namespace App\Modules\Cooking\Livewire;

use App\Modules\Cooking\Services\Ai\AiRateLimitedException;
use App\Modules\Cooking\Services\Ai\AiRecipeClient;
use App\Modules\Cooking\Services\Ai\AiRecipeImporter;
use App\Modules\Cooking\Services\Ai\IngredientValidator;
use App\Modules\Cooking\Services\AiQuotaGuard;
use App\Modules\Cooking\Services\Matching\RecipeMatcher;
use App\Modules\Cooking\Support\IngredientCatalog;
use App\Modules\Cooking\Support\IngredientNormalizer;
use App\Modules\Cooking\Support\IngredientPlausibility;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout')]
class RecipeFinder extends Component
{
    public array $ingredients = [];

    public string $newIngredient = '';

    public array $results = [];

    public bool $searched = false;

    public ?string $aiError = null;

    public ?string $aiNotice = null;

    public ?string $ingredientError = null;

    public array $selectedMealCategories = [];

    public array $selectedCuisineTypes = [];

    public function addIngredient(): void
    {
        $this->ingredientError = null;
        $name = IngredientNormalizer::normalize($this->newIngredient);

        if ($name === '' || in_array($name, $this->ingredients, true)) {
            $this->newIngredient = '';

            return;
        }

        if (! IngredientPlausibility::looksLikeWord($name) && ! IngredientCatalog::isKnown($name)) {
            $this->ingredientError = "Bahan \"{$name}\" tidak dikenali. Periksa lagi penulisannya.";
            $this->newIngredient = '';

            return;
        }

        $this->ingredients[] = $name;
        $this->newIngredient = '';
    }

    public function removeIngredient(int $index): void
    {
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    public function search(RecipeMatcher $matcher): void
    {
        $this->searched = true;
        $this->results = collect($matcher->search(
            $this->ingredients,
            $this->selectedMealCategories,
            $this->selectedCuisineTypes,
        ))
            ->map(fn ($m) => [
                'id' => $m->recipe->id,
                'name' => $m->recipe->name,
                'source' => $m->recipe->source,
                'score' => $m->score,
                'matched' => $m->matched,
                'missing' => $m->missing,
            ])->all();
    }

    public function exploreWithAi(
        AiRecipeClient $client,
        AiRecipeImporter $importer,
        RecipeMatcher $matcher,
        IngredientValidator $validator,
        AiQuotaGuard $quotaGuard
    ): void {
        $this->aiError = null;
        $this->aiNotice = null;
        try {
            // Cek kuota SEBELUM loop validasi: setiap bahan yang belum ada di IngredientCatalog
            // memicu satu panggilan Groq lewat isPlausible(), jadi kalau guard-nya belakangan
            // user bisa menghabiskan beberapa panggilan lalu tetap ditolak karena kuota habis.
            $quotaError = $quotaGuard->check(auth()->user()?->current_household_id);
            if ($quotaError !== null) {
                $this->aiError = $quotaError;

                return;
            }

            $plausible = collect($this->ingredients)
                ->filter(fn ($i) => IngredientCatalog::isKnown($i) || $validator->isPlausible($i))
                ->values();

            $ignored = collect($this->ingredients)->diff($plausible);
            if ($ignored->isNotEmpty()) {
                $this->aiNotice = 'Bahan berikut diabaikan karena tidak dikenali AI: '.$ignored->implode(', ').'.';
            }

            if ($plausible->isEmpty()) {
                $this->aiError = 'Tidak ada bahan yang dikenali untuk dicari dengan AI.';

                return;
            }

            $suggestions = $client->suggest($plausible->all(), $this->selectedMealCategories, $this->selectedCuisineTypes);
            $importer->importMany($suggestions, $this->selectedMealCategories, $this->selectedCuisineTypes);
            $this->search($matcher);
        } catch (AiRateLimitedException $e) {
            report($e);
            $this->aiError = 'AI sedang sibuk, tunggu sebentar lalu coba lagi.';
        } catch (\Throwable $e) {
            report($e);
            $this->aiError = 'Gagal mengambil resep AI. Coba lagi nanti.';
        }
    }

    public function render()
    {
        return view('livewire.cooking.recipe-finder');
    }
}
