<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Collector;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\ProfileContext;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Captures routing information: matched route, controller, action,
 * route parameters, and middleware stack. Beyond Symfony: includes
 * route pattern, parameter constraints, and OpenAPI metadata hints.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RouteCollector implements CollectorInterface
{
    // ── Captured Data ───────────────────────────────────────────

    private string $routeName = '';
    private string $routePath = '';
    private string $routePattern = '';
    private string $controller = '';
    private string $action = '';

    /** @var array<string, string> */
    private array $routeParams = [];

    /** @var list<string> */
    private array $middlewareStack = [];

    /** @var array<string, mixed> */
    private array $routeMetadata = [];

    /**
     * Fully qualified handler string — computed from controller + action.
     */
    public string $handler {
        get {
            if ($this->controller === '') {
                return '';
            }

            return $this->action !== ''
                ? "{$this->controller}::{$this->action}"
                : $this->controller;
        }
    }

    /**
     * Short handler name — without namespace prefix.
     */
    public string $shortHandler {
        get {
            $full = $this->handler;
            if ($full === '') {
                return '';
            }

            // Remove namespace, keep ClassName::method
            $parts = explode('\\', $full);

            return end($parts);
        }
    }

    public function __construct(
        private readonly bool $enabled = true,
    ) {}

    public function name(): string
    {
        return 'route';
    }

    public function label(): string
    {
        return 'Route';
    }

    public function icon(): string
    {
        return '🔀';
    }

    public function priority(): int
    {
        return 900;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(ProfileContext $context): void
    {
        // Reset state for new request
        $this->routeName = '';
        $this->routePath = '';
        $this->routePattern = '';
        $this->controller = '';
        $this->action = '';
        $this->routeParams = [];
        $this->middlewareStack = [];
        $this->routeMetadata = [];
    }

    public function stop(ProfileContext $context): void
    {
        // Data is set externally via setRouteData()
    }

    /**
     * Inject matched route information from the router.
     *
     * @param array<string, string> $params     Route parameters
     * @param list<string>          $middleware  Middleware stack
     * @param array<string, mixed>  $metadata   OpenAPI/extra metadata
     */
    public function setRouteData(
        string $name,
        string $path,
        string $pattern,
        string $controller,
        string $action,
        array $params = [],
        array $middleware = [],
        array $metadata = [],
    ): void {
        $this->routeName = $name;
        $this->routePath = $path;
        $this->routePattern = $pattern;
        $this->controller = $controller;
        $this->action = $action;
        $this->routeParams = $params;
        $this->middlewareStack = $middleware;
        $this->routeMetadata = $metadata;
    }

    public function collect(ProfileContext $context): array
    {
        return [
            'route_name'    => $this->routeName,
            'route_path'    => $this->routePath,
            'route_pattern' => $this->routePattern,
            'controller'    => $this->controller,
            'action'        => $this->action,
            'handler'       => $this->handler,
            'short_handler' => $this->shortHandler,
            'params'        => $this->routeParams,
            'middleware'     => $this->middlewareStack,
            'metadata'      => $this->routeMetadata,
            'has_openapi'   => isset($this->routeMetadata['summary']),
        ];
    }
}
