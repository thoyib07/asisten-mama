<?php

namespace App\Modules\Cooking\Services;

use Illuminate\Support\Facades\Cache;

class AiQuotaGuard
{
    private const APP_DAILY_CAP = 25;

    private const HOUSEHOLD_DAILY_CAP = 7;

    /**
     * Checks (and, if under quota, consumes) today's app-wide and household AI
     * quota. Returns null if the call is allowed, or a user-facing reason if
     * either cap is already reached — callers should skip the Groq call entirely
     * in that case.
     */
    public function check(?int $householdId): ?string
    {
        $ttl = now()->endOfDay();
        $appKey = 'ai-quota:app:'.now()->toDateString();

        if (Cache::get($appKey, 0) >= self::APP_DAILY_CAP) {
            return 'Kuota AI aplikasi hari ini sudah habis, coba lagi besok.';
        }

        $householdKey = $householdId !== null ? "ai-quota:household:{$householdId}:".now()->toDateString() : null;

        if ($householdKey !== null && Cache::get($householdKey, 0) >= self::HOUSEHOLD_DAILY_CAP) {
            return 'Kuota AI household kamu hari ini sudah habis.';
        }

        $this->increment($appKey, $ttl);
        if ($householdKey !== null) {
            $this->increment($householdKey, $ttl);
        }

        return null;
    }

    private function increment(string $key, $ttl): void
    {
        // ponytail: database cache store's increment() is a no-op (returns false)
        // when the key doesn't exist yet, so seed it before incrementing.
        Cache::add($key, 0, $ttl);
        Cache::increment($key);
    }
}
