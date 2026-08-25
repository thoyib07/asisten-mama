<?php

namespace App\Modules\Cooking\Services\Ai;

use RuntimeException;

/**
 * Ditandai sebagai tipe tersendiri supaya pemanggil tidak perlu mencocokkan teks pesan.
 * Sebelumnya RecipeFinder memakai str_contains($e->getMessage(), 'rate limit'), yang diam-diam
 * berhenti bekerja begitu pesan di client diterjemahkan atau diubah kata-katanya.
 */
class AiRateLimitedException extends RuntimeException {}
