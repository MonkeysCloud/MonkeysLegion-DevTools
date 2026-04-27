<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Collector;

use MonkeysLegion\DevTools\Collector\MiddlewareCollector;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MiddlewareCollector::class)]
final class MiddlewareCollectorTest extends TestCase
{
    private MiddlewareCollector $collector;
    private ProfileContext $context;

    protected function setUp(): void
    {
        $this->collector = new MiddlewareCollector();
        $this->context = ProfileContext::create('test', true);
        $this->collector->start($this->context);
    }

    #[Test]
    public function name_label_icon_priority(): void
    {
        $this->assertSame('middleware', $this->collector->name());
        $this->assertSame('Middleware', $this->collector->label());
        $this->assertNotEmpty($this->collector->icon());
        $this->assertSame(950, $this->collector->priority());
    }

    #[Test]
    public function is_enabled_by_default(): void
    {
        $this->assertTrue($this->collector->isEnabled());
    }

    #[Test]
    public function enter_leave_tracks_middleware(): void
    {
        $this->collector->enter('AuthMiddleware');
        $this->collector->leave('AuthMiddleware');
        $this->collector->enter('CorsMiddleware');
        $this->collector->leave('CorsMiddleware');

        $this->assertSame(2, $this->collector->middlewareCount);
    }

    #[Test]
    public function total_duration_ms_computed(): void
    {
        $this->collector->enter('M1');
        usleep(1000); // ~1ms
        $this->collector->leave('M1');

        $this->assertGreaterThan(0.0, $this->collector->totalDurationMs);
    }

    #[Test]
    public function slowest_middleware_detected(): void
    {
        $this->collector->enter('FastMW');
        $this->collector->leave('FastMW');

        $this->collector->enter('SlowMW');
        usleep(2000); // ~2ms
        $this->collector->leave('SlowMW');

        $this->assertSame('SlowMW', $this->collector->slowestMiddleware);
    }

    #[Test]
    public function stop_finalizes_unclosed_entries(): void
    {
        $this->collector->enter('Unclosed');
        // Don't call leave()
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);
        $mw = $data['middleware'] ?? [];

        $this->assertCount(1, $mw);
        $this->assertTrue($mw[0]['completed']);
    }

    #[Test]
    public function collect_returns_structured_data(): void
    {
        $this->collector->enter('Auth');
        $this->collector->leave('Auth');
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);

        $this->assertArrayHasKey('middleware', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('total_duration_ms', $data);
        $this->assertArrayHasKey('slowest', $data);
        $this->assertSame(1, $data['count']);
        $this->assertTrue($data['middleware'][0]['completed']);
        $this->assertNotNull($data['middleware'][0]['duration_ms']);
        $this->assertNotNull($data['middleware'][0]['memory_delta']);
    }
}
