<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Collector;

use MonkeysLegion\DevTools\Collector\RequestCollector;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(RequestCollector::class)]
final class RequestCollectorTest extends TestCase
{
    private RequestCollector $collector;
    private ProfileContext $context;

    protected function setUp(): void
    {
        $this->collector = new RequestCollector();
        $this->context = ProfileContext::create('test', true);
    }

    #[Test]
    public function name_returns_request(): void
    {
        $this->assertSame('request', $this->collector->name());
    }

    #[Test]
    public function label_returns_request(): void
    {
        $this->assertSame('Request', $this->collector->label());
    }

    #[Test]
    public function icon_returns_globe(): void
    {
        $this->assertNotEmpty($this->collector->icon());
    }

    #[Test]
    public function priority_is_1000(): void
    {
        $this->assertSame(1000, $this->collector->priority());
    }

    #[Test]
    public function is_enabled_by_default(): void
    {
        $this->assertTrue($this->collector->isEnabled());
    }

    #[Test]
    public function can_be_disabled(): void
    {
        $collector = new RequestCollector(enabled: false);
        $this->assertFalse($collector->isEnabled());
    }

    #[Test]
    public function captured_method_defaults_empty(): void
    {
        $this->assertSame('', $this->collector->capturedMethod);
    }

    #[Test]
    public function captured_uri_defaults_empty(): void
    {
        $this->assertSame('', $this->collector->capturedUri);
    }

    #[Test]
    public function captured_status_defaults_zero(): void
    {
        $this->assertSame(0, $this->collector->capturedStatusCode);
    }

    #[Test]
    public function set_request_populates_hooks(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('http://localhost/api/users');
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getScheme')->willReturn('http');
        $uri->method('getPath')->willReturn('/api/users');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getUri')->willReturn($uri);
        $request->method('getProtocolVersion')->willReturn('1.1');
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getHeaders')->willReturn([]);
        $request->method('getServerParams')->willReturn(['REMOTE_ADDR' => '127.0.0.1']);
        $request->method('getQueryParams')->willReturn(['page' => '1']);

        $this->collector->setRequest($request);

        $this->assertSame('POST', $this->collector->capturedMethod);
        $this->assertSame('http://localhost/api/users', $this->collector->capturedUri);
    }

    #[Test]
    public function set_response_populates_status_hook(): void
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getSize')->willReturn(512);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $response->method('getBody')->willReturn($body);
        $response->method('getHeaderLine')->willReturn('application/json');
        $response->method('getHeaders')->willReturn([]);

        $this->collector->setResponse($response);

        $this->assertSame(201, $this->collector->capturedStatusCode);
    }

    #[Test]
    public function collect_returns_structured_data(): void
    {
        $this->collector->start($this->context);
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);

        $this->assertArrayHasKey('request_id', $data);
        $this->assertArrayHasKey('method', $data);
        $this->assertArrayHasKey('uri', $data);
        $this->assertArrayHasKey('duration_ms', $data);
        $this->assertArrayHasKey('memory_peak', $data);
        $this->assertArrayHasKey('fingerprint', $data);
    }

    #[Test]
    public function collect_with_request_includes_headers(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('http://localhost/');
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getScheme')->willReturn('http');
        $uri->method('getPath')->willReturn('/');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);
        $request->method('getProtocolVersion')->willReturn('1.1');
        $request->method('getHeaderLine')->willReturn('text/html');
        $request->method('getHeaders')->willReturn(['host' => ['localhost']]);
        $request->method('getServerParams')->willReturn([]);
        $request->method('getQueryParams')->willReturn([]);

        $this->collector->setRequest($request);
        $this->collector->start($this->context);
        $this->collector->stop($this->context);

        $data = $this->collector->collect($this->context);

        $this->assertArrayHasKey('request_headers', $data);
        $this->assertArrayHasKey('query_params', $data);
        $this->assertSame('http', $data['scheme']);
    }

    #[Test]
    public function collect_without_headers_when_disabled(): void
    {
        $collector = new RequestCollector(captureHeaders: false, captureQueryParams: false);
        $collector->start($this->context);
        $collector->stop($this->context);

        $data = $collector->collect($this->context);

        $this->assertArrayNotHasKey('request_headers', $data);
        $this->assertArrayNotHasKey('query_params', $data);
    }
}
