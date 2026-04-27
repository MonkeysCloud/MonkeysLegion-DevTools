# MonkeysLegion DevTools

Enterprise-grade debugging, profiling, and diagnostics for MonkeysLegion.

Inspect every request, route, query, cache operation, event, service, entity, config value, and exception — without adding heavyweight runtime magic to your application.

Built for PHP 8.4+.  
Designed for local speed.  
Safe for enterprise diagnostics.  
Ready for MonkeysCloud.

---

## Why DevTools?

```
Laravel has Telescope.
Symfony has the Profiler.
MonkeysLegion now has DevTools.
```

A lightweight, PHP 8.4+ inspection layer with:
- **Zero overhead** when disabled — no magic, no runtime cost
- **Property hooks** for computed profile metadata (duration, memory, status)
- **Deterministic sampling** for distributed trace consistency
- **Request fingerprinting** for automatic request grouping
- **Redaction pipeline** — secrets never leak, not even in dev
- **Priority-ordered collectors** with wrap semantics for correct timing

---

## Installation

```bash
composer require monkeyscloud/monkeyslegion-devtools --dev
```

For production diagnostics (sampling + redacted output):

```bash
composer require monkeyscloud/monkeyslegion-devtools
```

---

## Quick Start

### 1. Register the middleware

```php
// In your middleware pipeline
$devtools = new DevToolsServiceProvider();
$profiler = $devtools->boot([
    'enabled'     => true,
    'environment' => 'local',
    'sample_rate' => 1.0,
]);

$middleware = $devtools->createMiddleware();
// Add $middleware to your PSR-15 pipeline
```

### 2. Inspect via CLI

```bash
# List recent profiled requests
php ml devtools:requests

# Show detail for the latest request
php ml devtools:request latest

# Show DevTools status
php ml devtools:status

# Export a sanitized profile
php ml devtools:export latest --output=profile.json

# Clear stored profiles
php ml devtools:clear
```

---

## Configuration

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
        exception  = true
    }

    redaction {
        enabled = true
        keys    = ["password", "token", "secret", "api_key", "authorization"]
    }

    thresholds {
        slow_request_ms = 200
    }
}
```

---

## Collectors

| Collector | Data Captured | Icon |
|-----------|--------------|------|
| **Request** | Method, URI, status, timing, memory, headers, IP hash, fingerprint | 🌐 |
| **Route** | Name, pattern, controller, action, params, middleware stack | 🔀 |
| **Middleware** | Per-middleware timing, memory delta, bottleneck detection | 🔗 |
| **Exception** | Class, message, trace, chain, fingerprint for grouping | 💥 |

### Coming Soon

- **Query** — SQL profiling, N+1 detection, slow query alerts
- **Cache** — Hit/miss ratios, hot keys, stampede warnings
- **Event** — Timeline, slow listeners, storm detection
- **Container** — Dependency graph, unused services, circular detection
- **Entity** — Schema drift, relationship mapping, hydration benchmarks
- **Config** — Doctor, environment diff, secret leakage detection
- **OpenAPI** — Coverage scoring, contract validation
- **Auth** — Guard inspection, permission matrix

---

## Attributes

```php
use MonkeysLegion\DevTools\Attribute\Profile;
use MonkeysLegion\DevTools\Attribute\IgnoreProfile;
use MonkeysLegion\DevTools\Attribute\Redact;

// Force profiling on a specific route
#[Profile(name: 'checkout.process')]
public function processCheckout(): Response { }

// Exclude high-frequency endpoints
#[IgnoreProfile(reason: 'health check')]
public function healthCheck(): Response { }

// Mark sensitive data
public function __construct(
    #[Redact] private readonly string $apiSecret,
) {}
```

---

## PHP 8.4 Property Hooks

DevTools leverages PHP 8.4 property hooks throughout:

```php
// Profile model — computed properties, no getters needed
$profile->durationMs       // Computed from timestamps
$profile->durationFormatted // "42.7ms" or "1.23s"
$profile->isError          // statusCode >= 400
$profile->isSlow           // durationMs > threshold
$profile->statusBadge      // 🟢 🔵 🟠 🔴
$profile->memoryPeakFormatted // "12.4 MB"
$profile->memoryDelta      // Bytes used during request

// Profiler — live state
$profiler->isActive        // Currently profiling?
$profiler->collectorCount  // Number of collectors
$profiler->collectorNames  // List of collector names
```

---

## Architecture

```
src/
├── Attribute/        # #[Profile], #[IgnoreProfile], #[Redact]
├── Collector/        # Request, Route, Middleware, Exception
├── Command/          # CLI commands (devtools:*)
├── Contract/         # Stable interfaces
├── Exception/        # Package exceptions
├── Middleware/       # PSR-15 DevToolsMiddleware
├── Profiler/         # Core engine (Profile, ProfileContext, Profiler)
├── Redaction/        # Key-based sensitive data redaction
├── Sampler/          # Rate-based + deterministic sampling
├── Storage/          # File, Memory, Null drivers
└── DevToolsServiceProvider.php
```

---

## Roadmap

| Phase | Focus | Status |
|-------|-------|--------|
| 0 | Contracts & Skeleton | ✅ Complete |
| 1 | MVP Profiler & CLI | ✅ Complete |
| 2 | Debug Toolbar & Local DX | 🔜 Planned |
| 3 | Database, Cache & Events Intelligence | 🔜 Planned |
| 4 | Container, Entity & Config Inspectors | 🔜 Planned |
| 5 | OpenAPI, Security & Validation Reports | 🔜 Planned |
| 6 | Production Diagnostics | 🔜 Planned |
| 7 | MonkeysCloud Integration | 🔜 Planned |

---

## License

MIT — © 2026 MonkeysCloud Team
