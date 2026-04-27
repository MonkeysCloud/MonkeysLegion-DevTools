<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Collector;

use MonkeysLegion\DevTools\Collector\EventCollector;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventCollector::class)]
final class EventCollectorTest extends TestCase
{
    private EventCollector $collector;
    private ProfileContext $context;

    protected function setUp(): void
    {
        $this->collector = new EventCollector(stormThreshold: 3);
        $this->context = ProfileContext::create('test', true);
        $this->collector->start($this->context);
    }

    #[Test]
    public function records_event_dispatches(): void
    {
        $this->collector->recordDispatch('App\\Event\\UserCreated', [
            ['name' => 'App\\Listener\\SendWelcome', 'duration_ms' => 5.0],
        ]);

        $this->assertSame(1, $this->collector->eventCount);
        $this->assertSame(1, $this->collector->listenerCount);
    }

    #[Test]
    public function detects_event_storms(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->collector->recordDispatch('App\\Event\\CacheCleared');
        }

        $this->assertTrue($this->collector->hasStorm);
    }

    #[Test]
    public function no_storm_below_threshold(): void
    {
        $this->collector->recordDispatch('App\\Event\\UserCreated');
        $this->collector->recordDispatch('App\\Event\\UserUpdated');

        $this->assertFalse($this->collector->hasStorm);
    }

    #[Test]
    public function tracks_failed_listeners(): void
    {
        $this->collector->recordDispatch('App\\Event\\OrderPlaced', [
            ['name' => 'Listener1', 'duration_ms' => 1.0, 'failed' => false],
            ['name' => 'Listener2', 'duration_ms' => 2.0, 'failed' => true],
        ]);

        $this->assertSame(1, $this->collector->failedListenerCount);
    }

    #[Test]
    public function tracks_total_listener_time(): void
    {
        $this->collector->recordDispatch('E1', [
            ['name' => 'L1', 'duration_ms' => 10.0],
            ['name' => 'L2', 'duration_ms' => 20.0],
        ]);

        $this->assertEqualsWithDelta(30.0, $this->collector->totalListenerMs, 0.001);
    }

    #[Test]
    public function collect_returns_timeline(): void
    {
        $this->collector->recordDispatch('App\\Event\\First');
        $this->collector->recordDispatch('App\\Event\\Second');
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);

        $this->assertArrayHasKey('timeline', $data);
        $this->assertCount(2, $data['timeline']);
        $this->assertSame('First', $data['timeline'][0]['event']);
        $this->assertSame('Second', $data['timeline'][1]['event']);
    }
}
