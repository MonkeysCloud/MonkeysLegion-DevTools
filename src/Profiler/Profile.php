<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Profiler;

use DateTimeImmutable;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Represents a complete profiled request with all collector data.
 * Uses PHP 8.4 property hooks for computed properties and
 * asymmetric visibility for immutable-after-creation semantics.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class Profile
{
    // ── Identity ────────────────────────────────────────────────

    public private(set) string $id;
    public private(set) string $traceId;
    public private(set) string $environment;

    // ── Timing ──────────────────────────────────────────────────

    public private(set) float $startedAt;
    public private(set) float $endedAt;

    /**
     * Request duration in milliseconds — computed from timestamps.
     */
    public float $durationMs {
        get => $this->endedAt - $this->startedAt;
    }

    // ── Memory ──────────────────────────────────────────────────

    public private(set) int $memoryStart;
    public private(set) int $memoryPeak;

    /**
     * Memory delta in bytes — computed from start vs peak.
     */
    public int $memoryDelta {
        get => $this->memoryPeak - $this->memoryStart;
    }

    /**
     * Human-readable memory peak — formatted automatically.
     */
    public string $memoryPeakFormatted {
        get => $this->formatBytes($this->memoryPeak);
    }

    // ── Request Summary ─────────────────────────────────────────

    public private(set) string $method = '';
    public private(set) string $uri = '';
    public private(set) int $statusCode = 0;
    public private(set) int $responseSize = 0;

    /**
     * Whether the request resulted in an error status.
     */
    public bool $isError {
        get => $this->statusCode >= 400;
    }

    /**
     * Whether the request is considered slow (> 200ms default).
     */
    public bool $isSlow {
        get => $this->durationMs > $this->slowThresholdMs;
    }

    // ── Collector Data ──────────────────────────────────────────

    /** @var array<string, array<string, mixed>> */
    public private(set) array $collectors = [];

    // ── Metadata ────────────────────────────────────────────────

    public private(set) DateTimeImmutable $createdAt;

    /**
     * ISO 8601 timestamp — formatted for display.
     */
    public string $createdAtFormatted {
        get => $this->createdAt->format('Y-m-d H:i:s.v');
    }

    /**
     * Duration formatted for display — includes unit.
     */
    public string $durationFormatted {
        get {
            $ms = $this->durationMs;

            if ($ms < 1.0) {
                return sprintf('%.0fμs', $ms * 1000);
            }

            if ($ms < 1000.0) {
                return sprintf('%.1fms', $ms);
            }

            return sprintf('%.2fs', $ms / 1000);
        }
    }

    /**
     * Short status badge — colored indicator for CLI/toolbar.
     */
    public string $statusBadge {
        get => match (true) {
            $this->statusCode >= 500 => '🔴',
            $this->statusCode >= 400 => '🟠',
            $this->statusCode >= 300 => '🔵',
            $this->statusCode >= 200 => '🟢',
            default                  => '⚪',
        };
    }

    // ── Internal ────────────────────────────────────────────────

    private float $slowThresholdMs = 200.0;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    // ── Builder Methods ─────────────────────────────────────────

    /**
     * Create a Profile from a completed ProfileContext.
     *
     * @param array<string, array<string, mixed>> $collectorData
     */
    public static function fromContext(
        ProfileContext $context,
        float $endedAt,
        array $collectorData,
        string $method = '',
        string $uri = '',
        int $statusCode = 0,
        int $responseSize = 0,
        float $slowThresholdMs = 200.0,
    ): self {
        $profile = new self();
        $profile->id = $context->id;
        $profile->traceId = $context->traceId;
        $profile->environment = $context->environment;
        $profile->startedAt = $context->startedAt;
        $profile->endedAt = $endedAt;
        $profile->memoryStart = $context->memoryStart;
        $profile->memoryPeak = memory_get_peak_usage(true);
        $profile->method = $method;
        $profile->uri = $uri;
        $profile->statusCode = $statusCode;
        $profile->responseSize = $responseSize;
        $profile->collectors = $collectorData;
        $profile->slowThresholdMs = $slowThresholdMs;

        return $profile;
    }

    /**
     * Reconstruct a Profile from stored data (deserialization).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $profile = new self();
        $profile->id = (string) ($data['id'] ?? '');
        $profile->traceId = (string) ($data['trace_id'] ?? '');
        $profile->environment = (string) ($data['environment'] ?? 'unknown');
        $profile->startedAt = (float) ($data['started_at'] ?? 0.0);
        $profile->endedAt = (float) ($data['ended_at'] ?? 0.0);
        $profile->memoryStart = (int) ($data['memory_start'] ?? 0);
        $profile->memoryPeak = (int) ($data['memory_peak'] ?? 0);
        $profile->method = (string) ($data['method'] ?? '');
        $profile->uri = (string) ($data['uri'] ?? '');
        $profile->statusCode = (int) ($data['status_code'] ?? 0);
        $profile->responseSize = (int) ($data['response_size'] ?? 0);
        $profile->collectors = (array) ($data['collectors'] ?? []);

        if (isset($data['created_at'])) {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', (string) $data['created_at']);
            if ($dt !== false) {
                $profile->createdAt = $dt;
            }
        }

        return $profile;
    }

    /**
     * Serialize to a storable array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'trace_id'      => $this->traceId,
            'environment'   => $this->environment,
            'started_at'    => $this->startedAt,
            'ended_at'      => $this->endedAt,
            'duration_ms'   => $this->durationMs,
            'memory_start'  => $this->memoryStart,
            'memory_peak'   => $this->memoryPeak,
            'method'        => $this->method,
            'uri'           => $this->uri,
            'status_code'   => $this->statusCode,
            'response_size' => $this->responseSize,
            'collectors'    => $this->collectors,
            'created_at'    => $this->createdAt->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    /**
     * Get data from a specific collector.
     *
     * @return array<string, mixed>
     */
    public function collector(string $name): array
    {
        return $this->collectors[$name] ?? [];
    }

    /**
     * Check if a collector has data.
     */
    public function hasCollector(string $name): bool
    {
        return isset($this->collectors[$name]);
    }

    // ── Private Helpers ─────────────────────────────────────────

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB'];
        $value = (float) $bytes;

        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024) {
                return sprintf('%.1f %s', $value, $unit);
            }
        }

        return sprintf('%.1f TB', $value / 1024);
    }
}
