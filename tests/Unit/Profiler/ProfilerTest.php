<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Profiler;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\Profiler;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use MonkeysLegion\DevTools\Redaction\KeyBasedRedactor;
use MonkeysLegion\DevTools\Sampler\RateSampler;
use MonkeysLegion\DevTools\Storage\MemoryProfileStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Profiler::class)]
final class ProfilerTest extends TestCase
{
    private MemoryProfileStorage $storage;
    private Profiler $profiler;

    protected function setUp(): void
    {
        $this->storage = new MemoryProfileStorage();
        $this->profiler = new Profiler(
            storage: $this->storage,
            redactor: new KeyBasedRedactor(),
            sampler: new RateSampler(defaultRate: 1.0),
            environment: 'test',
            enabled: true,
        );
    }

    #[Test]
    public function start_returns_context_when_enabled(): void
    {
        $context = $this->profiler->start();

        $this->assertNotNull($context);
        $this->assertInstanceOf(ProfileContext::class, $context);
        $this->assertSame('test', $context->environment);
        $this->assertTrue($context->sampled);
    }

    #[Test]
    public function start_returns_null_when_disabled(): void
    {
        $this->profiler->setEnabled(false);

        $this->assertNull($this->profiler->start());
    }

    #[Test]
    public function start_returns_null_when_not_sampled(): void
    {
        $profiler = new Profiler(
            storage: $this->storage,
            redactor: new KeyBasedRedactor(),
            sampler: new RateSampler(defaultRate: 0.0),
            environment: 'test',
        );

        $this->assertNull($profiler->start());
    }

    #[Test]
    public function stop_persists_profile(): void
    {
        $this->profiler->start();

        $profile = $this->profiler->stop(
            method: 'GET',
            uri: '/api/test',
            statusCode: 200,
            responseSize: 512,
        );

        $this->assertNotNull($profile);
        $this->assertSame('GET', $profile->method);
        $this->assertSame('/api/test', $profile->uri);
        $this->assertSame(200, $profile->statusCode);
        $this->assertSame(1, $this->storage->count());
    }

    #[Test]
    public function stop_without_start_returns_null(): void
    {
        $this->assertNull($this->profiler->stop());
    }

    #[Test]
    public function is_active_property_hook_reflects_state(): void
    {
        $this->assertFalse($this->profiler->isActive);

        $this->profiler->start();
        $this->assertTrue($this->profiler->isActive);

        $this->profiler->stop();
        $this->assertFalse($this->profiler->isActive);
    }

    #[Test]
    public function collectors_are_called_in_priority_order(): void
    {
        $order = [];

        $low = $this->createCollector('low', 10, $order);
        $high = $this->createCollector('high', 100, $order);
        $mid = $this->createCollector('mid', 50, $order);

        $this->profiler->addCollector($low);
        $this->profiler->addCollector($high);
        $this->profiler->addCollector($mid);

        $this->profiler->start();
        $this->profiler->stop();

        // Start: high (100) → mid (50) → low (10)
        $this->assertSame(['high:start', 'mid:start', 'low:start'], array_slice($order, 0, 3));

        // Stop: low (10) → mid (50) → high (100) [reverse]
        $this->assertSame(['low:stop', 'mid:stop', 'high:stop'], array_slice($order, 3, 3));
    }

    #[Test]
    public function collector_count_property_hook_works(): void
    {
        $this->assertSame(0, $this->profiler->collectorCount);

        $this->profiler->addCollector($this->createSimpleCollector('a'));
        $this->assertSame(1, $this->profiler->collectorCount);

        $this->profiler->addCollector($this->createSimpleCollector('b'));
        $this->assertSame(2, $this->profiler->collectorCount);

        $this->profiler->removeCollector('a');
        $this->assertSame(1, $this->profiler->collectorCount);
    }

    #[Test]
    public function collector_names_property_hook_returns_names(): void
    {
        $this->profiler->addCollector($this->createSimpleCollector('request'));
        $this->profiler->addCollector($this->createSimpleCollector('route'));

        $this->assertEqualsCanonicalizing(['request', 'route'], $this->profiler->collectorNames);
    }

    #[Test]
    public function redaction_is_applied_to_collector_data(): void
    {
        $collector = $this->createMock(CollectorInterface::class);
        $collector->method('name')->willReturn('test');
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('priority')->willReturn(0);
        $collector->method('collect')->willReturn([
            'safe_key' => 'visible',
            'password' => 'secret123',
            'token'    => 'abc-xyz',
        ]);

        $this->profiler->addCollector($collector);
        $this->profiler->start();
        $profile = $this->profiler->stop();

        $this->assertNotNull($profile);
        $data = $profile->collector('test');
        $this->assertSame('visible', $data['safe_key']);
        $this->assertNotSame('secret123', $data['password']);
        $this->assertNotSame('abc-xyz', $data['token']);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * @param list<string> $order
     */
    private function createCollector(string $name, int $priority, array &$order): CollectorInterface
    {
        $collector = $this->createMock(CollectorInterface::class);
        $collector->method('name')->willReturn($name);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('priority')->willReturn($priority);
        $collector->method('collect')->willReturn([]);

        $collector->method('start')->willReturnCallback(
            function () use ($name, &$order): void { $order[] = "{$name}:start"; },
        );

        $collector->method('stop')->willReturnCallback(
            function () use ($name, &$order): void { $order[] = "{$name}:stop"; },
        );

        return $collector;
    }

    private function createSimpleCollector(string $name): CollectorInterface
    {
        $collector = $this->createMock(CollectorInterface::class);
        $collector->method('name')->willReturn($name);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('priority')->willReturn(0);
        $collector->method('collect')->willReturn([]);

        return $collector;
    }
}
