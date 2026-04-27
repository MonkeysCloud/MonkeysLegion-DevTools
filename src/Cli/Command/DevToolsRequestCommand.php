<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;
use MonkeysLegion\DevTools\Exception\ProfileNotFoundException;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Shows full detail for a single profiled request including
 * all collector data, timing breakdown, and metadata.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[CommandAttr('devtools:request', 'Show detailed profile for a single request')]
final class DevToolsRequestCommand extends Command
{
    public function __construct(
        private readonly ProfileStorageInterface $storage,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $id = $this->argument(0);

        // Support "latest" keyword
        if ($id === null || $id === 'latest') {
            $profiles = $this->storage->latest(1);
            if ($profiles === []) {
                $this->error('No profiles found.');

                return self::FAILURE;
            }
            $profile = $profiles[0];
        } else {
            $profile = $this->storage->find($id);
            if ($profile === null) {
                // Try partial ID match
                $all = $this->storage->latest(200);
                foreach ($all as $p) {
                    if (str_starts_with($p->id, $id)) {
                        $profile = $p;
                        break;
                    }
                }

                if ($profile === null) {
                    $this->error("Profile '{$id}' not found.");

                    return self::FAILURE;
                }
            }
        }

        $this->newLine();
        $this->info("  {$profile->statusBadge} Profile: {$profile->id}");
        $this->line(str_repeat('═', 70));
        $this->newLine();

        // Request summary
        $this->info('  ── Request ────────────────────────────────────────────');
        $this->line("  Method:       {$profile->method}");
        $this->line("  URI:          {$profile->uri}");
        $this->line("  Status:       {$profile->statusCode}");
        $this->line("  Duration:     {$profile->durationFormatted}" . ($profile->isSlow ? ' ⚠ SLOW' : ''));
        $this->line("  Memory Peak:  {$profile->memoryPeakFormatted}");
        $this->line("  Response:     {$profile->responseSize} bytes");
        $this->line("  Time:         {$profile->createdAtFormatted}");
        $this->line("  Environment:  {$profile->environment}");
        $this->line("  Trace ID:     {$profile->traceId}");
        $this->newLine();

        // Collector details
        foreach ($profile->collectors as $collectorName => $data) {
            $this->info("  ── {$this->formatCollectorName($collectorName)} ────────────────────────────────────────");

            if ($data === []) {
                $this->comment('    (no data)');
                $this->newLine();
                continue;
            }

            $this->renderCollectorData($data, 2);
            $this->newLine();
        }

        $this->line(str_repeat('═', 70));
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderCollectorData(array $data, int $indent): void
    {
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            $label = str_pad($key, 20);

            if (is_array($value)) {
                if ($this->isSequential($value) && count($value) <= 10) {
                    // Short list — inline
                    $items = array_map(
                        static fn(mixed $v): string => is_string($v) ? $v : json_encode($v),
                        $value,
                    );
                    $this->line("{$prefix}{$label} [" . implode(', ', $items) . ']');
                } elseif (count($value) > 0) {
                    // Nested — recurse
                    $this->line("{$prefix}{$label}");
                    $this->renderCollectorData($value, $indent + 1);
                }
                continue;
            }

            if (is_bool($value)) {
                $display = $value ? "\033[32mtrue\033[0m" : "\033[31mfalse\033[0m";
                $this->line("{$prefix}{$label} {$display}");
                continue;
            }

            if ($value === null) {
                $this->line("{$prefix}{$label} \033[90mnull\033[0m");
                continue;
            }

            if (is_float($value)) {
                $this->line("{$prefix}{$label} " . round($value, 3));
                continue;
            }

            $this->line("{$prefix}{$label} {$value}");
        }
    }

    private function formatCollectorName(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }

    /**
     * @param array<mixed> $arr
     */
    private function isSequential(array $arr): bool
    {
        return array_is_list($arr);
    }
}
