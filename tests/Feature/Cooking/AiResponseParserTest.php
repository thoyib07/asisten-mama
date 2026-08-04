<?php

use App\Modules\Cooking\Services\Ai\AiResponseParser;

it('parses a realistic Groq response into the full current shape', function () {
    $raw = json_encode([[
        'name' => 'Telur Dadar',
        'ingredients' => [
            ['name' => 'telur', 'is_primary' => true, 'quantity' => '2 butir'],
            ['name' => 'garam', 'is_primary' => false, 'quantity' => '1 sdt'],
        ],
        'steps' => [
            ['text' => 'Kocok telur', 'duration_minutes' => 2],
            ['text' => 'Goreng di wajan panas', 'duration_minutes' => 5],
        ],
        'servings' => 2,
        'duration_minutes' => 10,
        'nutrition' => ['calories' => 200, 'protein' => 12, 'carbs' => 2, 'fat' => 14],
    ]]);

    $result = (new AiResponseParser)->parse($raw);

    expect($result)->toBe([[
        'name' => 'Telur Dadar',
        'ingredients' => [
            ['name' => 'telur', 'is_primary' => true, 'quantity' => '2 butir'],
            ['name' => 'garam', 'is_primary' => false, 'quantity' => '1 sdt'],
        ],
        'steps' => [
            ['text' => 'Kocok telur', 'duration_minutes' => 2],
            ['text' => 'Goreng di wajan panas', 'duration_minutes' => 5],
        ],
        'servings' => 2,
        'duration_minutes' => 10,
        'nutrition' => ['calories' => 200, 'protein' => 12, 'carbs' => 2, 'fat' => 14],
    ]]);
});

it('tolerates missing optional fields and legacy plain-string steps/ingredients', function () {
    $raw = json_encode([[
        'name' => 'Sup Sederhana',
        'ingredients' => [['name' => 'wortel']],
        'steps' => ['Rebus semua bahan'],
    ]]);

    $result = (new AiResponseParser)->parse($raw);

    expect($result)->toBe([[
        'name' => 'Sup Sederhana',
        'ingredients' => [['name' => 'wortel', 'is_primary' => false, 'quantity' => null]],
        'steps' => [['text' => 'Rebus semua bahan', 'duration_minutes' => null]],
        'servings' => null,
        'duration_minutes' => null,
        'nutrition' => null,
    ]]);
});

it('rejects a step entry with empty text instead of silently keeping a blank step', function () {
    $raw = json_encode([[
        'name' => 'Resep Rusak',
        'ingredients' => [['name' => 'telur']],
        'steps' => [['text' => '  ', 'duration_minutes' => 5]],
    ]]);

    expect(fn () => (new AiResponseParser)->parse($raw))->toThrow(InvalidArgumentException::class);
});
