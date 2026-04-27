<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Sampler;

use MonkeysLegion\DevTools\Contract\SamplerInterface;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Probabilistic rate-based sampler with environment-specific overrides.
 * Supports deterministic sampling via request ID hashing for
 * consistent trace correlation across distributed systems.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RateSampler implements SamplerInterface
{
    /**
     * Effective sampling rate — clamped between 0.0 and 1.0.
     */
    public float $effectiveRate {
        get => max(0.0, min(1.0, $this->defaultRate));
    }

    /**
     * @param float                       $defaultRate     Default sample rate (0.0–1.0)
     * @param array<string, float>        $environmentRates Per-environment overrides
     * @param bool                        $deterministic   Use hash-based deterministic sampling
     */
    public function __construct(
        private readonly float $defaultRate = 1.0,
        private readonly array $environmentRates = [],
        private readonly bool $deterministic = false,
    ) {}

    public function shouldSample(string $requestId, string $environment): bool
    {
        $rate = $this->rate($environment);

        // Fast paths
        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        // Deterministic: hash-based for trace consistency
        if ($this->deterministic) {
            $hash = crc32($requestId);
            $normalized = ($hash & 0x7FFFFFFF) / 0x7FFFFFFF;

            return $normalized < $rate;
        }

        // Probabilistic: random sampling
        return (mt_rand() / mt_getrandmax()) < $rate;
    }

    public function rate(string $environment): float
    {
        $rate = $this->environmentRates[$environment] ?? $this->defaultRate;

        return max(0.0, min(1.0, $rate));
    }
}
