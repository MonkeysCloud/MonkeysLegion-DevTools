<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Profiler;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;
use MonkeysLegion\DevTools\Contract\RedactorInterface;
use MonkeysLegion\DevTools\Contract\SamplerInterface;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Central profiler engine — orchestrates collectors, sampling,
 * redaction, and storage. Designed for zero overhead when disabled.
 *
 * Competitive advantages over Symfony Profiler:
 * - Priority-ordered collectors with wrap semantics
 * - Built-in sampling for production safety
 * - Automatic redaction pipeline
 * - Environment-aware enable/disable
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class Profiler
{
    // ── Collectors ──────────────────────────────────────────────

    /** @var array<string, CollectorInterface> */
    private array $collectors = [];

    /** @var list<CollectorInterface> Sorted by priority (descending) */
    private array $sortedCollectors = [];

    private bool $sorted = false;

    // ── State ───────────────────────────────────────────────────

    private bool $enabled;
    private ?ProfileContext $activeContext = null;

    /**
     * Whether a profiling session is currently active.
     */
    public bool $isActive {
        get => $this->activeContext !== null;
    }

    /**
     * Number of registered collectors.
     */
    public int $collectorCount {
        get => count($this->collectors);
    }

    /**
     * Names of all registered collectors.
     *
     * @var list<string>
     */
    public array $collectorNames {
        get => array_keys($this->collectors);
    }

    public function __construct(
        private readonly ProfileStorageInterface $storage,
        private readonly RedactorInterface $redactor,
        private readonly SamplerInterface $sampler,
        private readonly string $environment = 'local',
        private readonly float $slowThresholdMs = 200.0,
        bool $enabled = true,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->enabled = $enabled;
    }

    // ── Collector Registration ──────────────────────────────────

    /**
     * Register a data collector.
     */
    public function addCollector(CollectorInterface $collector): void
    {
        $this->collectors[$collector->name()] = $collector;
        $this->sorted = false;
    }

    /**
     * Remove a collector by name.
     */
    public function removeCollector(string $name): void
    {
        unset($this->collectors[$name]);
        $this->sorted = false;
    }

    /**
     * Get a collector by name.
     */
    public function getCollector(string $name): ?CollectorInterface
    {
        return $this->collectors[$name] ?? null;
    }

    /**
     * Check if a collector is registered.
     */
    public function hasCollector(string $name): bool
    {
        return isset($this->collectors[$name]);
    }

    // ── Profiling Lifecycle ─────────────────────────────────────

    /**
     * Start profiling a request.
     *
     * Returns null if profiling is disabled or not sampled.
     */
    public function start(): ?ProfileContext
    {
        if (!$this->enabled) {
            return null;
        }

        $requestId = bin2hex(random_bytes(8));

        if (!$this->sampler->shouldSample($requestId, $this->environment)) {
            return null;
        }

        $context = ProfileContext::create(
            environment: $this->environment,
            sampled: true,
        );

        $this->activeContext = $context;
        $this->ensureSorted();

        // Start collectors in priority order (highest first)
        foreach ($this->sortedCollectors as $collector) {
            if (!$collector->isEnabled()) {
                continue;
            }

            try {
                $collector->start($context);
            } catch (\Throwable $e) {
                $this->logger->warning("Collector '{$collector->name()}' failed to start", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $context;
    }

    /**
     * Stop profiling and persist the result.
     *
     * Collectors are stopped in reverse priority order (lowest first)
     * to correctly unwrap timing layers.
     */
    public function stop(
        string $method = '',
        string $uri = '',
        int $statusCode = 0,
        int $responseSize = 0,
    ): ?Profile {
        if ($this->activeContext === null) {
            return null;
        }

        $context = $this->activeContext;
        $this->activeContext = null;
        $endedAt = hrtime(true) / 1e6;

        $collectorData = [];

        // Stop collectors in reverse priority order
        $reversed = array_reverse($this->sortedCollectors);

        foreach ($reversed as $collector) {
            if (!$collector->isEnabled()) {
                continue;
            }

            try {
                $collector->stop($context);
                $data = $collector->collect($context);
                $collectorData[$collector->name()] = $this->redactor->redact($data);
            } catch (\Throwable $e) {
                $this->logger->warning("Collector '{$collector->name()}' failed to collect", [
                    'error' => $e->getMessage(),
                ]);
                $collectorData[$collector->name()] = [
                    '_error' => $e->getMessage(),
                ];
            }
        }

        $profile = Profile::fromContext(
            context: $context,
            endedAt: $endedAt,
            collectorData: $collectorData,
            method: $method,
            uri: $uri,
            statusCode: $statusCode,
            responseSize: $responseSize,
            slowThresholdMs: $this->slowThresholdMs,
        );

        try {
            $this->storage->save($profile);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to persist profile', [
                'profile_id' => $profile->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $profile;
    }

    /**
     * Get the currently active profiling context.
     */
    public function activeContext(): ?ProfileContext
    {
        return $this->activeContext;
    }

    // ── Configuration ───────────────────────────────────────────

    /**
     * Enable or disable the profiler at runtime.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Whether the profiler is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // ── Private ─────────────────────────────────────────────────

    /**
     * Sort collectors by priority (descending) for correct execution order.
     */
    private function ensureSorted(): void
    {
        if ($this->sorted) {
            return;
        }

        $this->sortedCollectors = array_values($this->collectors);

        usort(
            $this->sortedCollectors,
            static fn(CollectorInterface $a, CollectorInterface $b): int => $b->priority() <=> $a->priority(),
        );

        $this->sorted = true;
    }
}
