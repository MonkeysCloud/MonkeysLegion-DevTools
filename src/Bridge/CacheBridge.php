<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Bridge;

use MonkeysLegion\DevTools\Collector\CacheCollector;
use Psr\SimpleCache\CacheInterface;

/**
 * Decorating wrapper that proxies a PSR-16 CacheInterface and reports
 * every cache operation to the DevTools CacheCollector.
 *
 * Wrap your existing cache store with this bridge to get automatic
 * hit/miss tracking in the DevTools toolbar:
 *
 *   $bridge = new CacheBridge($realCache, $cacheCollector);
 *   // use $bridge as your CacheInterface
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CacheBridge implements CacheInterface
{
    public function __construct(
        private readonly CacheInterface $inner,
        private readonly CacheCollector $cacheCollector,
        private readonly string $storeName = 'default',
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $start = hrtime(true);
        $value = $this->inner->get($key, $default);
        $durationMs = (hrtime(true) - $start) / 1e6;

        $hit = $value !== $default;
        $this->cacheCollector->recordOperation(
            store: $this->storeName,
            key: $key,
            operation: 'get',
            hit: $hit,
            durationMs: $durationMs,
        );

        return $value;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $start = hrtime(true);
        $result = $this->inner->set($key, $value, $ttl);
        $durationMs = (hrtime(true) - $start) / 1e6;

        $this->cacheCollector->recordOperation(
            store: $this->storeName,
            key: $key,
            operation: 'set',
            hit: true,
            durationMs: $durationMs,
            ttl: is_int($ttl) ? $ttl : null,
        );

        return $result;
    }

    public function delete(string $key): bool
    {
        $start = hrtime(true);
        $result = $this->inner->delete($key);
        $durationMs = (hrtime(true) - $start) / 1e6;

        $this->cacheCollector->recordOperation(
            store: $this->storeName,
            key: $key,
            operation: 'delete',
            hit: $result,
            durationMs: $durationMs,
        );

        return $result;
    }

    public function clear(): bool
    {
        $start = hrtime(true);
        $result = $this->inner->clear();
        $durationMs = (hrtime(true) - $start) / 1e6;

        $this->cacheCollector->recordOperation(
            store: $this->storeName,
            key: '*',
            operation: 'clear',
            hit: true,
            durationMs: $durationMs,
        );

        return $result;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $start = hrtime(true);
        $values = $this->inner->getMultiple($keys, $default);
        $durationMs = (hrtime(true) - $start) / 1e6;

        foreach ($keys as $key) {
            $this->cacheCollector->recordOperation(
                store: $this->storeName,
                key: $key,
                operation: 'get',
                hit: isset($values[$key]) && $values[$key] !== $default,
                durationMs: $durationMs / max(1, count(is_array($keys) ? $keys : iterator_to_array($keys))),
            );
        }

        return $values;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $start = hrtime(true);
        $result = $this->inner->setMultiple($values, $ttl);
        $durationMs = (hrtime(true) - $start) / 1e6;

        foreach ($values as $key => $value) {
            $this->cacheCollector->recordOperation(
                store: $this->storeName,
                key: (string) $key,
                operation: 'set',
                hit: true,
                durationMs: 0.0,
                ttl: is_int($ttl) ? $ttl : null,
            );
        }

        return $result;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $start = hrtime(true);
        $result = $this->inner->deleteMultiple($keys);
        $durationMs = (hrtime(true) - $start) / 1e6;

        foreach ($keys as $key) {
            $this->cacheCollector->recordOperation(
                store: $this->storeName,
                key: $key,
                operation: 'delete',
                hit: true,
                durationMs: 0.0,
            );
        }

        return $result;
    }

    public function has(string $key): bool
    {
        $start = hrtime(true);
        $result = $this->inner->has($key);
        $durationMs = (hrtime(true) - $start) / 1e6;

        $this->cacheCollector->recordOperation(
            store: $this->storeName,
            key: $key,
            operation: 'has',
            hit: $result,
            durationMs: $durationMs,
        );

        return $result;
    }
}
