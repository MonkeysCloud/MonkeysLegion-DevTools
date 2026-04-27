<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Middleware;

use MonkeysLegion\DevTools\Collector\ExceptionCollector;
use MonkeysLegion\DevTools\Collector\RequestCollector;
use MonkeysLegion\DevTools\Profiler\Profiler;
use MonkeysLegion\DevTools\Toolbar\ToolbarInjector;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * PSR-15 middleware that wraps the full request lifecycle with profiling.
 * Automatically starts/stops the profiler, captures request/response data,
 * handles exceptions, attaches profile headers, and injects the debug toolbar.
 *
 * Competitive advantages:
 * - Zero overhead when profiler is disabled (early return)
 * - Automatic exception capture without swallowing
 * - Profile ID in response headers for API debugging
 * - Content-type aware — only injects toolbar into HTML responses
 * - Self-contained toolbar with embedded CSS/JS (no external assets)
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class DevToolsMiddleware implements MiddlewareInterface
{
    private const string PROFILE_HEADER = 'X-MonkeysLegion-Profile';
    private const string PROFILE_DURATION_HEADER = 'X-MonkeysLegion-Duration';
    private const string REQUEST_ID_ATTRIBUTE = 'devtools.profile_id';

    public function __construct(
        private readonly Profiler $profiler,
        private readonly ?ToolbarInjector $injector = null,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Fast path: skip when profiler is disabled
        if (!$this->profiler->isEnabled()) {
            return $handler->handle($request);
        }

        // 1. Start profiling session
        $context = $this->profiler->start();

        if ($context === null) {
            // Not sampled — pass through
            return $handler->handle($request);
        }

        // 2. Inject request data into RequestCollector
        $requestCollector = $this->profiler->getCollector('request');
        if ($requestCollector instanceof RequestCollector) {
            $requestCollector->setRequest($request);
        }

        // 3. Attach profile ID to request attributes for downstream use
        $request = $request->withAttribute(self::REQUEST_ID_ATTRIBUTE, $context->id);

        // 4. Execute the request pipeline
        $response = null;
        $statusCode = 500;

        try {
            $response = $handler->handle($request);
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $e) {
            // Capture exception without swallowing
            $exceptionCollector = $this->profiler->getCollector('exception');
            if ($exceptionCollector instanceof ExceptionCollector) {
                $exceptionCollector->addException($e);
            }

            // Stop profiler before re-throwing
            $this->profiler->stop(
                method: $request->getMethod(),
                uri: (string) $request->getUri(),
                statusCode: 500,
                responseSize: 0,
            );

            throw $e;
        }

        // 5. Inject response data into RequestCollector
        if ($requestCollector instanceof RequestCollector) {
            $requestCollector->setResponse($response);
        }

        // 6. Stop profiling and persist
        $responseSize = $response->getBody()->getSize() ?? 0;

        $profile = $this->profiler->stop(
            method: $request->getMethod(),
            uri: (string) $request->getUri(),
            statusCode: $statusCode,
            responseSize: (int) $responseSize,
        );

        // 7. Attach profile headers to response
        if ($profile !== null) {
            $response = $response
                ->withHeader(self::PROFILE_HEADER, $profile->id)
                ->withHeader(self::PROFILE_DURATION_HEADER, sprintf('%.2fms', $profile->durationMs));

            // 8. Inject debug toolbar into HTML responses
            if ($this->injector !== null) {
                $response = $this->injector->inject($response, $profile);
            }
        }

        return $response;
    }
}
