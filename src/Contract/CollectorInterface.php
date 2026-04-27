<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Contract;

use MonkeysLegion\DevTools\Profiler\ProfileContext;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface CollectorInterface
{
    /**
     * Unique collector identifier.
     */
    public function name(): string;

    /**
     * Whether this collector is active for the current environment.
     */
    public function isEnabled(): bool;

    /**
     * Begin data collection for the current request.
     */
    public function start(ProfileContext $context): void;

    /**
     * Finalize data collection after the request completes.
     */
    public function stop(ProfileContext $context): void;

    /**
     * Return collected data as a serializable array.
     *
     * @return array<string, mixed>
     */
    public function collect(ProfileContext $context): array;

    /**
     * Collector priority — higher values run first on start, last on stop.
     *
     * This enables collectors to wrap each other (e.g., timing middleware).
     */
    public function priority(): int;

    /**
     * Human-readable label for toolbar/CLI display.
     */
    public function label(): string;

    /**
     * Collector icon identifier for toolbar panels.
     */
    public function icon(): string;
}
