<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit;

use MonkeysLegion\DevTools\DevToolsServiceProvider;
use MonkeysLegion\DevTools\Middleware\DevToolsMiddleware;
use MonkeysLegion\DevTools\Profiler\Profiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DevToolsServiceProvider::class)]
final class DevToolsServiceProviderTest extends TestCase
{
    #[Test]
    public function boots_with_defaults(): void
    {
        $provider = new DevToolsServiceProvider();
        $profiler = $provider->boot();

        $this->assertInstanceOf(Profiler::class, $profiler);
        $this->assertTrue($provider->booted);
        $this->assertNotNull($provider->profiler);
    }

    #[Test]
    public function boots_with_config(): void
    {
        $provider = new DevToolsServiceProvider();
        $profiler = $provider->boot([
            'enabled' => true,
            'environment' => 'staging',
            'sample_rate' => 0.5,
            'storage' => ['driver' => 'file', 'path' => sys_get_temp_dir() . '/ml-test-sp'],
            'redaction' => ['enabled' => true, 'keys' => ['password']],
            'collectors' => ['request' => true, 'route' => true, 'middleware' => true, 'exception' => true],
            'thresholds' => ['slow_request_ms' => 100],
        ]);

        $this->assertTrue($provider->booted);
        $this->assertTrue($profiler->isEnabled());

        // Cleanup
        $this->deleteDir(sys_get_temp_dir() . '/ml-test-sp');
    }

    #[Test]
    public function boots_with_null_storage(): void
    {
        $provider = new DevToolsServiceProvider();
        $profiler = $provider->boot([
            'storage' => ['driver' => 'null'],
        ]);

        $this->assertInstanceOf(Profiler::class, $profiler);
    }

    #[Test]
    public function registers_all_collectors(): void
    {
        $provider = new DevToolsServiceProvider();
        $provider->boot([
            'storage' => ['driver' => 'null'],
            'collectors' => [
                'request' => true,
                'route' => true,
                'middleware' => true,
                'query' => true,
                'cache' => true,
                'event' => true,
                'exception' => true,
            ],
        ]);

        $collectors = $provider->getCollectors();

        $this->assertArrayHasKey('request', $collectors);
        $this->assertArrayHasKey('route', $collectors);
        $this->assertArrayHasKey('middleware', $collectors);
        $this->assertArrayHasKey('query', $collectors);
        $this->assertArrayHasKey('cache', $collectors);
        $this->assertArrayHasKey('event', $collectors);
        $this->assertArrayHasKey('exception', $collectors);
    }

    #[Test]
    public function disables_specific_collectors(): void
    {
        $provider = new DevToolsServiceProvider();
        $provider->boot([
            'storage' => ['driver' => 'null'],
            'collectors' => [
                'request' => true,
                'route' => false,
                'middleware' => false,
                'exception' => true,
            ],
        ]);

        $collectors = $provider->getCollectors();

        $this->assertArrayHasKey('request', $collectors);
        $this->assertArrayNotHasKey('route', $collectors);
        $this->assertArrayNotHasKey('middleware', $collectors);
        $this->assertArrayHasKey('exception', $collectors);
    }

    #[Test]
    public function create_middleware_works(): void
    {
        $provider = new DevToolsServiceProvider();
        $provider->boot(['storage' => ['driver' => 'null']]);

        $mw = $provider->createMiddleware();
        $this->assertInstanceOf(DevToolsMiddleware::class, $mw);
    }

    #[Test]
    public function create_middleware_throws_before_boot(): void
    {
        $provider = new DevToolsServiceProvider();

        $this->expectException(\RuntimeException::class);
        $provider->createMiddleware();
    }

    #[Test]
    public function get_collectors_empty_before_boot(): void
    {
        $provider = new DevToolsServiceProvider();
        $this->assertSame([], $provider->getCollectors());
    }

    #[Test]
    public function toolbar_null_by_default(): void
    {
        $provider = new DevToolsServiceProvider();
        $provider->boot(['storage' => ['driver' => 'null']]);

        $this->assertNull($provider->toolbar);
        $this->assertNull($provider->injector);
    }

    #[Test]
    public function toolbar_created_when_enabled(): void
    {
        $provider = new DevToolsServiceProvider();
        $provider->boot([
            'storage' => ['driver' => 'null'],
            'toolbar' => ['enabled' => true],
        ]);

        $this->assertNotNull($provider->toolbar);
        $this->assertNotNull($provider->injector);
    }

    #[Test]
    public function disabled_redaction(): void
    {
        $provider = new DevToolsServiceProvider();
        $provider->boot([
            'storage' => ['driver' => 'null'],
            'redaction' => ['enabled' => false],
        ]);

        $this->assertTrue($provider->booted);
    }

    #[Test]
    public function production_sample_rate(): void
    {
        $provider = new DevToolsServiceProvider();
        $provider->boot([
            'storage' => ['driver' => 'null'],
            'production' => ['sample_rate' => 0.01],
        ]);

        $this->assertTrue($provider->booted);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = "$dir/$f";
            is_dir($p) ? $this->deleteDir($p) : unlink($p);
        }
        rmdir($dir);
    }
}
