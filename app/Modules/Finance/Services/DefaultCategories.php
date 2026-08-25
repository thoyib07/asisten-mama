<?php

namespace App\Modules\Finance\Services;

use App\Models\Household;
use App\Modules\Finance\Models\Category;

class DefaultCategories
{
    /** @return array<int, array{type: string, name: string, icon: string}> */
    private const DEFAULTS = [
        ['type' => Category::TYPE_INCOME, 'name' => 'Gaji', 'icon' => '💼'],
        ['type' => Category::TYPE_INCOME, 'name' => 'Lainnya', 'icon' => '➕'],
        ['type' => Category::TYPE_EXPENSE, 'name' => 'Belanja Harian', 'icon' => '🛒'],
        ['type' => Category::TYPE_EXPENSE, 'name' => 'Tagihan', 'icon' => '🧾'],
        ['type' => Category::TYPE_EXPENSE, 'name' => 'Pendidikan', 'icon' => '📚'],
        ['type' => Category::TYPE_EXPENSE, 'name' => 'Kesehatan', 'icon' => '🩺'],
        ['type' => Category::TYPE_EXPENSE, 'name' => 'Lainnya', 'icon' => '➕'],
    ];

    public static function seedFor(Household $household): void
    {
        foreach (self::DEFAULTS as $category) {
            $household->categories()->create($category + ['is_default' => true]);
        }
    }
}
