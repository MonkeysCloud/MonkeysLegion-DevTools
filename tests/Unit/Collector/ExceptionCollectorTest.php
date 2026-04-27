<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Collector;

use MonkeysLegion\DevTools\Collector\ExceptionCollector;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExceptionCollector::class)]
final class ExceptionCollectorTest extends TestCase
{
    private ExceptionCollector $collector;
    private ProfileContext $context;

    protected function setUp(): void
    {
        $this->collector = new ExceptionCollector();
        $this->context = ProfileContext::create('test', true);
        $this->collector->start($this->context);
    }

    #[Test]
    public function name_label_icon_priority(): void
    {
        $this->assertSame('exception', $this->collector->name());
        $this->assertSame('Exceptions', $this->collector->label());
        $this->assertNotEmpty($this->collector->icon());
        $this->assertSame(100, $this->collector->priority());
    }

    #[Test]
    public function has_no_exceptions_initially(): void
    {
        $this->assertFalse($this->collector->hasExceptions);
        $this->assertSame(0, $this->collector->exceptionCount);
        $this->assertSame('', $this->collector->primaryExceptionClass);
    }

    #[Test]
    public function add_exception_updates_hooks(): void
    {
        $this->collector->addException(new \RuntimeException('fail'));

        $this->assertTrue($this->collector->hasExceptions);
        $this->assertSame(1, $this->collector->exceptionCount);
        $this->assertSame(\RuntimeException::class, $this->collector->primaryExceptionClass);
    }

    #[Test]
    public function multiple_exceptions_tracked(): void
    {
        $this->collector->addException(new \RuntimeException('first'));
        $this->collector->addException(new \InvalidArgumentException('second'));

        $this->assertSame(2, $this->collector->exceptionCount);
        // Primary is the first one
        $this->assertSame(\RuntimeException::class, $this->collector->primaryExceptionClass);
    }

    #[Test]
    public function respects_max_exceptions(): void
    {
        $collector = new ExceptionCollector(maxExceptions: 2);
        $collector->start($this->context);

        $collector->addException(new \RuntimeException('1'));
        $collector->addException(new \RuntimeException('2'));
        $collector->addException(new \RuntimeException('3'));

        $this->assertSame(2, $collector->exceptionCount);
    }

    #[Test]
    public function collect_includes_trace_and_fingerprint(): void
    {
        $this->collector->addException(new \RuntimeException('test error'));
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);

        $this->assertSame(1, $data['count']);
        $this->assertTrue($data['has_errors']);
        $this->assertArrayHasKey('exceptions', $data);

        $ex = $data['exceptions'][0];
        $this->assertSame(\RuntimeException::class, $ex['class']);
        $this->assertSame('test error', $ex['message']);
        $this->assertArrayHasKey('fingerprint', $ex);
        $this->assertArrayHasKey('trace', $ex);
        $this->assertIsArray($ex['trace']);
    }

    #[Test]
    public function collect_includes_previous_exception(): void
    {
        $previous = new \LogicException('root cause');
        $exception = new \RuntimeException('wrapper', 0, $previous);

        $this->collector->addException($exception);
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);
        $ex = $data['exceptions'][0];

        $this->assertArrayHasKey('previous', $ex);
        $this->assertSame(\LogicException::class, $ex['previous']['class']);
        $this->assertSame('root cause', $ex['previous']['message']);
    }

    #[Test]
    public function start_resets_state(): void
    {
        $this->collector->addException(new \RuntimeException('old'));
        $this->assertSame(1, $this->collector->exceptionCount);

        $this->collector->start($this->context);
        $this->assertSame(0, $this->collector->exceptionCount);
        $this->assertFalse($this->collector->hasExceptions);
    }
}
