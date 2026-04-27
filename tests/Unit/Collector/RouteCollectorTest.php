<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Collector;

use MonkeysLegion\DevTools\Collector\RouteCollector;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteCollector::class)]
final class RouteCollectorTest extends TestCase
{
    private RouteCollector $collector;
    private ProfileContext $context;

    protected function setUp(): void
    {
        $this->collector = new RouteCollector();
        $this->context = ProfileContext::create('test', true);
    }

    #[Test]
    public function name_returns_route(): void
    {
        $this->assertSame('route', $this->collector->name());
    }

    #[Test]
    public function label_icon_priority(): void
    {
        $this->assertSame('Route', $this->collector->label());
        $this->assertNotEmpty($this->collector->icon());
        $this->assertSame(900, $this->collector->priority());
    }

    #[Test]
    public function is_enabled_by_default(): void
    {
        $this->assertTrue($this->collector->isEnabled());
    }

    #[Test]
    public function can_be_disabled(): void
    {
        $collector = new RouteCollector(enabled: false);
        $this->assertFalse($collector->isEnabled());
    }

    #[Test]
    public function handler_hook_empty_by_default(): void
    {
        $this->assertSame('', $this->collector->handler);
        $this->assertSame('', $this->collector->shortHandler);
    }

    #[Test]
    public function set_route_data_populates_hooks(): void
    {
        $this->collector->setRouteData(
            name: 'users.show',
            path: '/api/users/42',
            pattern: '/api/users/{id}',
            controller: 'App\\Controller\\UserController',
            action: 'show',
            params: ['id' => '42'],
            middleware: ['auth', 'throttle'],
            metadata: ['summary' => 'Get user'],
        );

        $this->assertSame('App\\Controller\\UserController::show', $this->collector->handler);
        $this->assertSame('UserController::show', $this->collector->shortHandler);
    }

    #[Test]
    public function handler_without_action(): void
    {
        $this->collector->setRouteData(
            name: 'test',
            path: '/',
            pattern: '/',
            controller: 'App\\Controller\\HomeController',
            action: '',
        );

        $this->assertSame('App\\Controller\\HomeController', $this->collector->handler);
    }

    #[Test]
    public function collect_returns_structured_data(): void
    {
        $this->collector->start($this->context);
        $this->collector->setRouteData(
            name: 'orders.index',
            path: '/api/orders',
            pattern: '/api/orders',
            controller: 'App\\Controller\\OrderController',
            action: 'index',
            params: [],
            middleware: ['auth'],
            metadata: ['summary' => 'List orders'],
        );
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);

        $this->assertSame('orders.index', $data['route_name']);
        $this->assertSame('/api/orders', $data['route_path']);
        $this->assertSame('OrderController::index', $data['short_handler']);
        $this->assertSame(['auth'], $data['middleware']);
        $this->assertTrue($data['has_openapi']);
    }

    #[Test]
    public function start_resets_state(): void
    {
        $this->collector->setRouteData(
            name: 'old',
            path: '/old',
            pattern: '/old',
            controller: 'OldCtrl',
            action: 'old',
        );

        $this->collector->start($this->context);
        $data = $this->collector->collect($this->context);

        $this->assertSame('', $data['route_name']);
        $this->assertSame('', $data['controller']);
    }
}
