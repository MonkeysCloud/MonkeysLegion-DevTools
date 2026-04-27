<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Collector;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\ProfileContext;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Captures middleware execution order and individual durations.
 * Beyond Symfony: provides per-middleware timing, bottleneck detection,
 * and middleware-level memory tracking.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class MiddlewareCollector implements CollectorInterface
{
    /** @var list<array{name: string, started_at: float, ended_at: float|null, memory_before: int, memory_after: int|null}> */
    private array $middlewareTimings = [];

    /**
     * Total number of middleware that executed.
     */
    public int $middlewareCount {
        get => count($this->middlewareTimings);
    }

    /**
     * Total middleware pipeline duration in milliseconds.
     */
    public float $totalDurationMs {
        get {
            $total = 0.0;
            foreach ($this->middlewareTimings as $timing) {
                if ($timing['ended_at'] !== null) {
                    $total += $timing['ended_at'] - $timing['started_at'];
                }
            }

            return $total;
        }
    }

    /**
     * Name of the slowest middleware — computed from timings.
     */
    public string $slowestMiddleware {
        get {
            $slowest = '';
            $maxDuration = 0.0;

            foreach ($this->middlewareTimings as $timing) {
                if ($timing['ended_at'] === null) {
                    continue;
                }

                $duration = $timing['ended_at'] - $timing['started_at'];
                if ($duration > $maxDuration) {
                    $maxDuration = $duration;
                    $slowest = $timing['name'];
                }
            }

            return $slowest;
        }
    }

    public function __construct(
        private readonly bool $enabled = true,
    ) {}

    public function name(): string
    {
        return 'middleware';
    }

    public function label(): string
    {
        return 'Middleware';
    }

    public function icon(): string
    {
        return '🔗';
    }

    public function priority(): int
    {
        return 950;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(ProfileContext $context): void
    {
        $this->middlewareTimings = [];
    }

    public function stop(ProfileContext $context): void
    {
        // Finalize any unclosed middleware entries
        foreach ($this->middlewareTimings as $i => $timing) {
            if ($timing['ended_at'] === null) {
                $this->middlewareTimings[$i]['ended_at'] = hrtime(true) / 1e6;
                $this->middlewareTimings[$i]['memory_after'] = memory_get_usage(true);
            }
        }
    }

    /**
     * Record middleware entry (call before executing middleware).
     */
    public function enter(string $middlewareName): void
    {
        $this->middlewareTimings[] = [
            'name'          => $middlewareName,
            'started_at'    => hrtime(true) / 1e6,
            'ended_at'      => null,
            'memory_before' => memory_get_usage(true),
            'memory_after'  => null,
        ];
    }

    /**
     * Record middleware exit (call after middleware completes).
     */
    public function leave(string $middlewareName): void
    {
        // Find the last entry for this middleware
        for ($i = count($this->middlewareTimings) - 1; $i >= 0; $i--) {
            if ($this->middlewareTimings[$i]['name'] === $middlewareName
                && $this->middlewareTimings[$i]['ended_at'] === null) {
                $this->middlewareTimings[$i]['ended_at'] = hrtime(true) / 1e6;
                $this->middlewareTimings[$i]['memory_after'] = memory_get_usage(true);

                return;
            }
        }
    }

    public function collect(ProfileContext $context): array
    {
        $entries = [];

        foreach ($this->middlewareTimings as $timing) {
            $durationMs = ($timing['ended_at'] !== null)
                ? round($timing['ended_at'] - $timing['started_at'], 3)
                : null;

            $memoryDelta = ($timing['memory_after'] !== null)
                ? $timing['memory_after'] - $timing['memory_before']
                : null;

            $entries[] = [
                'name'         => $timing['name'],
                'duration_ms'  => $durationMs,
                'memory_delta' => $memoryDelta,
                'completed'    => $timing['ended_at'] !== null,
            ];
        }

        return [
            'middleware'          => $entries,
            'count'              => $this->middlewareCount,
            'total_duration_ms'  => round($this->totalDurationMs, 3),
            'slowest'            => $this->slowestMiddleware,
        ];
    }
}
