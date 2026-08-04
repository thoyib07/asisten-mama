<?php

namespace App\Modules\Cooking\Services\Ai;

use App\Modules\Cooking\Support\RecipeTaxonomy;

class RecipePrompt
{
    public static function build(array $ingredientNames, array $mealCategories = [], array $cuisineTypes = []): string
    {
        $list = implode(', ', $ingredientNames);

        $context = 'Beri 3 ide resep';
        if ($mealCategories !== []) {
            $labels = array_map(fn ($c) => RecipeTaxonomy::MEAL_CATEGORIES[$c] ?? $c, $mealCategories);
            $context .= ' untuk kategori '.implode('/', $labels);
        }
        if ($cuisineTypes !== []) {
            $labels = array_map(fn ($c) => RecipeTaxonomy::CUISINE_TYPES[$c] ?? $c, $cuisineTypes);
            $context .= ' dengan gaya masakan '.implode('/', $labels);
        }
        $context .= " yang bisa dibuat dengan bahan-bahan berikut: {$list}.";

        return "{$context} "
            .'Balas HANYA JSON array. Tiap item: {name, ingredients (array objek {name, is_primary, quantity}), '
            .'steps (array objek {text, duration_minutes}, tiap elemen satu langkah memasak yang rinci dan '
            .'berurutan), servings (number), duration_minutes (number, total waktu masak resep), '
            .'nutrition (objek {calories, protein, carbs, fat}, semua angka gram kecuali calories dalam kkal, '
            .'perkiraan total untuk keseluruhan resep)}. '
            .'Field "name" tiap ingredients HARUS berupa nama bahan polos saja tanpa takaran atau satuan '
            .'(contoh benar: "telur"; contoh salah: "4 butir telur" atau "200 gram telur") — takaran/gramasinya '
            .'taruh di field "quantity" terpisah (contoh: "2 butir", "200 gram"). Field "is_primary" bernilai '
            .'true HANYA untuk bahan utama resep ini (bukan bumbu/pelengkap). Field "duration_minutes" tiap '
            .'step adalah perkiraan lama pengerjaan step itu saja dalam menit (boleh null kalau sangat singkat). '
            .'Semua angka gizi & durasi adalah PERKIRAAN kasar, bukan hasil hitungan presisi — boleh null kalau '
            .'benar-benar tidak bisa diperkirakan. '
            .'Jangan menyarankan teknik pengalengan, fermentasi, curing, atau menyimpan bahan mentah dalam minyak '
            .'— kalau ragu soal keamanan pangan, pilih metode memasak paling konvensional.';
    }
}
