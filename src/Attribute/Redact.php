<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Attribute;

use Attribute;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Mark a property or parameter as sensitive for redaction.
 * When DevTools collects data from objects with this attribute,
 * the value will be automatically redacted.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Redact
{
    public function __construct(
        public readonly string $replacement = '████████',
    ) {}
}
