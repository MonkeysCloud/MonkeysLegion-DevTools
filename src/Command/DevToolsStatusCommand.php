<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;
use MonkeysLegion\DevTools\Profiler\Profiler;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Shows DevTools configuration status, enabled collectors,
 * storage driver, and sampling information.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[CommandAttr('devtools:status', 'Show DevTools configuration and status')]
final class DevToolsStatusCommand extends Command
{
    public function __construct(
        private readonly Profiler $profiler,
        private readonly ProfileStorageInterface $storage,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $this->newLine();
        $this->info('  🔧 MonkeysLegion DevTools — Status');
        $this->line(str_repeat('─', 60));
        $this->newLine();

        // Profiler state
        $enabled = $this->profiler->isEnabled() ? "\033[32m● Enabled\033[0m" : "\033[31m○ Disabled\033[0m";
        $this->line("  Profiler:    {$enabled}");
        $this->line("  Active:      " . ($this->profiler->isActive ? 'Yes' : 'No'));
        $this->line("  Profiles:    {$this->storage->count()} stored");
        $this->newLine();

        // Registered collectors
        $this->info('  Collectors:');
        $collectors = $this->profiler->collectorNames;

        if ($collectors === []) {
            $this->comment('    No collectors registered.');
        } else {
            foreach ($collectors as $name) {
                $collector = $this->profiler->getCollector($name);
                if ($collector === null) {
                    continue;
                }

                $status = $collector->isEnabled()
                    ? "\033[32m✓\033[0m"
                    : "\033[31m✗\033[0m";

                $this->line(sprintf(
                    '    %s %s  %s  (priority: %d)',
                    $status,
                    $collector->icon(),
                    str_pad($collector->label(), 15),
                    $collector->priority(),
                ));
            }
        }

        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->newLine();

        return self::SUCCESS;
    }
}
