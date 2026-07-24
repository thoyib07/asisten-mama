<?php

namespace App\Modules\Cooking\Services\Ai;

class RecipePrompt
{
    public static function build(array $ingredientNames): string
    {
        $list = implode(', ', $ingredientNames);

        return "Beri 3 ide resep memakai sebagian besar bahan ini: {$list}. "
            .'Balas HANYA JSON array. Tiap item: {name, ingredients (array string), '
            .'steps (array string, tiap elemen satu langkah memasak yang rinci dan berurutan), servings (number)}. '
            .'Setiap elemen ingredients HARUS berupa nama bahan polos saja tanpa takaran atau satuan '
            .'(contoh benar: "telur"; contoh salah: "4 butir telur" atau "200 gram telur").';
    }
}
