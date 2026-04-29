<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Bridge;

use MonkeysLegion\DevTools\Bridge\DatabaseEventBridge;
use MonkeysLegion\DevTools\Collector\QueryCollector;
use PHPUnit\Framework\TestCase;

/**
 * Tests that DatabaseEventBridge correctly forwards
 * onQueryExecuted() calls to the QueryCollector.
 */
final class DatabaseEventBridgeTest extends TestCase
{
    public function testOnQueryExecutedForwardsToCollector(): void
    {
        $collector = new QueryCollector();
        $bridge = new DatabaseEventBridge($collector);

        // Create a mock connection (just needs to satisfy the type hint)
        $connection = $this->createMock(
            \MonkeysLegion\Database\Contracts\ConnectionInterface::class
        );

        $bridge->onQueryExecuted($connection, 'SELECT * FROM users WHERE id = ?', ['id' => 42], 1.5);

        $this->assertSame(1, $collector->queryCount);
        $this->assertGreaterThan(0.0, $collector->totalDurationMs);
    }

    public function testMultipleQueriesAccumulate(): void
    {
        $collector = new QueryCollector();
        $bridge = new DatabaseEventBridge($collector);

        $connection = $this->createMock(
            \MonkeysLegion\Database\Contracts\ConnectionInterface::class
        );

        $bridge->onQueryExecuted($connection, 'SELECT 1', [], 0.5);
        $bridge->onQueryExecuted($connection, 'SELECT 2', [], 1.0);
        $bridge->onQueryExecuted($connection, 'SELECT 3', [], 2.0);

        $this->assertSame(3, $collector->queryCount);
        $this->assertEqualsWithDelta(3.5, $collector->totalDurationMs, 0.01);
    }

    public function testNoOpMethodsDontThrow(): void
    {
        $collector = new QueryCollector();
        $bridge = new DatabaseEventBridge($collector);

        $connection = $this->createMock(
            \MonkeysLegion\Database\Contracts\ConnectionInterface::class
        );

        // These should be no-ops
        $bridge->onConnected($connection);
        $bridge->onDisconnected($connection);
        $bridge->onError($connection, new \RuntimeException('test'));

        $this->assertSame(0, $collector->queryCount);
    }
}
