<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Storage;

use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;
use MonkeysLegion\DevTools\Profiler\Profile;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * File-based profile storage using JSON serialization.
 * Designed for local development with automatic retention enforcement.
 *
 * Advantages over Symfony's FileProfilerStorage:
 * - JSON format (human-readable, diffable)
 * - Index file for fast listing without reading all profiles
 * - Retention pruning on write (not separate cron)
 * - Query filtering without full deserialization
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class FileProfileStorage implements ProfileStorageInterface
{
    private const string INDEX_FILE = '_index.json';
    private const int DEFAULT_MAX_PROFILES = 1000;
    private const int DEFAULT_RETENTION_DAYS = 7;

    /**
     * Number of stored profiles (from index).
     */
    public int $profileCount {
        get => count($this->loadIndex());
    }

    /**
     * Storage directory path.
     */
    public string $storagePath {
        get => $this->path;
    }

    public function __construct(
        private readonly string $path = 'var/devtools/profiles',
        private readonly int $maxProfiles = self::DEFAULT_MAX_PROFILES,
        private readonly int $retentionDays = self::DEFAULT_RETENTION_DAYS,
    ) {
        $this->ensureDirectory();
    }

    public function save(Profile $profile): void
    {
        $this->ensureDirectory();

        // Write profile data
        $profilePath = $this->profilePath($profile->id);
        file_put_contents(
            $profilePath,
            json_encode($profile->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        // Update index
        $index = $this->loadIndex();
        array_unshift($index, [
            'id'          => $profile->id,
            'method'      => $profile->method,
            'uri'         => $profile->uri,
            'status_code' => $profile->statusCode,
            'duration_ms' => $profile->durationMs,
            'memory_peak' => $profile->memoryPeak,
            'environment' => $profile->environment,
            'created_at'  => $profile->createdAt->format('Y-m-d\TH:i:s.uP'),
            'timestamp'   => time(),
        ]);

        $this->saveIndex($index);

        // Prune on write — keep storage bounded
        $this->prune();
    }

    public function find(string $id): ?Profile
    {
        $path = $this->profilePath($id);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return Profile::fromArray($data);
    }

    public function latest(int $limit = 50): array
    {
        $index = $this->loadIndex();
        $slice = array_slice($index, 0, $limit);

        $profiles = [];
        foreach ($slice as $entry) {
            $profile = $this->find((string) $entry['id']);
            if ($profile !== null) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }

    public function query(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $index = $this->loadIndex();
        $matched = [];

        foreach ($index as $entry) {
            if (!$this->matchesFilters($entry, $filters)) {
                continue;
            }

            $matched[] = $entry;
        }

        $slice = array_slice($matched, $offset, $limit);
        $profiles = [];

        foreach ($slice as $entry) {
            $profile = $this->find((string) $entry['id']);
            if ($profile !== null) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }

    public function delete(string $id): void
    {
        $path = $this->profilePath($id);

        if (file_exists($path)) {
            unlink($path);
        }

        // Remove from index
        $index = $this->loadIndex();
        $index = array_values(array_filter(
            $index,
            static fn(array $entry): bool => ($entry['id'] ?? '') !== $id,
        ));
        $this->saveIndex($index);
    }

    public function clear(): int
    {
        $index = $this->loadIndex();
        $count = count($index);

        // Delete all profile files
        foreach ($index as $entry) {
            $path = $this->profilePath((string) $entry['id']);
            if (file_exists($path)) {
                unlink($path);
            }
        }

        // Clear index
        $this->saveIndex([]);

        return $count;
    }

    public function count(): int
    {
        return count($this->loadIndex());
    }

    public function prune(): int
    {
        $index = $this->loadIndex();
        $pruned = 0;

        // Enforce retention days
        if ($this->retentionDays > 0) {
            $cutoff = time() - ($this->retentionDays * 86400);

            foreach ($index as $i => $entry) {
                $timestamp = (int) ($entry['timestamp'] ?? 0);
                if ($timestamp > 0 && $timestamp < $cutoff) {
                    $this->deleteProfileFile((string) $entry['id']);
                    unset($index[$i]);
                    $pruned++;
                }
            }

            $index = array_values($index);
        }

        // Enforce max profiles
        if (count($index) > $this->maxProfiles) {
            $excess = array_slice($index, $this->maxProfiles);
            $index = array_slice($index, 0, $this->maxProfiles);

            foreach ($excess as $entry) {
                $this->deleteProfileFile((string) $entry['id']);
                $pruned++;
            }
        }

        if ($pruned > 0) {
            $this->saveIndex($index);
        }

        return $pruned;
    }

    // ── Private Helpers ─────────────────────────────────────────

    private function profilePath(string $id): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $id . '.json';
    }

    private function indexPath(): string
    {
        return $this->path . DIRECTORY_SEPARATOR . self::INDEX_FILE;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadIndex(): array
    {
        $path = $this->indexPath();

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : [];
    }

    /**
     * @param list<array<string, mixed>> $index
     */
    private function saveIndex(array $index): void
    {
        file_put_contents(
            $this->indexPath(),
            json_encode($index, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    private function deleteProfileFile(string $id): void
    {
        $path = $this->profilePath($id);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @param array<string, mixed>                $entry
     * @param array<string, mixed>                $filters
     */
    private function matchesFilters(array $entry, array $filters): bool
    {
        if (isset($filters['method']) && ($entry['method'] ?? '') !== $filters['method']) {
            return false;
        }

        if (isset($filters['uri']) && !str_contains((string) ($entry['uri'] ?? ''), (string) $filters['uri'])) {
            return false;
        }

        if (isset($filters['status_min']) && ($entry['status_code'] ?? 0) < $filters['status_min']) {
            return false;
        }

        if (isset($filters['status_max']) && ($entry['status_code'] ?? 0) > $filters['status_max']) {
            return false;
        }

        if (isset($filters['min_duration_ms']) && ($entry['duration_ms'] ?? 0) < $filters['min_duration_ms']) {
            return false;
        }

        if (isset($filters['environment']) && ($entry['environment'] ?? '') !== $filters['environment']) {
            return false;
        }

        if (isset($filters['since']) && ($entry['timestamp'] ?? 0) < $filters['since']) {
            return false;
        }

        if (isset($filters['until']) && ($entry['timestamp'] ?? 0) > $filters['until']) {
            return false;
        }

        return true;
    }
}
