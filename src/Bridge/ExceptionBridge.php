<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Bridge;

use MonkeysLegion\DevTools\Collector\ExceptionCollector;

/**
 * Registers a global exception handler that feeds the DevTools ExceptionCollector.
 *
 * This bridge installs itself via set_exception_handler() and chains to any
 * previously registered handler. It captures all uncaught exceptions for the
 * DevTools toolbar "Exceptions" panel.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ExceptionBridge
{
    /** @var ?callable Previous exception handler to chain to */
    private mixed $previousHandler = null;

    public function __construct(
        private readonly ExceptionCollector $exceptionCollector,
    ) {}

    /**
     * Install this bridge as the global exception handler.
     *
     * Chains to any previously registered handler so existing
     * error handling is not disrupted.
     */
    public function install(): void
    {
        $this->previousHandler = set_exception_handler($this->handle(...));
    }

    /**
     * Handle an uncaught exception — record it and chain to previous handler.
     */
    public function handle(\Throwable $exception): void
    {
        $this->exceptionCollector->addException($exception);

        // Chain to previous handler if one existed
        if ($this->previousHandler !== null) {
            ($this->previousHandler)($exception);
        }
    }

    /**
     * Manually record an exception (for caught exceptions that
     * still need to appear in DevTools).
     */
    public function record(\Throwable $exception): void
    {
        $this->exceptionCollector->addException($exception);
    }
}
