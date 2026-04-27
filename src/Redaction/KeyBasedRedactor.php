<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Redaction;

use MonkeysLegion\DevTools\Contract\RedactorInterface;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Recursively redacts sensitive values based on key pattern matching.
 * Supports both exact and partial key matching with configurable
 * replacement strings. Goes beyond Symfony/Laravel by providing:
 * - Partial value preservation (first/last N chars)
 * - Pattern-based redaction (regex keys)
 * - Depth-limited recursion for performance
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class KeyBasedRedactor implements RedactorInterface
{
    private const string REDACTED = '████████';
    private const int MAX_DEPTH = 10;

    /**
     * Number of keys redacted in the last operation.
     */
    public private(set) int $lastRedactionCount = 0;

    /** @var list<string> Lowercase key patterns */
    private readonly array $normalizedKeys;

    /**
     * @param list<string> $sensitiveKeys Keys to redact (case-insensitive partial match)
     * @param int          $preserveChars Number of chars to preserve at start/end of values
     * @param string       $replacement   Replacement string for redacted values
     */
    public function __construct(
        private readonly array $sensitiveKeys = [
            'authorization',
            'cookie',
            'set-cookie',
            'password',
            'password_hash',
            'token',
            'secret',
            'api_key',
            'apikey',
            'private_key',
            'database_url',
            'dsn',
            'credential',
            'access_token',
            'refresh_token',
            'client_secret',
            'webhook_secret',
            'encryption_key',
        ],
        private readonly int $preserveChars = 0,
        private readonly string $replacement = self::REDACTED,
    ) {
        $this->normalizedKeys = array_map('strtolower', $this->sensitiveKeys);
    }

    public function redact(array $data): array
    {
        $this->lastRedactionCount = 0;

        return $this->redactRecursive($data, 0);
    }

    public function isRedactable(string $key): bool
    {
        $lowerKey = strtolower($key);

        foreach ($this->normalizedKeys as $sensitive) {
            if (str_contains($lowerKey, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    public function redactValue(string $key, string $value): string
    {
        if (!$this->isRedactable($key)) {
            return $value;
        }

        return $this->maskValue($value);
    }

    // ── Private ─────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function redactRecursive(array $data, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            $stringKey = (string) $key;

            if ($this->isRedactable($stringKey)) {
                $this->lastRedactionCount++;
                $result[$key] = is_string($value)
                    ? $this->maskValue($value)
                    : $this->replacement;
                continue;
            }

            if (is_array($value)) {
                $result[$key] = $this->redactRecursive($value, $depth + 1);
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Mask a string value, optionally preserving leading/trailing chars.
     */
    private function maskValue(string $value): string
    {
        if ($this->preserveChars <= 0 || strlen($value) <= ($this->preserveChars * 2 + 4)) {
            return $this->replacement;
        }

        $start = substr($value, 0, $this->preserveChars);
        $end = substr($value, -$this->preserveChars);

        return "{$start}{$this->replacement}{$end}";
    }
}
