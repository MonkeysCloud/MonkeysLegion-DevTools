<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Toolbar;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use MonkeysLegion\DevTools\Toolbar\Panel\OverviewPanel;
use MonkeysLegion\DevTools\Toolbar\ToolbarInjector;
use MonkeysLegion\DevTools\Toolbar\ToolbarRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(ToolbarInjector::class)]
final class ToolbarInjectorTest extends TestCase
{
    private ToolbarRenderer $renderer;
    private ToolbarInjector $injector;

    protected function setUp(): void
    {
        $this->renderer = new ToolbarRenderer();
        $this->renderer->addPanel(new OverviewPanel());
        $this->injector = new ToolbarInjector($this->renderer);
    }

    #[Test]
    public function skips_non_html_responses(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaderLine')->willReturn('application/json');

        $result = $this->injector->inject($response, $this->createProfile());

        $this->assertSame($response, $result);
        $this->assertSame(0, $this->injector->injectionCount);
    }

    #[Test]
    public function is_injectable_checks_content_type(): void
    {
        $htmlResponse = $this->createMock(ResponseInterface::class);
        $htmlResponse->method('getHeaderLine')->willReturn('text/html; charset=utf-8');

        $jsonResponse = $this->createMock(ResponseInterface::class);
        $jsonResponse->method('getHeaderLine')->willReturn('application/json');

        $this->assertTrue($this->injector->isInjectable($htmlResponse));
        $this->assertFalse($this->injector->isInjectable($jsonResponse));
    }

    #[Test]
    public function skips_large_responses(): void
    {
        $injector = new ToolbarInjector($this->renderer, maxPayloadKb: 1);

        $body = $this->createMock(StreamInterface::class);
        $body->method('getSize')->willReturn(2048); // 2KB > 1KB limit

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaderLine')->willReturn('text/html');
        $response->method('getBody')->willReturn($body);

        $result = $injector->inject($response, $this->createProfile());

        $this->assertSame($response, $result);
    }

    #[Test]
    public function injection_count_starts_at_zero(): void
    {
        $this->assertSame(0, $this->injector->injectionCount);
    }

    private function createProfile(): Profile
    {
        $ctx = ProfileContext::create('test', true);
        return Profile::fromContext($ctx, $ctx->startedAt + 42.0, []);
    }
}
