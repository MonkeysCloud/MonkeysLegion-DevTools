<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools;

use MonkeysLegion\DevTools\Collector\CacheCollector;
use MonkeysLegion\DevTools\Collector\EventCollector;
use MonkeysLegion\DevTools\Collector\ExceptionCollector;
use MonkeysLegion\DevTools\Collector\MiddlewareCollector;
use MonkeysLegion\DevTools\Collector\QueryCollector;
use MonkeysLegion\DevTools\Collector\RequestCollector;
use MonkeysLegion\DevTools\Collector\RouteCollector;
use MonkeysLegion\DevTools\Toolbar\Panel\CachePanel;
use MonkeysLegion\DevTools\Toolbar\Panel\EventPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\ExceptionPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\OverviewPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\QueryPanel;
use MonkeysLegion\DevTools\Toolbar\ToolbarInjector;
use MonkeysLegion\DevTools\Toolbar\ToolbarRenderer;
use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Contract\ProfileStorageInterface;
use MonkeysLegion\DevTools\Contract\RedactorInterface;
use MonkeysLegion\DevTools\Contract\SamplerInterface;
use MonkeysLegion\DevTools\Middleware\DevToolsMiddleware;
use MonkeysLegion\DevTools\Profiler\Profiler;
use MonkeysLegion\DevTools\Redaction\KeyBasedRedactor;
use MonkeysLegion\DevTools\Sampler\RateSampler;
use MonkeysLegion\DevTools\Storage\FileProfileStorage;
use MonkeysLegion\DevTools\Storage\NullProfileStorage;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Service provider that bootstraps the DevTools subsystem.
 * Registers the profiler, storage, redactor, sampler, collectors,
 * middleware, and CLI commands into the DI container.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class DevToolsServiceProvider
{
    /**
     * Whether DevTools has been booted.
     */
    public private(set) bool $booted = false;

    /**
     * Resolved profiler instance — set after boot.
     */
    public private(set) ?Profiler $profiler = null;

    /**
     * Resolved toolbar renderer — set after boot.
     */
    public private(set) ?ToolbarRenderer $toolbar = null;

    /**
     * Resolved toolbar injector — set after boot.
     */
    public private(set) ?ToolbarInjector $injector = null;

    // ── Configuration Defaults ──────────────────────────────────

    private const string DEFAULT_STORAGE_PATH = 'var/devtools/profiles';
    private const int DEFAULT_MAX_PROFILES = 1000;
    private const int DEFAULT_RETENTION_DAYS = 7;
    private const float DEFAULT_SAMPLE_RATE = 1.0;
    private const float DEFAULT_SLOW_THRESHOLD_MS = 200.0;

    /**
     * Boot DevTools with the given configuration.
     *
     * @param array<string, mixed> $config Configuration from devtools.mlc
     */
    public function boot(array $config = []): Profiler
    {
        $enabled = (bool) ($config['enabled'] ?? true);
        $environment = (string) ($config['environment'] ?? 'local');

        // Resolve storage
        $storage = $this->resolveStorage($config['storage'] ?? []);

        // Resolve redactor
        $redactor = $this->resolveRedactor($config['redaction'] ?? []);

        // Resolve sampler
        $sampler = $this->resolveSampler($config);

        // Resolve logger
        $logger = new NullLogger();

        // Create profiler
        $profiler = new Profiler(
            storage: $storage,
            redactor: $redactor,
            sampler: $sampler,
            environment: $environment,
            slowThresholdMs: (float) ($config['thresholds']['slow_request_ms'] ?? self::DEFAULT_SLOW_THRESHOLD_MS),
            enabled: $enabled,
            logger: $logger,
        );

        // Register collectors
        $this->registerCollectors($profiler, $config['collectors'] ?? [], $config['thresholds'] ?? []);

        // Build toolbar if enabled
        $toolbarEnabled = (bool) ($config['toolbar']['enabled'] ?? false);
        if ($toolbarEnabled) {
            $this->toolbar = $this->buildToolbar();
            $this->injector = new ToolbarInjector(
                renderer: $this->toolbar,
                maxPayloadKb: (int) ($config['toolbar']['max_payload_kb'] ?? 256),
            );
        }

        $this->profiler = $profiler;
        $this->booted = true;

        return $profiler;
    }

    /**
     * Create the DevTools PSR-15 middleware.
     */
    public function createMiddleware(): DevToolsMiddleware
    {
        if ($this->profiler === null) {
            throw new \RuntimeException('DevTools has not been booted. Call boot() first.');
        }

        return new DevToolsMiddleware($this->profiler, $this->injector);
    }

    /**
     * Get all registered collector instances.
     *
     * @return array<string, CollectorInterface>
     */
    public function getCollectors(): array
    {
        if ($this->profiler === null) {
            return [];
        }

        $collectors = [];
        foreach ($this->profiler->collectorNames as $name) {
            $collector = $this->profiler->getCollector($name);
            if ($collector !== null) {
                $collectors[$name] = $collector;
            }
        }

        return $collectors;
    }

    // ── Private Resolution ──────────────────────────────────────

    /**
     * @param array<string, mixed> $storageConfig
     */
    private function resolveStorage(array $storageConfig): ProfileStorageInterface
    {
        $driver = (string) ($storageConfig['driver'] ?? 'file');

        return match ($driver) {
            'file' => new FileProfileStorage(
                path: (string) ($storageConfig['path'] ?? self::DEFAULT_STORAGE_PATH),
                maxProfiles: (int) ($storageConfig['max_profiles'] ?? self::DEFAULT_MAX_PROFILES),
                retentionDays: (int) ($storageConfig['retention_days'] ?? self::DEFAULT_RETENTION_DAYS),
            ),
            'null', 'none' => new NullProfileStorage(),
            default => new FileProfileStorage(
                path: (string) ($storageConfig['path'] ?? self::DEFAULT_STORAGE_PATH),
            ),
        };
    }

    /**
     * @param array<string, mixed> $redactionConfig
     */
    private function resolveRedactor(array $redactionConfig): RedactorInterface
    {
        $enabled = (bool) ($redactionConfig['enabled'] ?? true);

        if (!$enabled) {
            return new KeyBasedRedactor(sensitiveKeys: []);
        }

        $keys = (array) ($redactionConfig['keys'] ?? []);

        if ($keys !== []) {
            return new KeyBasedRedactor(sensitiveKeys: $keys);
        }

        // Use default built-in sensitive keys
        return new KeyBasedRedactor();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveSampler(array $config): SamplerInterface
    {
        $defaultRate = (float) ($config['sample_rate'] ?? self::DEFAULT_SAMPLE_RATE);

        // Build environment-specific rates
        $environmentRates = [];

        if (isset($config['production']['sample_rate'])) {
            $environmentRates['production'] = (float) $config['production']['sample_rate'];
        }

        return new RateSampler(
            defaultRate: $defaultRate,
            environmentRates: $environmentRates,
        );
    }

    /**
     * @param array<string, mixed> $collectorsConfig
     */
    /**
     * @param array<string, mixed> $collectorsConfig
     * @param array<string, mixed> $thresholds
     */
    private function registerCollectors(Profiler $profiler, array $collectorsConfig, array $thresholds = []): void
    {
        // Request collector — always enabled if configured
        if ($this->isCollectorEnabled($collectorsConfig, 'request')) {
            $profiler->addCollector(new RequestCollector());
        }

        // Route collector
        if ($this->isCollectorEnabled($collectorsConfig, 'route')) {
            $profiler->addCollector(new RouteCollector());
        }

        // Middleware collector
        if ($this->isCollectorEnabled($collectorsConfig, 'middleware')) {
            $profiler->addCollector(new MiddlewareCollector());
        }

        // Query collector
        if ($this->isCollectorEnabled($collectorsConfig, 'query')) {
            $profiler->addCollector(new QueryCollector(
                slowQueryThresholdMs: (float) ($thresholds['slow_query_ms'] ?? 100.0),
                nPlusOneThreshold: (int) ($thresholds['n_plus_one_count'] ?? 5),
            ));
        }

        // Cache collector
        if ($this->isCollectorEnabled($collectorsConfig, 'cache')) {
            $profiler->addCollector(new CacheCollector());
        }

        // Event collector
        if ($this->isCollectorEnabled($collectorsConfig, 'event')) {
            $profiler->addCollector(new EventCollector());
        }

        // Exception collector
        if ($this->isCollectorEnabled($collectorsConfig, 'exception')) {
            $profiler->addCollector(new ExceptionCollector());
        }
    }

    /**
     * Build the toolbar renderer with all default panels.
     */
    private function buildToolbar(): ToolbarRenderer
    {
        $toolbar = new ToolbarRenderer();

        $toolbar->addPanel(new OverviewPanel());
        $toolbar->addPanel(new QueryPanel());
        $toolbar->addPanel(new CachePanel());
        $toolbar->addPanel(new EventPanel());
        $toolbar->addPanel(new ExceptionPanel());

        return $toolbar;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function isCollectorEnabled(array $config, string $name): bool
    {
        return (bool) ($config[$name] ?? true);
    }
}
