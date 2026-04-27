<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Contract;

use MonkeysLegion\DevTools\Profiler\Profile;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface ProfileStorageInterface
{
    /**
     * Persist a profile.
     */
    public function save(Profile $profile): void;

    /**
     * Retrieve a single profile by ID.
     */
    public function find(string $id): ?Profile;

    /**
     * Retrieve the most recent profiles.
     *
     * @return list<Profile>
     */
    public function latest(int $limit = 50): array;

    /**
     * Retrieve profiles matching filter criteria.
     *
     * @param array{
     *   method?: string,
     *   uri?: string,
     *   status_min?: int,
     *   status_max?: int,
     *   min_duration_ms?: float,
     *   environment?: string,
     *   since?: float,
     *   until?: float,
     * } $filters
     *
     * @return list<Profile>
     */
    public function query(array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * Delete a single profile.
     */
    public function delete(string $id): void;

    /**
     * Clear all stored profiles.
     *
     * @return int Number of profiles removed
     */
    public function clear(): int;

    /**
     * Count total stored profiles.
     */
    public function count(): int;

    /**
     * Enforce retention policies (age, max count).
     *
     * @return int Number of profiles pruned
     */
    public function prune(): int;
}
