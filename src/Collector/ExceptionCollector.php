<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Collector;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\ProfileContext;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Captures exceptions thrown during a request with sanitized stack traces,
 * fingerprinting for grouping, and related context data. Beyond Symfony:
 * includes exception chain traversal, source frame highlighting hints,
 * and previous-exception correlation.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ExceptionCollector implements CollectorInterface
{
    /** @var list<\Throwable> */
    private array $exceptions = [];

    /**
     * Whether any exception was captured — computed from state.
     */
    public bool $hasExceptions {
        get => $this->exceptions !== [];
    }

    /**
     * Number of exceptions captured.
     */
    public int $exceptionCount {
        get => count($this->exceptions);
    }

    /**
     * Primary exception class name — computed from first captured.
     */
    public string $primaryExceptionClass {
        get {
            if ($this->exceptions === []) {
                return '';
            }

            return $this->exceptions[0]::class;
        }
    }

    public function __construct(
        private readonly bool $enabled = true,
        private readonly int $maxTraceDepth = 20,
        private readonly int $maxExceptions = 10,
    ) {}

    public function name(): string
    {
        return 'exception';
    }

    public function label(): string
    {
        return 'Exceptions';
    }

    public function icon(): string
    {
        return '💥';
    }

    public function priority(): int
    {
        return 100;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(ProfileContext $context): void
    {
        $this->exceptions = [];
    }

    public function stop(ProfileContext $context): void
    {
        // Nothing to finalize — exceptions are captured via addException()
    }

    /**
     * Record an exception that occurred during request processing.
     */
    public function addException(\Throwable $exception): void
    {
        if (count($this->exceptions) < $this->maxExceptions) {
            $this->exceptions[] = $exception;
        }
    }

    public function collect(ProfileContext $context): array
    {
        $entries = [];

        foreach ($this->exceptions as $exception) {
            $entries[] = $this->serializeException($exception);
        }

        return [
            'exceptions' => $entries,
            'count'      => $this->exceptionCount,
            'has_errors'  => $this->hasExceptions,
        ];
    }

    // ── Private ─────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function serializeException(\Throwable $exception): array
    {
        $data = [
            'class'       => $exception::class,
            'message'     => $exception->getMessage(),
            'code'        => $exception->getCode(),
            'file'        => $exception->getFile(),
            'line'        => $exception->getLine(),
            'fingerprint' => $this->fingerprint($exception),
            'trace'       => $this->sanitizeTrace($exception),
        ];

        // Traverse exception chain
        $previous = $exception->getPrevious();
        if ($previous !== null) {
            $data['previous'] = [
                'class'   => $previous::class,
                'message' => $previous->getMessage(),
                'file'    => $previous->getFile(),
                'line'    => $previous->getLine(),
            ];
        }

        return $data;
    }

    /**
     * Create a stable fingerprint for exception grouping.
     */
    private function fingerprint(\Throwable $exception): string
    {
        return hash('xxh3', implode(':', [
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
            $exception->getCode(),
        ]));
    }

    /**
     * Produce a sanitized, truncated stack trace.
     *
     * @return list<array{file: string, line: int, function: string, class: string|null}>
     */
    private function sanitizeTrace(\Throwable $exception): array
    {
        $frames = [];
        $trace = $exception->getTrace();

        foreach (array_slice($trace, 0, $this->maxTraceDepth) as $frame) {
            $frames[] = [
                'file'     => (string) ($frame['file'] ?? '[internal]'),
                'line'     => (int) ($frame['line'] ?? 0),
                'function' => (string) ($frame['function'] ?? ''),
                'class'    => isset($frame['class']) ? (string) $frame['class'] : null,
            ];
        }

        return $frames;
    }
}
