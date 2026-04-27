<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Force profiling on a controller or action, even if
 * the global sample rate would otherwise skip it.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Profile
{
    public function __construct(
        public readonly string $name = '',
        public readonly bool $includePayload = false,
    ) {}
}
