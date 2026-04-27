<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4%2B-7A86B8?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.4+">
  <img src="https://img.shields.io/badge/License-MIT-9ece6a?style=for-the-badge" alt="MIT License">
  <img src="https://img.shields.io/badge/Tests-184%20passed-9ece6a?style=for-the-badge" alt="Tests">
  <img src="https://img.shields.io/badge/Coverage-90%25%2B-7aa2f7?style=for-the-badge" alt="Coverage">
  <img src="https://img.shields.io/badge/Property%20Hooks-26-e0af68?style=for-the-badge" alt="PHP 8.4 Hooks">
</p>

# 🐒 MonkeysLegion DevTools

**Enterprise-grade debugging, profiling, and diagnostics for MonkeysLegion v2.**

Inspect every request, route, query, cache operation, event, and exception — with a self-contained debug toolbar, intelligent anomaly detection, and zero runtime overhead when disabled.

```
Laravel has Telescope.  Symfony has the Web Profiler.
MonkeysLegion has DevTools — and it's smarter than both.
```

---

## ✨ Key Features

| Feature | Description |
|---------|-------------|
| 🚀 **Zero Overhead** | Early-return when disabled — no reflection, no magic, no cost |
| 🪝 **PHP 8.4 Property Hooks** | 26 computed properties replace traditional getters across the codebase |
| 🔍 **N+1 Detection** | Automatic duplicate query grouping with configurable thresholds |
| ⚡ **Cache Intelligence** | Hit/miss ratios, hot key ranking, per-store breakdown |
| 📡 **Event Storm Detection** | Timeline visualization with storm alerts and slow listener tracking |
| 🎯 **Deterministic Sampling** | Hash-based consistency for distributed trace coherence |
| 🔒 **Redaction Pipeline** | Recursive key-based masking — secrets never leak, even in dev |
| 🛡️ **Request Fingerprinting** | xxh3-based grouping for automatic request clustering |
| 🖥️ **Debug Toolbar** | Self-contained Tokyo Night UI — no external assets required |
| 🧪 **184 Tests** | 572 assertions across 22 test files — 90%+ coverage |

---

## 📦 Installation

```bash
composer require monkeyscloud/monkeyslegion-devtools --dev
```

For production diagnostics (sampling + redacted output):

```bash
composer require monkeyscloud/monkeyslegion-devtools
```

### Requirements

- PHP 8.4+ (property hooks, asymmetric visibility)
- PSR-7 HTTP Message (`psr/http-message ^2.0`)
- PSR-15 Middleware (`psr/http-server-middleware ^1.0`)

---

## 🚀 Quick Start

### 1. Boot & Register Middleware

```php
use MonkeysLegion\DevTools\DevToolsServiceProvider;

$devtools = new DevToolsServiceProvider();
$profiler = $devtools->boot([
    'enabled'     => true,
    'environment' => 'local',
    'sample_rate' => 1.0,
    'storage'     => ['driver' => 'file', 'path' => 'var/devtools/profiles'],
    'collectors'  => [
        'request'    => true,
        'route'      => true,
        'middleware'  => true,
        'query'      => true,
        'cache'      => true,
        'event'      => true,
        'exception'  => true,
    ],
    'toolbar' => ['enabled' => true],
]);

// Add to your PSR-15 pipeline
$middleware = $devtools->createMiddleware();
```

### 2. Record Intelligence Data

```php
// Query profiling — N+1 is detected automatically
$queryCollector = $profiler->getCollector('query');
$queryCollector->recordQuery('SELECT * FROM users WHERE id = ?', durationMs: 2.3, connection: 'mysql');

// Cache tracking — hot keys are ranked automatically
$cacheCollector = $profiler->getCollector('cache');
$cacheCollector->recordOperation('redis', 'user:42', 'get', hit: true, durationMs: 0.4);

// Event timeline — storms are flagged automatically
$eventCollector = $profiler->getCollector('event');
$eventCollector->recordDispatch('App\Event\OrderPlaced', [
    ['name' => 'SendConfirmation', 'duration_ms' => 45.2],
    ['name' => 'UpdateInventory', 'duration_ms' => 12.1],
]);
```

### 3. Inspect via CLI

```bash
# System status
php ml devtools:status

# List recent profiled requests (color-coded by status)
php ml devtools:requests --limit=20

# Inspect the latest request in detail
php ml devtools:request latest

# Inspect by partial ID
php ml devtools:request c461

# Export a sanitized profile for sharing
php ml devtools:export latest --output=profile.json

# Clear all stored profiles
php ml devtools:clear
```

### 4. Debug Toolbar

When `toolbar.enabled` is `true`, the toolbar auto-injects into HTML responses:

- **Toggle**: Click the 🐒 bar or press `Ctrl+Shift+D`
- **5 Panels**: Overview, Queries, Cache, Events, Exceptions
- **Severity Badges**: Green / Yellow / Red per-panel status at a glance
- **Self-Contained**: Zero external CSS/JS — embedded Tokyo Night theme

The toolbar only injects into `text/html` responses and respects a configurable size limit.

---

## ⚙️ Configuration

Create `config/devtools.mlc`:

```mlc
devtools {
    enabled     = ${DEVTOOLS_ENABLED:true}
    environment = ${APP_ENV:local}
    sample_rate = ${DEVTOOLS_SAMPLE_RATE:1.0}

    storage {
        driver         = "file"
        path           = "var/devtools/profiles"
        retention_days = 7
        max_profiles   = 1000
    }

    collectors {
        request    = true
        route      = true
        middleware = true
        query      = true
        cache      = true
        event      = true
        exception  = true
    }

    redaction {
        enabled = true
        keys    = ["password", "token", "secret", "api_key", "authorization", "cookie"]
    }

    thresholds {
        slow_request_ms  = 200
        slow_query_ms    = 100
        n_plus_one_count = 5
    }

    toolbar {
        enabled        = true
        max_payload_kb = 256
    }

    production {
        sample_rate = 0.01
    }
}
```

---

## 🔬 Collectors

### Built-in Collectors (7)

| # | Collector | Icon | Priority | Key Features |
|---|-----------|------|----------|--------------|
| 1 | **Request** | 🌐 | 1000 | Method, URI, headers, IP hashing, fingerprint, timing, memory |
| 2 | **Middleware** | 🔗 | 950 | Per-middleware timing, memory delta, bottleneck detection |
| 3 | **Route** | 🔀 | 900 | Pattern, controller, params, middleware stack, OpenAPI hints |
| 4 | **Query** | 🗄️ | 800 | SQL timing, N+1 detection, duplicate grouping, source tracing |
| 5 | **Cache** | ⚡ | 700 | Hit/miss ratio, hot key ranking, per-store breakdown |
| 6 | **Event** | 📡 | 600 | Timeline, storm detection, slow listeners, failed tracking |
| 7 | **Exception** | 💥 | 100 | Class, message, trace, chain traversal, xxh3 fingerprint |

### Intelligence Features

#### N+1 Query Detection
```
🔴 N+1 DETECTED: SELECT * FROM comments WHERE post_id = ? (×47 executions)
   Source: App\Repository\CommentRepository::findByPost() at line 42
```

The QueryCollector automatically fingerprints SQL by normalizing literals and compares execution counts against the configurable threshold.

#### Cache Hot Key Analysis
```
🔥 Hot Keys:
   user:session:abc123  (×84 accesses)
   config:app           (×31 accesses)
   route:cache          (×22 accesses)
```

#### Event Storm Detection
```
🌪️ STORM: App\Event\CacheInvalidated dispatched 142 times in this request
```

### Custom Collectors

Implement `CollectorInterface` to add your own:

```php
use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\ProfileContext;

final class MyCollector implements CollectorInterface
{
    public function name(): string { return 'custom'; }
    public function label(): string { return 'Custom'; }
    public function icon(): string { return '🔧'; }
    public function priority(): int { return 500; }
    public function isEnabled(): bool { return true; }

    public function start(ProfileContext $context): void { /* setup */ }
    public function stop(ProfileContext $context): void { /* teardown */ }
    public function collect(ProfileContext $context): array { return [...]; }
}

$profiler->addCollector(new MyCollector());
```

---

## 🏷️ Attributes

```php
use MonkeysLegion\DevTools\Attribute\{Profile, IgnoreProfile, Redact};

// Force profiling on a specific route with a label
#[Profile(name: 'checkout.process', includePayload: true)]
public function processCheckout(): Response { }

// Exclude high-frequency endpoints from profiling
#[IgnoreProfile(reason: 'health check')]
public function healthCheck(): Response { }

// Mark sensitive constructor parameters
public function __construct(
    #[Redact] private readonly string $apiSecret,
    #[Redact(replacement: '***')] private readonly string $dbPassword,
) {}
```

---

## 🪝 PHP 8.4 Property Hooks

DevTools leverages **26 PHP 8.4 property hooks** — replacing traditional getters with declarative computed properties. This is a first for PHP frameworks.

```php
// Profile model — zero getters, all computed
$profile->durationMs           // float: endedAt - startedAt
$profile->durationFormatted    // "42.7ms" | "1.23s" | "850μs"
$profile->isError              // statusCode >= 400
$profile->isSlow               // durationMs > threshold
$profile->statusBadge          // 🟢 🔵 🟠 🔴 ⚪
$profile->memoryPeakFormatted  // "12.4 MB"
$profile->memoryDelta          // bytes used during request
$profile->createdAtFormatted   // "2026-04-27 03:42:53.087"

// Profiler engine — live state hooks
$profiler->isActive            // currently profiling?
$profiler->collectorCount      // number of registered collectors
$profiler->collectorNames      // ['request', 'query', 'cache', ...]

// QueryCollector — computed analytics
$collector->queryCount         // total queries recorded
$collector->totalDurationMs    // sum of all query times
$collector->duplicateCount     // grouped duplicate queries
$collector->hasNPlusOne        // automatic N+1 flag
$collector->slowestQueryMs     // max single query time

// CacheCollector — computed metrics
$collector->hitRatio           // hits / total (float)
$collector->hitRatioFormatted  // "85.7%"
$collector->operationCount     // total operations tracked

// EventCollector — computed state
$collector->hasStorm           // event storm detected?
$collector->failedListenerCount // listeners that threw
$collector->totalListenerMs    // aggregate listener time

// ServiceProvider — boot state
$provider->booted              // has boot() been called?
$provider->profiler            // resolved Profiler instance
$provider->toolbar             // resolved ToolbarRenderer
```

### Asymmetric Visibility

All mutable state uses `private(set)` — the profiler's internal state is read-only for external consumers:

```php
public private(set) string $id;
public private(set) bool $booted = false;
public private(set) ?Profiler $profiler = null;
```

---

## 🗄️ Storage Drivers

| Driver | Use Case | Persistence |
|--------|----------|-------------|
| `FileProfileStorage` | Local development — JSON files with index | ✅ Disk |
| `MemoryProfileStorage` | Testing & benchmarks | ❌ Request-scoped |
| `NullProfileStorage` | Disabled / CI environments | ❌ No-op |

File storage features:
- **JSON format** — human-readable, diffable, grep-friendly
- **Index file** — fast listing without deserializing all profiles
- **Retention pruning** — automatic cleanup on write (no cron needed)
- **Query filtering** — method, URI, status, duration, environment, time range

---

## 🖥️ Debug Toolbar Panels

### Overview Panel
Request summary, performance alerts (slow/error), and collector badge aggregation.

### Query Panel
SQL listing with per-query timing, duplicate warnings (🟡), and N+1 alerts (🔴). Source file tracing for each query.

### Cache Panel
Hit/miss ratio gauges, per-store breakdown table, and 🔥 hot key ranking.

### Event Panel
Chronological timeline with relative timestamps, storm warnings (🌪️), and failed listener tracking.

### Exception Panel
Error display with class highlighting, sanitized stack traces, and previous exception chain traversal.

### Custom Panels

Extend `AbstractPanel` to add your own:

```php
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;
use MonkeysLegion\DevTools\Profiler\Profile;

final class MyPanel extends AbstractPanel
{
    public function id(): string { return 'custom'; }
    public function label(): string { return 'Custom'; }
    public function icon(): string { return '🔧'; }
    public function priority(): int { return 400; }

    public function badge(Profile $profile): string { return '3 items'; }
    public function badgeSeverity(Profile $profile): string { return 'ok'; }

    public function render(Profile $profile): string
    {
        return $this->section('Data', $this->renderTable([
            'Key' => 'Value',
        ]));
    }
}
```

---

## 🏗️ Architecture

```
src/
├── Attribute/               # #[Profile], #[IgnoreProfile], #[Redact]
├── Collector/               # 7 intelligence collectors
│   ├── CacheCollector.php       # Hit/miss, hot keys, per-store
│   ├── EventCollector.php       # Timeline, storms, slow listeners
│   ├── ExceptionCollector.php   # Traces, chains, fingerprinting
│   ├── MiddlewareCollector.php  # Per-middleware timing, bottlenecks
│   ├── QueryCollector.php       # N+1, duplicates, SQL fingerprinting
│   ├── RequestCollector.php     # Headers, IP hash, fingerprint
│   └── RouteCollector.php       # Pattern, controller, OpenAPI
├── Command/                 # 5 CLI commands (devtools:*)
├── Contract/                # Stable interfaces
├── Exception/               # Package exceptions
├── Middleware/               # PSR-15 DevToolsMiddleware
├── Profiler/                # Core engine
│   ├── Profile.php              # 10 property hooks
│   ├── ProfileContext.php       # Request lifecycle context
│   └── Profiler.php             # Orchestrator with 3 hooks
├── Redaction/               # Key-based recursive masking
├── Sampler/                 # Rate-based + deterministic sampling
├── Storage/                 # File, Memory, Null drivers
├── Toolbar/                 # Self-contained debug toolbar
│   ├── Panel/                   # 5 built-in panels
│   ├── AbstractPanel.php        # Rendering helpers
│   ├── PanelInterface.php       # Panel contract
│   ├── ToolbarInjector.php      # HTML response injection
│   └── ToolbarRenderer.php      # Self-contained HTML/CSS/JS
└── DevToolsServiceProvider.php  # Config-driven bootstrap
```

**40 source files** · **5,099 lines** · **22 test files** · **184 tests** · **572 assertions**

---

## 🆚 Competitive Comparison

| Feature | Symfony Profiler | Laravel Telescope | **ML DevTools** |
|---------|:---------------:|:-----------------:|:---------------:|
| PHP Version | 8.2+ | 8.3+ | **8.4+** |
| Property Hooks | 0 | 0 | **26** |
| N+1 Detection | ❌ | ❌ | **✅ Automatic** |
| Event Storm Detection | ❌ | ❌ | **✅** |
| Cache Hot Key Analysis | ❌ | ❌ | **✅** |
| Deterministic Sampling | ❌ | ❌ | **✅ Hash-based** |
| Request Fingerprinting | ❌ | ❌ | **✅ xxh3** |
| Priority Collectors | ❌ | ❌ | **✅ With wrap** |
| Partial Value Redaction | ❌ | ❌ | **✅** |
| Self-Contained Toolbar | ❌ Requires Webpack | ❌ Requires Tailwind | **✅ Embedded** |
| Zero-Overhead Disabled | ⚠️ Partial | ❌ | **✅ Full** |
| Asymmetric Visibility | ❌ | ❌ | **✅ private(set)** |
| MLC Config Format | ❌ | ❌ | **✅ Native** |

---

## 🧪 Testing

```bash
# Run all tests
composer test

# With testdox output
./vendor/bin/phpunit --testdox

# Static analysis
composer analyse
```

### Test Suite Coverage

| Area | Test Files | Tests | Assertions |
|------|-----------|-------|------------|
| Collectors (7) | 7 | 62 | 178 |
| Profiler Core | 3 | 28 | 102 |
| Storage (3) | 3 | 28 | 72 |
| Toolbar & Panels | 3 | 38 | 108 |
| Service Provider | 1 | 12 | 34 |
| Middleware | 1 | 4 | 12 |
| Attributes | 1 | 9 | 18 |
| Exceptions | 1 | 3 | 6 |
| Redaction | 1 | 10 | 24 |
| Sampler | 1 | 6 | 18 |
| **Total** | **22** | **184** | **572** |

---

## 🗺️ Roadmap

| Phase | Focus | Status |
|-------|-------|--------|
| 0 | Contracts & Skeleton | ✅ Complete |
| 1 | MVP Profiler & CLI | ✅ Complete |
| 2 | Debug Toolbar & Local DX | ✅ Complete |
| 3 | Query, Cache & Event Intelligence | ✅ Complete |
| 4 | Container, Entity & Config Inspectors | 🔜 Next |
| 5 | OpenAPI, Security & Validation Reports | 🔜 Planned |
| 6 | Production Diagnostics & APM | 🔜 Planned |
| 7 | MonkeysCloud SaaS Integration | 🔜 Planned |

---

## 🤝 Contributing

1. Fork & clone the repository
2. Install dependencies: `composer install`
3. Run the test suite: `composer test`
4. Run static analysis: `composer analyse`
5. Submit a PR against `main`

Please ensure all new collectors include corresponding unit tests and property hooks where applicable.

---

## 📄 License

MIT — © 2026 MonkeysCloud Team

Built with 🐒 by [MonkeysCloud](https://monkeyslegion.com)
