<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Contract;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface RedactorInterface
{
    /**
     * Recursively redact sensitive values from a data array.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function redact(array $data): array;

    /**
     * Check whether a key should be redacted.
     */
    public function isRedactable(string $key): bool;

    /**
     * Redact a single string value (e.g., headers, query params).
     */
    public function redactValue(string $key, string $value): string;
}
