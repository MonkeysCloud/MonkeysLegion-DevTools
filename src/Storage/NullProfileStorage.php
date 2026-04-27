<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Storage;

use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;
use MonkeysLegion\DevTools\Profiler\Profile;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * No-op storage for when profiling is disabled.
 * Ensures zero overhead in production.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class NullProfileStorage implements ProfileStorageInterface
{
    public function save(Profile $profile): void {}

    public function find(string $id): ?Profile
    {
        return null;
    }

    public function latest(int $limit = 50): array
    {
        return [];
    }

    public function query(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return [];
    }

    public function delete(string $id): void {}

    public function clear(): int
    {
        return 0;
    }

    public function count(): int
    {
        return 0;
    }

    public function prune(): int
    {
        return 0;
    }
}
