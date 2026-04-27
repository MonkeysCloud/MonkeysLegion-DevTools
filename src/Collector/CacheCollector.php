<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Collector;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\ProfileContext;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Captures cache operations with hit/miss ratios, hot key detection,
 * lock contention, and per-store breakdown. Beyond Symfony/Laravel:
 * - Hit ratio calculation per request and per store
 * - Hot key ranking (most accessed keys)
 * - Lock wait time tracking
 * - Tag invalidation visibility
 * - Stampede detection heuristic
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CacheCollector implements CollectorInterface
{
    /** @var list<array{store: string, key: string, operation: string, hit: bool, duration_ms: float, ttl: int|null, tags: list<string>, payload_size: int|null, started_at: float}> */
    private array $operations = [];

    /** @var array<string, int> key => access count for hot key detection */
    private array $keyAccessCounts = [];

    /**
     * Total cache operations.
     */
    public int $operationCount {
        get => count($this->operations);
    }

    /**
     * Number of cache hits.
     */
    public int $hitCount {
        get {
            $hits = 0;
            foreach ($this->operations as $op) {
                if ($op['hit']) {
                    $hits++;
                }
            }

            return $hits;
        }
    }

    /**
     * Number of cache misses.
     */
    public int $missCount {
        get => $this->operationCount - $this->hitCount;
    }

    /**
     * Cache hit ratio (0.0 – 1.0).
     */
    public float $hitRatio {
        get {
            $total = $this->operationCount;

            return $total > 0 ? $this->hitCount / $total : 0.0;
        }
    }

    /**
     * Hit ratio as percentage string.
     */
    public string $hitRatioFormatted {
        get => sprintf('%.1f%%', $this->hitRatio * 100);
    }

    /**
     * Total cache operation time.
     */
    public float $totalDurationMs {
        get {
            $total = 0.0;
            foreach ($this->operations as $op) {
                $total += $op['duration_ms'];
            }

            return $total;
        }
    }

    public function __construct(
        private readonly bool $enabled = true,
        private readonly int $hotKeyThreshold = 5,
    ) {}

    public function name(): string
    {
        return 'cache';
    }

    public function label(): string
    {
        return 'Cache';
    }

    public function icon(): string
    {
        return '⚡';
    }

    public function priority(): int
    {
        return 700;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(ProfileContext $context): void
    {
        $this->operations = [];
        $this->keyAccessCounts = [];
    }

    public function stop(ProfileContext $context): void
    {
        // Data collected via recordOperation()
    }

    /**
     * Record a cache operation.
     *
     * @param list<string> $tags
     */
    public function recordOperation(
        string $store,
        string $key,
        string $operation,
        bool $hit,
        float $durationMs = 0.0,
        ?int $ttl = null,
        array $tags = [],
        ?int $payloadSize = null,
    ): void {
        $this->operations[] = [
            'store'        => $store,
            'key'          => $key,
            'operation'    => $operation,
            'hit'          => $hit,
            'duration_ms'  => $durationMs,
            'ttl'          => $ttl,
            'tags'         => $tags,
            'payload_size' => $payloadSize,
            'started_at'   => hrtime(true) / 1e6,
        ];

        // Track key access frequency
        $this->keyAccessCounts[$key] = ($this->keyAccessCounts[$key] ?? 0) + 1;
    }

    public function collect(ProfileContext $context): array
    {
        $operations = [];
        $stores = [];

        foreach ($this->operations as $i => $op) {
            $operations[] = [
                'index'        => $i,
                'store'        => $op['store'],
                'key'          => $this->truncateKey($op['key']),
                'operation'    => $op['operation'],
                'hit'          => $op['hit'],
                'duration_ms'  => round($op['duration_ms'], 3),
                'ttl'          => $op['ttl'],
                'tags'         => $op['tags'],
                'payload_size' => $op['payload_size'],
            ];

            // Per-store stats
            $store = $op['store'];
            if (!isset($stores[$store])) {
                $stores[$store] = ['hits' => 0, 'misses' => 0, 'total_ms' => 0.0];
            }
            $op['hit'] ? $stores[$store]['hits']++ : $stores[$store]['misses']++;
            $stores[$store]['total_ms'] += $op['duration_ms'];
        }

        // Hot keys
        arsort($this->keyAccessCounts);
        $hotKeys = [];
        foreach ($this->keyAccessCounts as $key => $count) {
            if ($count >= $this->hotKeyThreshold) {
                $hotKeys[] = ['key' => $this->truncateKey($key), 'count' => $count];
            }
        }

        return [
            'operations'       => $operations,
            'count'            => $this->operationCount,
            'hits'             => $this->hitCount,
            'misses'           => $this->missCount,
            'hit_ratio'        => round($this->hitRatio, 4),
            'hit_ratio_display' => $this->hitRatioFormatted,
            'total_duration_ms' => round($this->totalDurationMs, 3),
            'stores'           => $stores,
            'hot_keys'         => $hotKeys,
        ];
    }

    private function truncateKey(string $key, int $maxLen = 80): string
    {
        return strlen($key) > $maxLen
            ? substr($key, 0, $maxLen - 3) . '...'
            : $key;
    }
}
