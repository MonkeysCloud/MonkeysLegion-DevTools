<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Middleware;

use MonkeysLegion\DevTools\Collector\ExceptionCollector;
use MonkeysLegion\DevTools\Collector\RequestCollector;
use MonkeysLegion\DevTools\Middleware\DevToolsMiddleware;
use MonkeysLegion\DevTools\Profiler\Profiler;
use MonkeysLegion\DevTools\Redaction\KeyBasedRedactor;
use MonkeysLegion\DevTools\Sampler\RateSampler;
use MonkeysLegion\DevTools\Storage\MemoryProfileStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(DevToolsMiddleware::class)]
final class DevToolsMiddlewareTest extends TestCase
{
    private MemoryProfileStorage $storage;
    private Profiler $profiler;
    private DevToolsMiddleware $middleware;

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
        $this->profiler->addCollector(new RequestCollector());
        $this->profiler->addCollector(new ExceptionCollector());
        $this->middleware = new DevToolsMiddleware($this->profiler);
    }

    #[Test]
    public function passes_through_when_disabled(): void
    {
        $this->profiler->setEnabled(false);

        $request = $this->createRequest();

        // When disabled, the response passes through unmodified — no profile headers
        $body = $this->createMock(StreamInterface::class);
        $body->method('getSize')->willReturn(100);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $response->method('hasHeader')->willReturn(false);

        $handler = $this->createHandler($response);

        $result = $this->middleware->process($request, $handler);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(0, $this->storage->count());
        $this->assertFalse($result->hasHeader('X-MonkeysLegion-Profile'));
    }

    #[Test]
    public function profiles_successful_request(): void
    {
        $request = $this->createRequest();
        $response = $this->createResponse(200);
        $handler = $this->createHandler($response);

        $result = $this->middleware->process($request, $handler);

        $this->assertSame(1, $this->storage->count());
        $this->assertTrue($result->hasHeader('X-MonkeysLegion-Profile'));
        $this->assertTrue($result->hasHeader('X-MonkeysLegion-Duration'));
    }

    #[Test]
    public function captures_exception_and_rethrows(): void
    {
        $request = $this->createRequest();
        $handler = $this->createThrowingHandler(new \RuntimeException('boom'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        try {
            $this->middleware->process($request, $handler);
        } finally {
            // Profile is saved even on exception
            $this->assertSame(1, $this->storage->count());
        }
    }

    #[Test]
    public function passes_through_when_not_sampled(): void
    {
        $profiler = new Profiler(
            storage: $this->storage,
            redactor: new KeyBasedRedactor(),
            sampler: new RateSampler(defaultRate: 0.0),
            environment: 'test',
        );
        $mw = new DevToolsMiddleware($profiler);

        $request = $this->createRequest();
        $response = $this->createResponse(200);
        $handler = $this->createHandler($response);

        $result = $mw->process($request, $handler);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(0, $this->storage->count());
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function createRequest(string $method = 'GET', string $uri = '/test'): ServerRequestInterface
    {
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('__toString')->willReturn($uri);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uriMock);
        $request->method('withAttribute')->willReturnSelf();

        return $request;
    }

    private function createResponse(int $status): ResponseInterface
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getSize')->willReturn(100);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($body);
        $response->method('withHeader')->willReturnSelf();
        $response->method('hasHeader')->willReturnCallback(
            fn(string $name): bool => in_array($name, ['X-MonkeysLegion-Profile', 'X-MonkeysLegion-Duration']),
        );

        return $response;
    }

    private function createHandler(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    private function createThrowingHandler(\Throwable $exception): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willThrowException($exception);

        return $handler;
    }
}
