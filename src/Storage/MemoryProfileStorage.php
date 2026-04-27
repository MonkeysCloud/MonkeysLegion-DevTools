<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Storage;

use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;
use MonkeysLegion\DevTools\Profiler\Profile;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * In-memory profile storage for testing and short-lived processes.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class MemoryProfileStorage implements ProfileStorageInterface
{
    /** @var array<string, Profile> id => profile */
    private array $profiles = [];

    /** @var list<string> Ordered IDs (newest first) */
    private array $order = [];

    /**
     * Number of stored profiles — computed property hook.
     */
    public int $size {
        get => count($this->profiles);
    }

    public function save(Profile $profile): void
    {
        $this->profiles[$profile->id] = $profile;
        array_unshift($this->order, $profile->id);
    }

    public function find(string $id): ?Profile
    {
        return $this->profiles[$id] ?? null;
    }

    public function latest(int $limit = 50): array
    {
        $ids = array_slice($this->order, 0, $limit);
        $profiles = [];

        foreach ($ids as $id) {
            if (isset($this->profiles[$id])) {
                $profiles[] = $this->profiles[$id];
            }
        }

        return $profiles;
    }

    public function query(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $matched = [];

        foreach ($this->order as $id) {
            $profile = $this->profiles[$id] ?? null;
            if ($profile === null) {
                continue;
            }

            if (!$this->matchesFilters($profile, $filters)) {
                continue;
            }

            $matched[] = $profile;
        }

        return array_slice($matched, $offset, $limit);
    }

    public function delete(string $id): void
    {
        unset($this->profiles[$id]);
        $this->order = array_values(array_filter(
            $this->order,
            static fn(string $orderId): bool => $orderId !== $id,
        ));
    }

    public function clear(): int
    {
        $count = count($this->profiles);
        $this->profiles = [];
        $this->order = [];

        return $count;
    }

    public function count(): int
    {
        return count($this->profiles);
    }

    public function prune(): int
    {
        return 0;
    }

    // ── Private ─────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $filters
     */
    private function matchesFilters(Profile $profile, array $filters): bool
    {
        if (isset($filters['method']) && $profile->method !== $filters['method']) {
            return false;
        }

        if (isset($filters['uri']) && !str_contains($profile->uri, (string) $filters['uri'])) {
            return false;
        }

        if (isset($filters['status_min']) && $profile->statusCode < $filters['status_min']) {
            return false;
        }

        if (isset($filters['status_max']) && $profile->statusCode > $filters['status_max']) {
            return false;
        }

        if (isset($filters['min_duration_ms']) && $profile->durationMs < $filters['min_duration_ms']) {
            return false;
        }

        if (isset($filters['environment']) && $profile->environment !== $filters['environment']) {
            return false;
        }

        return true;
    }
}
