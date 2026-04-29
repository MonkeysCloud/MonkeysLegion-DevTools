<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Bridge;

use MonkeysLegion\Database\Contracts\ConnectionEventDispatcherInterface;
use MonkeysLegion\Database\Contracts\ConnectionInterface;
use MonkeysLegion\DevTools\Collector\QueryCollector;

/**
 * Bridges the database Connection event system to the DevTools QueryCollector.
 *
 * When set as the Connection's `$eventDispatcher`, this adapter forwards
 * every `onQueryExecuted()` call to `QueryCollector::recordQuery()`,
 * enabling automatic SQL profiling in the DevTools toolbar.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class DatabaseEventBridge implements ConnectionEventDispatcherInterface
{
    public function __construct(
        private readonly QueryCollector $queryCollector,
    ) {}

    public function onConnected(ConnectionInterface $connection): void
    {
        // No-op — QueryCollector doesn't track connections.
    }

    public function onDisconnected(ConnectionInterface $connection): void
    {
        // No-op
    }

    public function onError(ConnectionInterface $connection, \Throwable $error): void
    {
        // No-op — ExceptionCollector handles errors.
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onQueryExecuted(
        ConnectionInterface $connection,
        string $sql,
        array $params,
        float $durationMs,
    ): void {
        $this->queryCollector->recordQuery(
            sql: $sql,
            bindings: array_values($params),
            durationMs: $durationMs,
            connection: 'default',
        );
    }
}
