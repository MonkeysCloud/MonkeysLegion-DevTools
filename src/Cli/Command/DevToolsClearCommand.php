<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Cli\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Clears all stored profiles with confirmation prompt.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[CommandAttr('devtools:clear', 'Clear all stored profiles')]
final class DevToolsClearCommand extends Command
{
    public function __construct(
        private readonly ProfileStorageInterface $storage,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $count = $this->storage->count();

        if ($count === 0) {
            $this->newLine();
            $this->comment('  No profiles to clear.');
            $this->newLine();

            return self::SUCCESS;
        }

        $force = $this->hasOption('force');

        if (!$force) {
            $confirmed = $this->confirm("  Clear {$count} stored profiles?");

            if (!$confirmed) {
                $this->comment('  Cancelled.');

                return self::SUCCESS;
            }
        }

        $cleared = $this->storage->clear();

        $this->newLine();
        $this->info("  ✓ Cleared {$cleared} profiles.");
        $this->newLine();

        return self::SUCCESS;
    }
}
