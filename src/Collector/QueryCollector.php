<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Collector;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\ProfileContext;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Captures SQL queries with timing, duplicate detection, N+1 heuristics,
 * and source-file tracing. Beyond Symfony/Laravel:
 * - Automatic N+1 pattern detection via query fingerprinting
 * - Duplicate query grouping with count
 * - Per-query memory delta
 * - Transaction depth tracking
 * - Source file/line from backtrace
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class QueryCollector implements CollectorInterface
{
    /** @var list<array{sql: string, bindings: list<mixed>, duration_ms: float, connection: string, source_file: string, source_line: int, memory_before: int, memory_after: int, transaction_depth: int, started_at: float}> */
    private array $queries = [];

    /** @var array<string, int> fingerprint => count for duplicate detection */
    private array $fingerprints = [];

    /**
     * Total number of queries executed.
     */
    public int $queryCount {
        get => count($this->queries);
    }

    /**
     * Total query time in milliseconds.
     */
    public float $totalDurationMs {
        get {
            $total = 0.0;
            foreach ($this->queries as $q) {
                $total += $q['duration_ms'];
            }

            return $total;
        }
    }

    /**
     * Number of duplicate queries detected.
     */
    public int $duplicateCount {
        get {
            $count = 0;
            foreach ($this->fingerprints as $c) {
                if ($c > 1) {
                    $count += $c - 1;
                }
            }

            return $count;
        }
    }

    /**
     * Whether N+1 pattern is likely (same query fingerprint executed 5+ times).
     */
    public bool $hasNPlusOne {
        get {
            foreach ($this->fingerprints as $count) {
                if ($count >= $this->nPlusOneThreshold) {
                    return true;
                }
            }

            return false;
        }
    }

    /**
     * Slowest query duration in milliseconds.
     */
    public float $slowestQueryMs {
        get {
            $max = 0.0;
            foreach ($this->queries as $q) {
                if ($q['duration_ms'] > $max) {
                    $max = $q['duration_ms'];
                }
            }

            return $max;
        }
    }

    public function __construct(
        private readonly bool $enabled = true,
        private readonly float $slowQueryThresholdMs = 100.0,
        private readonly int $nPlusOneThreshold = 5,
        private readonly int $maxQueries = 500,
        private readonly int $backtraceDepth = 10,
    ) {}

    public function name(): string
    {
        return 'query';
    }

    public function label(): string
    {
        return 'Queries';
    }

    public function icon(): string
    {
        return '🗄️';
    }

    public function priority(): int
    {
        return 800;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(ProfileContext $context): void
    {
        $this->queries = [];
        $this->fingerprints = [];
    }

    public function stop(ProfileContext $context): void
    {
        // All data collected via recordQuery()
    }

    /**
     * Record a SQL query execution.
     *
     * Call this from a database driver hook or query builder listener.
     *
     * @param list<mixed> $bindings
     */
    public function recordQuery(
        string $sql,
        array $bindings = [],
        float $durationMs = 0.0,
        string $connection = 'default',
        int $transactionDepth = 0,
    ): void {
        if (count($this->queries) >= $this->maxQueries) {
            return;
        }

        // Source tracing from backtrace
        $source = $this->resolveSource();

        $memBefore = memory_get_usage(true);

        $this->queries[] = [
            'sql'               => $sql,
            'bindings'          => $bindings,
            'duration_ms'       => $durationMs,
            'connection'        => $connection,
            'source_file'       => $source['file'],
            'source_line'       => $source['line'],
            'memory_before'     => $memBefore,
            'memory_after'      => memory_get_usage(true),
            'transaction_depth' => $transactionDepth,
            'started_at'        => hrtime(true) / 1e6,
        ];

        // Fingerprint for duplicate/N+1 detection
        $fingerprint = $this->fingerprint($sql);
        $this->fingerprints[$fingerprint] = ($this->fingerprints[$fingerprint] ?? 0) + 1;
    }

    public function collect(ProfileContext $context): array
    {
        $queries = [];
        $slowQueries = [];
        $duplicates = [];

        foreach ($this->queries as $i => $q) {
            $fp = $this->fingerprint($q['sql']);
            $isDuplicate = ($this->fingerprints[$fp] ?? 0) > 1;
            $isSlow = $q['duration_ms'] >= $this->slowQueryThresholdMs;

            $entry = [
                'index'             => $i,
                'sql'               => $q['sql'],
                'bindings_count'    => count($q['bindings']),
                'duration_ms'       => round($q['duration_ms'], 3),
                'connection'        => $q['connection'],
                'source'            => "{$q['source_file']}:{$q['source_line']}",
                'transaction_depth' => $q['transaction_depth'],
                'is_slow'           => $isSlow,
                'is_duplicate'      => $isDuplicate,
                'fingerprint'       => $fp,
            ];

            $queries[] = $entry;

            if ($isSlow) {
                $slowQueries[] = $entry;
            }
        }

        // Build duplicate groups
        foreach ($this->fingerprints as $fp => $count) {
            if ($count > 1) {
                // Find the first query with this fingerprint for the SQL
                $sample = '';
                foreach ($this->queries as $q) {
                    if ($this->fingerprint($q['sql']) === $fp) {
                        $sample = $q['sql'];
                        break;
                    }
                }

                $duplicates[] = [
                    'fingerprint' => $fp,
                    'count'       => $count,
                    'sql_sample'  => $sample,
                    'is_n_plus_one' => $count >= $this->nPlusOneThreshold,
                ];
            }
        }

        // Sort slow queries by duration descending
        usort($slowQueries, static fn(array $a, array $b): int => $b['duration_ms'] <=> $a['duration_ms']);

        return [
            'queries'           => $queries,
            'count'             => $this->queryCount,
            'total_duration_ms' => round($this->totalDurationMs, 3),
            'slow_queries'      => $slowQueries,
            'slow_count'        => count($slowQueries),
            'duplicates'        => $duplicates,
            'duplicate_count'   => $this->duplicateCount,
            'has_n_plus_one'    => $this->hasNPlusOne,
            'slowest_ms'        => round($this->slowestQueryMs, 3),
        ];
    }

    // ── Private ─────────────────────────────────────────────────

    /**
     * Create a stable fingerprint by normalizing SQL.
     *
     * Replaces literal values with ? placeholders for grouping.
     */
    private function fingerprint(string $sql): string
    {
        // Normalize: collapse whitespace, replace quoted strings and numbers
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;
        $normalized = preg_replace("/'.+?'/", '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b\d+\b/', '?', $normalized) ?? $normalized;

        return hash('xxh3', strtolower($normalized));
    }

    /**
     * Resolve the app-level source file/line that triggered the query.
     *
     * @return array{file: string, line: int}
     */
    private function resolveSource(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->backtraceDepth);

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';

            // Skip framework internals — find the app-level caller
            if ($file !== '' && !str_contains($file, '/vendor/') && !str_contains($file, '/DevTools/')) {
                return [
                    'file' => $file,
                    'line' => (int) ($frame['line'] ?? 0),
                ];
            }
        }

        // Fallback to direct caller
        return [
            'file' => $trace[1]['file'] ?? '[unknown]',
            'line' => (int) ($trace[1]['line'] ?? 0),
        ];
    }
}
