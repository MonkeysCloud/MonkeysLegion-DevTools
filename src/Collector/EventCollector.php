<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Collector;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\ProfileContext;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Captures event dispatches with timeline ordering, per-listener timing,
 * and slow-listener detection. Beyond Symfony/Laravel:
 * - Chronological timeline with relative timestamps
 * - Per-listener duration breakdown
 * - Failed listener tracking
 * - Event storm detection (same event dispatched 10+ times)
 * - Async/queue listener correlation
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class EventCollector implements CollectorInterface
{
    /** @var list<array{event: string, listeners: list<array{name: string, duration_ms: float, failed: bool, async: bool}>, timestamp: float, memory: int}> */
    private array $dispatches = [];

    /** @var array<string, int> event class => dispatch count for storm detection */
    private array $dispatchCounts = [];

    private float $profileStartMs = 0.0;

    /**
     * Total events dispatched.
     */
    public int $eventCount {
        get => count($this->dispatches);
    }

    /**
     * Total listener executions.
     */
    public int $listenerCount {
        get {
            $total = 0;
            foreach ($this->dispatches as $d) {
                $total += count($d['listeners']);
            }

            return $total;
        }
    }

    /**
     * Whether any event storm is detected (10+ dispatches of same event).
     */
    public bool $hasStorm {
        get {
            foreach ($this->dispatchCounts as $count) {
                if ($count >= $this->stormThreshold) {
                    return true;
                }
            }

            return false;
        }
    }

    /**
     * Total listener execution time.
     */
    public float $totalListenerMs {
        get {
            $total = 0.0;
            foreach ($this->dispatches as $d) {
                foreach ($d['listeners'] as $l) {
                    $total += $l['duration_ms'];
                }
            }

            return $total;
        }
    }

    /**
     * Number of failed listeners.
     */
    public int $failedListenerCount {
        get {
            $count = 0;
            foreach ($this->dispatches as $d) {
                foreach ($d['listeners'] as $l) {
                    if ($l['failed']) {
                        $count++;
                    }
                }
            }

            return $count;
        }
    }

    public function __construct(
        private readonly bool $enabled = true,
        private readonly int $stormThreshold = 10,
        private readonly float $slowListenerMs = 50.0,
    ) {}

    public function name(): string
    {
        return 'event';
    }

    public function label(): string
    {
        return 'Events';
    }

    public function icon(): string
    {
        return '📡';
    }

    public function priority(): int
    {
        return 600;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(ProfileContext $context): void
    {
        $this->dispatches = [];
        $this->dispatchCounts = [];
        $this->profileStartMs = $context->startedAt;
    }

    public function stop(ProfileContext $context): void
    {
        // Data collected via recordDispatch()
    }

    /**
     * Record an event dispatch with its listener executions.
     *
     * @param list<array{name: string, duration_ms: float, failed?: bool, async?: bool}> $listeners
     */
    public function recordDispatch(
        string $eventClass,
        array $listeners = [],
    ): void {
        $normalized = [];
        foreach ($listeners as $l) {
            $normalized[] = [
                'name'        => $l['name'],
                'duration_ms' => $l['duration_ms'],
                'failed'      => $l['failed'] ?? false,
                'async'       => $l['async'] ?? false,
            ];
        }

        $this->dispatches[] = [
            'event'     => $eventClass,
            'listeners' => $normalized,
            'timestamp' => hrtime(true) / 1e6,
            'memory'    => memory_get_usage(true),
        ];

        $this->dispatchCounts[$eventClass] = ($this->dispatchCounts[$eventClass] ?? 0) + 1;
    }

    public function collect(ProfileContext $context): array
    {
        $timeline = [];
        $slowListeners = [];
        $storms = [];

        foreach ($this->dispatches as $i => $d) {
            $relativeMs = round($d['timestamp'] - $this->profileStartMs, 3);
            $shortEvent = $this->shortClassName($d['event']);

            $listenerEntries = [];
            foreach ($d['listeners'] as $l) {
                $entry = [
                    'name'        => $this->shortClassName($l['name']),
                    'full_name'   => $l['name'],
                    'duration_ms' => round($l['duration_ms'], 3),
                    'failed'      => $l['failed'],
                    'async'       => $l['async'],
                    'is_slow'     => $l['duration_ms'] >= $this->slowListenerMs,
                ];

                $listenerEntries[] = $entry;

                if ($entry['is_slow']) {
                    $slowListeners[] = [
                        'event'       => $shortEvent,
                        'listener'    => $entry['name'],
                        'duration_ms' => $entry['duration_ms'],
                    ];
                }
            }

            $timeline[] = [
                'index'          => $i,
                'event'          => $shortEvent,
                'full_event'     => $d['event'],
                'relative_ms'    => $relativeMs,
                'listener_count' => count($listenerEntries),
                'listeners'      => $listenerEntries,
            ];
        }

        // Detect storms
        foreach ($this->dispatchCounts as $event => $count) {
            if ($count >= $this->stormThreshold) {
                $storms[] = [
                    'event' => $this->shortClassName($event),
                    'count' => $count,
                ];
            }
        }

        // Sort slow listeners by duration
        usort($slowListeners, static fn(array $a, array $b): int => $b['duration_ms'] <=> $a['duration_ms']);

        return [
            'timeline'             => $timeline,
            'event_count'          => $this->eventCount,
            'listener_count'       => $this->listenerCount,
            'total_listener_ms'    => round($this->totalListenerMs, 3),
            'slow_listeners'       => $slowListeners,
            'failed_listener_count' => $this->failedListenerCount,
            'storms'               => $storms,
            'has_storm'            => $this->hasStorm,
        ];
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
