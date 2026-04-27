<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Collector;

use MonkeysLegion\DevTools\Collector\QueryCollector;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryCollector::class)]
final class QueryCollectorTest extends TestCase
{
    private QueryCollector $collector;
    private ProfileContext $context;

    protected function setUp(): void
    {
        $this->collector = new QueryCollector(nPlusOneThreshold: 3);
        $this->context = ProfileContext::create('test', true);
        $this->collector->start($this->context);
    }

    #[Test]
    public function records_queries(): void
    {
        $this->collector->recordQuery('SELECT * FROM users', durationMs: 5.0);
        $this->collector->recordQuery('SELECT * FROM posts', durationMs: 10.0);

        $this->assertSame(2, $this->collector->queryCount);
    }

    #[Test]
    public function detects_duplicate_queries(): void
    {
        $this->collector->recordQuery('SELECT * FROM users WHERE id = 1', durationMs: 1.0);
        $this->collector->recordQuery('SELECT * FROM users WHERE id = 2', durationMs: 1.0);

        $this->assertGreaterThan(0, $this->collector->duplicateCount);
    }

    #[Test]
    public function detects_n_plus_one(): void
    {
        // Same query structure repeated 3+ times (our threshold)
        for ($i = 1; $i <= 5; $i++) {
            $this->collector->recordQuery("SELECT * FROM comments WHERE post_id = {$i}", durationMs: 1.0);
        }

        $this->assertTrue($this->collector->hasNPlusOne);
    }

    #[Test]
    public function no_n_plus_one_below_threshold(): void
    {
        $this->collector->recordQuery('SELECT * FROM users WHERE id = 1', durationMs: 1.0);
        $this->collector->recordQuery('SELECT * FROM users WHERE id = 2', durationMs: 1.0);

        $this->assertFalse($this->collector->hasNPlusOne);
    }

    #[Test]
    public function tracks_total_duration(): void
    {
        $this->collector->recordQuery('SELECT 1', durationMs: 10.0);
        $this->collector->recordQuery('SELECT 2', durationMs: 20.0);

        $this->assertEqualsWithDelta(30.0, $this->collector->totalDurationMs, 0.001);
    }

    #[Test]
    public function tracks_slowest_query(): void
    {
        $this->collector->recordQuery('SELECT 1', durationMs: 5.0);
        $this->collector->recordQuery('SELECT 2', durationMs: 50.0);
        $this->collector->recordQuery('SELECT 3', durationMs: 10.0);

        $this->assertEqualsWithDelta(50.0, $this->collector->slowestQueryMs, 0.001);
    }

    #[Test]
    public function collect_returns_structured_data(): void
    {
        $this->collector->recordQuery('SELECT * FROM users', durationMs: 5.0, connection: 'mysql');
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);

        $this->assertArrayHasKey('queries', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('total_duration_ms', $data);
        $this->assertArrayHasKey('duplicates', $data);
        $this->assertArrayHasKey('has_n_plus_one', $data);
        $this->assertSame(1, $data['count']);
    }

    #[Test]
    public function respects_max_queries(): void
    {
        $collector = new QueryCollector(maxQueries: 5);
        $collector->start($this->context);

        for ($i = 0; $i < 10; $i++) {
            $collector->recordQuery("SELECT {$i}", durationMs: 1.0);
        }

        $this->assertSame(5, $collector->queryCount);
    }
}
