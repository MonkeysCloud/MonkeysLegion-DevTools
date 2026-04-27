<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Lists recent profiled requests in a formatted table.
 * Supports filtering by method, status, and duration threshold.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[CommandAttr('devtools:requests', 'List recent profiled requests')]
final class DevToolsRequestsCommand extends Command
{
    public function __construct(
        private readonly ProfileStorageInterface $storage,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $limit = (int) ($this->option('limit') ?? 20);
        $method = $this->option('method');
        $slow = $this->hasOption('slow');
        $failed = $this->hasOption('failed');

        // Build filters
        $filters = [];

        if (is_string($method)) {
            $filters['method'] = strtoupper($method);
        }

        if ($slow) {
            $filters['min_duration_ms'] = (float) ($this->option('threshold') ?? 200);
        }

        if ($failed) {
            $filters['status_min'] = 400;
        }

        $profiles = $filters !== []
            ? $this->storage->query($filters, $limit)
            : $this->storage->latest($limit);

        if ($profiles === []) {
            $this->newLine();
            $this->comment('  No profiled requests found.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("  📊 Recent Requests ({$this->storage->count()} total)");
        $this->line(str_repeat('─', 100));
        $this->newLine();

        // Table header
        $this->line(sprintf(
            '  %s  %-8s %-40s %6s %10s %s',
            str_pad('ID', 12),
            'Method',
            'URI',
            'Status',
            'Duration',
            'Memory',
        ));
        $this->line('  ' . str_repeat('─', 96));

        foreach ($profiles as $profile) {
            $methodColored = $this->colorMethod($profile->method);
            $uri = strlen($profile->uri) > 38
                ? substr($profile->uri, 0, 35) . '...'
                : $profile->uri;

            $statusColored = match (true) {
                $profile->statusCode >= 500 => "\033[31m{$profile->statusCode}\033[0m",
                $profile->statusCode >= 400 => "\033[33m{$profile->statusCode}\033[0m",
                $profile->statusCode >= 300 => "\033[36m{$profile->statusCode}\033[0m",
                default                     => "\033[32m{$profile->statusCode}\033[0m",
            };

            $durationColored = $profile->isSlow
                ? "\033[31m{$profile->durationFormatted}\033[0m"
                : $profile->durationFormatted;

            $this->line(sprintf(
                '  %s  %-8s %-40s %6s %10s %s',
                substr($profile->id, 0, 12),
                $methodColored . str_repeat(' ', max(0, 8 - strlen($profile->method) - 9)),
                $uri,
                $statusColored . str_repeat(' ', max(0, 6 - strlen((string) $profile->statusCode) - 9)),
                str_pad($durationColored, 10 + ($profile->isSlow ? 9 : 0)),
                $profile->memoryPeakFormatted,
            ));
        }

        $this->newLine();
        $this->comment("  Showing {$limit} of {$this->storage->count()} profiles. Use --limit=N to change.");
        $this->newLine();

        return self::SUCCESS;
    }

    private function colorMethod(string $method): string
    {
        return match ($method) {
            'GET'     => "\033[32m{$method}\033[0m",
            'POST'    => "\033[33m{$method}\033[0m",
            'PUT'     => "\033[34m{$method}\033[0m",
            'PATCH'   => "\033[36m{$method}\033[0m",
            'DELETE'  => "\033[31m{$method}\033[0m",
            'OPTIONS' => "\033[35m{$method}\033[0m",
            default   => $method,
        };
    }
}
