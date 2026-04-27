<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Collector;

use MonkeysLegion\DevTools\Collector\CacheCollector;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheCollector::class)]
final class CacheCollectorTest extends TestCase
{
    private CacheCollector $collector;
    private ProfileContext $context;

    protected function setUp(): void
    {
        $this->collector = new CacheCollector(hotKeyThreshold: 3);
        $this->context = ProfileContext::create('test', true);
        $this->collector->start($this->context);
    }

    #[Test]
    public function records_cache_operations(): void
    {
        $this->collector->recordOperation('redis', 'user:1', 'get', hit: true, durationMs: 0.5);
        $this->collector->recordOperation('redis', 'user:2', 'get', hit: false, durationMs: 1.0);

        $this->assertSame(2, $this->collector->operationCount);
    }

    #[Test]
    public function calculates_hit_miss_ratio(): void
    {
        $this->collector->recordOperation('redis', 'k1', 'get', hit: true);
        $this->collector->recordOperation('redis', 'k2', 'get', hit: true);
        $this->collector->recordOperation('redis', 'k3', 'get', hit: false);

        $this->assertSame(2, $this->collector->hitCount);
        $this->assertSame(1, $this->collector->missCount);
        $this->assertEqualsWithDelta(0.6667, $this->collector->hitRatio, 0.01);
    }

    #[Test]
    public function formats_hit_ratio(): void
    {
        $this->collector->recordOperation('redis', 'k1', 'get', hit: true);

        $this->assertSame('100.0%', $this->collector->hitRatioFormatted);
    }

    #[Test]
    public function detects_hot_keys(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->collector->recordOperation('redis', 'hot-key', 'get', hit: true);
        }
        $this->collector->recordOperation('redis', 'cold-key', 'get', hit: true);

        $this->collector->stop($this->context);
        $data = $this->collector->collect($this->context);

        $hotKeys = $data['hot_keys'] ?? [];
        $this->assertNotEmpty($hotKeys);
        $this->assertSame('hot-key', $hotKeys[0]['key']);
        $this->assertSame(5, $hotKeys[0]['count']);
    }

    #[Test]
    public function tracks_total_duration(): void
    {
        $this->collector->recordOperation('redis', 'k1', 'get', hit: true, durationMs: 1.5);
        $this->collector->recordOperation('redis', 'k2', 'get', hit: false, durationMs: 2.5);

        $this->assertEqualsWithDelta(4.0, $this->collector->totalDurationMs, 0.001);
    }

    #[Test]
    public function collect_includes_per_store_breakdown(): void
    {
        $this->collector->recordOperation('redis', 'k1', 'get', hit: true);
        $this->collector->recordOperation('file', 'k2', 'get', hit: false);
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);

        $this->assertArrayHasKey('stores', $data);
        $this->assertArrayHasKey('redis', $data['stores']);
        $this->assertArrayHasKey('file', $data['stores']);
    }
}
