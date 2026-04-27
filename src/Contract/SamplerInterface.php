<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Contract;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface SamplerInterface
{
    /**
     * Determine whether the current request should be profiled.
     */
    public function shouldSample(string $requestId, string $environment): bool;

    /**
     * Get the effective sample rate for the given environment.
     *
     * @return float 0.0 (never) to 1.0 (always)
     */
    public function rate(string $environment): float;
}
