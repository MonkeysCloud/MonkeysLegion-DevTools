<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Profiler;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Immutable context for a single profiling session.
 * Created at the start of a request and passed through all collectors.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ProfileContext
{
    public function __construct(
        public readonly string $id,
        public readonly string $traceId,
        public readonly float $startedAt,
        public readonly string $environment,
        public readonly bool $sampled,
        public readonly int $memoryStart = 0,
    ) {}

    /**
     * Create a new context for the current request.
     */
    public static function create(string $environment, bool $sampled): self
    {
        return new self(
            id: bin2hex(random_bytes(16)),
            traceId: bin2hex(random_bytes(16)),
            startedAt: hrtime(true) / 1e6,
            environment: $environment,
            sampled: $sampled,
            memoryStart: memory_get_usage(true),
        );
    }

    /**
     * Elapsed time in milliseconds since context creation.
     */
    public function elapsedMs(): float
    {
        return (hrtime(true) / 1e6) - $this->startedAt;
    }
}
