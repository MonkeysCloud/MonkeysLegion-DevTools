<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Exclude a controller or action from profiling.
 * Useful for health checks, metrics endpoints, and high-frequency routes.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class IgnoreProfile
{
    public function __construct(
        public readonly string $reason = '',
    ) {}
}
