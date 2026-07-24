<?php

namespace Database\Seeders;

use App\Modules\Cooking\Models\Ingredient;
use App\Modules\Cooking\Models\Recipe;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['Telur Dadar', [
                'Pecahkan telur ke mangkuk, beri sejumput garam.',
                'Kocok telur hingga berbusa rata.',
                'Panaskan minyak di wajan dengan api sedang.',
                'Tuang telur, masak hingga kedua sisi matang keemasan.',
            ], 2, ['telur', 'garam', 'minyak']],
            ['Nasi Goreng', [
                'Cincang bawang putih, panaskan minyak.',
                'Tumis bawang putih hingga harum.',
                'Masukkan telur, orak-arik hingga matang.',
                'Masukkan nasi dan kecap, aduk rata hingga panas merata.',
            ], 2, ['nasi', 'bawang putih', 'kecap', 'telur', 'minyak']],
            ['Tumis Kangkung', [
                'Cincang bawang putih, panaskan minyak.',
                'Tumis bawang putih hingga harum.',
                'Masukkan kangkung dan garam, aduk hingga layu.',
            ], 3, ['kangkung', 'bawang putih', 'garam', 'minyak']],
            ['Sup Ayam', [
                'Rebus air hingga mendidih, masukkan ayam.',
                'Tambahkan wortel dan bawang putih.',
                'Bumbui dengan garam, masak hingga ayam empuk.',
            ], 4, ['ayam', 'wortel', 'bawang putih', 'garam']],
            ['Mie Rebus', [
                'Rebus mie hingga setengah matang.',
                'Masukkan bawang putih dan kecap.',
                'Tambahkan telur, aduk hingga matang.',
            ], 1, ['mie', 'telur', 'bawang putih', 'kecap']],
        ];

        foreach ($data as [$name, $steps, $servings, $ingredients]) {
            $recipe = Recipe::create([
                'name' => $name,
                'steps' => $steps,
                'servings' => $servings,
                'source' => Recipe::SOURCE_SEED,
            ]);
            foreach ($ingredients as $ing) {
                $recipe->ingredients()->attach(Ingredient::findOrCreateNormalized($ing));
            }
        }
    }
}
