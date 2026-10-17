<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Cache;

/**
 * Pool sampai 10 API key Gemini (GEMINI_API_KEY_1..10). Key yang kena limit
 * diistirahatkan (cooldown di cache) lalu request pindah ke key berikutnya
 * yang tersedia. Key yang terakhir berhasil dipakai lebih dulu pada request
 * berikutnya. Key tidak pernah ditulis ke log - hanya nomor slot-nya.
 */
class GeminiKeyPool
{
    private const CACHE_PREFIX = 'gemini_key_cooldown:';

    private const ACTIVE_SLOT_KEY = 'gemini_key_active_slot';

    /** @param  array<int, string>  $keys  slot (1-10) => api key */
    public function __construct(private array $keys) {}

    public static function fromConfig(): self
    {
        $keys = [];
        foreach ((array) config('services.gemini.keys', []) as $slot => $key) {
            if (is_string($key) && trim($key) !== '') {
                $keys[(int) $slot] = trim($key);
            }
        }

        return new self($keys);
    }

    public function isEmpty(): bool
    {
        return $this->keys === [];
    }

    /**
     * Slot yang sedang tidak cooldown, mulai dari slot aktif terakhir lalu
     * berputar ke slot berikutnya.
     *
     * @return array<int, string> slot => key
     */
    public function available(): array
    {
        $slots = array_keys($this->keys);
        $active = (int) Cache::get(self::ACTIVE_SLOT_KEY, $slots[0] ?? 1);
        $start = array_search($active, $slots, true);
        $ordered = $start === false ? $slots : [...array_slice($slots, $start), ...array_slice($slots, 0, $start)];

        $result = [];
        foreach ($ordered as $slot) {
            if (! Cache::has(self::CACHE_PREFIX.$slot)) {
                $result[$slot] = $this->keys[$slot];
            }
        }

        return $result;
    }

    public function markSuccess(int $slot): void
    {
        Cache::forever(self::ACTIVE_SLOT_KEY, $slot);
    }

    public function coolDown(int $slot, int $seconds, string $reason): void
    {
        Cache::put(self::CACHE_PREFIX.$slot, ['reason' => $reason, 'until' => now()->addSeconds($seconds)->toIso8601String()], $seconds);
    }

    /**
     * @return array<int, array{slot: int, status: string, until: ?string, reason: ?string}>
     */
    public function status(): array
    {
        $active = Cache::get(self::ACTIVE_SLOT_KEY);

        return collect(array_keys($this->keys))->map(function (int $slot) use ($active) {
            $cooldown = Cache::get(self::CACHE_PREFIX.$slot);

            return [
                'slot' => $slot,
                'status' => $cooldown ? 'istirahat' : ($slot === (int) $active ? 'aktif' : 'siap'),
                'until' => $cooldown['until'] ?? null,
                'reason' => $cooldown['reason'] ?? null,
            ];
        })->all();
    }
}
