<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Command;

use MonkeysLegion\Cli\Console\Attributes\Command as CommandAttr;
use MonkeysLegion\Cli\Console\Command;
use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;
use MonkeysLegion\DevTools\Contract\RedactorInterface;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Exports a profile as sanitized JSON — safe for sharing
 * with team members or uploading to MonkeysCloud.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
#[CommandAttr('devtools:export', 'Export a sanitized profile as JSON')]
final class DevToolsExportCommand extends Command
{
    public function __construct(
        private readonly ProfileStorageInterface $storage,
        private readonly RedactorInterface $redactor,
    ) {
        parent::__construct();
    }

    protected function handle(): int
    {
        $id = $this->argument(0);

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
                $this->error("Profile '{$id}' not found.");

                return self::FAILURE;
            }
        }

        // Double-redact for export safety
        $data = $profile->toArray();
        $data['collectors'] = $this->redactor->redact($data['collectors'] ?? []);

        // Output destination
        $outputPath = $this->option('output');

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (is_string($outputPath)) {
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($outputPath, $json);
            $this->newLine();
            $this->info("  ✓ Profile exported to: {$outputPath}");
        } else {
            // Write to stdout
            fwrite(STDOUT, $json . PHP_EOL);
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
