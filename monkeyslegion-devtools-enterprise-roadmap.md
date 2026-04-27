# MonkeysLegion DevTools — Enterprise Roadmap

> Package proposal: `monkeyscloud/monkeyslegion-devtools`  
> Target: MonkeysLegion v2.x ecosystem  
> Runtime philosophy: zero-magic, attribute-first, compiled, observable PHP 8.4+  
> Primary goal: give MonkeysLegion the enterprise-grade debugging, profiling, inspection, and operational visibility expected from mature PHP ecosystems — without compromising runtime performance.

---

## 1. Executive Summary

MonkeysLegion already has a strong modular foundation: core runtime, PSR-oriented HTTP layer, routing, DI, MLC config, database, query builder, entity metadata, migrations, auth, validation, cache, queue, scheduler, mail, telemetry, OpenAPI, CLI, and development server.

The next strategic package should be `monkeyslegion-devtools`.

The purpose of this package is to make MonkeysLegion applications easier to inspect, debug, optimize, and operate in production-like environments.

Laravel has ecosystem velocity.  
Symfony has a world-class profiler.  
MonkeysLegion should own the lane of:

> High-performance PHP 8.4+ applications with transparent internals, compiled infrastructure, and first-class developer observability.

`monkeyslegion-devtools` should not be a heavy runtime layer. It should be a development and diagnostics layer that can be disabled, sampled, redacted, or compiled out depending on the environment.

---

## 2. Strategic Positioning

### 2.1 Market Position

MonkeysLegion should position DevTools as:

```text
The profiler and inspection layer for zero-magic PHP 8.4+ applications.
```

### 2.2 Comparison Message

```text
Laravel built the PHP product ecosystem.
Symfony built the PHP enterprise foundation.
MonkeysLegion is building the PHP 8.4+ performance framework for APIs, SaaS platforms, AI backends, and cloud-native systems.

DevTools makes that performance visible.
```

### 2.3 Why This Package Matters

Without DevTools, MonkeysLegion can claim performance.

With DevTools, MonkeysLegion can prove performance.

The package should make it easy to answer:

- Why is this route slow?
- Which middleware ran?
- Which services were resolved?
- Which queries executed?
- Which cache keys missed?
- Which events fired?
- Which entity metadata was used?
- Which config values were loaded?
- Which OpenAPI routes are incomplete?
- Which requests are failing in production?
- Which parts of the app are causing memory growth?

---

## 3. Package Name and Installation

### 3.1 Composer Package

```bash
composer require monkeyscloud/monkeyslegion-devtools --dev
```

### 3.2 Optional Production Diagnostics Install

For enterprise teams that want safe sampling in staging or production:

```bash
composer require monkeyscloud/monkeyslegion-devtools
```

### 3.3 Package Autoload Namespace

```json
{
  "autoload": {
    "psr-4": {
      "MonkeysLegion\\DevTools\\": "src/"
    }
  }
}
```

---

## 4. Design Principles

### 4.1 Zero Runtime Penalty by Default

DevTools must not add overhead in production unless explicitly enabled.

```mlc
devtools {
  enabled = ${APP_ENV:local} == "local"
  toolbar = ${APP_DEBUG:false}
  storage = "file"
  sample_rate = 1.0
}
```

### 4.2 Explicit Collection

Collectors should be registered explicitly.

```mlc
devtools {
  collectors = [
    "request",
    "route",
    "query",
    "cache",
    "event",
    "container",
    "entity",
    "config",
    "exception"
  ]
}
```

### 4.3 Safe by Default

Sensitive values must be redacted.

```mlc
devtools {
  redact = [
    "authorization",
    "cookie",
    "set-cookie",
    "password",
    "password_hash",
    "token",
    "secret",
    "api_key",
    "private_key",
    "database_url"
  ]
}
```

### 4.4 Environment-Aware Behavior

| Environment | Toolbar | Profiler | Storage | Sampling | Sensitive Data |
|---|---:|---:|---|---:|---|
| local | yes | yes | file/sqlite | 100% | redacted |
| test | no | optional | memory | 100% | redacted |
| staging | optional | yes | sqlite/redis | 10–100% | redacted |
| production | no | optional | redis/telemetry | 0.1–5% | heavily redacted |

### 4.5 Framework-Native

DevTools should integrate with:

- `monkeyslegion-cli`
- `monkeyslegion-router`
- `monkeyslegion-http`
- `monkeyslegion-di`
- `monkeyslegion-mlc`
- `monkeyslegion-database`
- `monkeyslegion-query`
- `monkeyslegion-entity`
- `monkeyslegion-cache`
- `monkeyslegion-events`
- `monkeyslegion-telemetry`
- `monkeyslegion-openapi`
- `monkeyslegion-auth`
- `monkeyslegion-session`
- `monkeyslegion-validation`

---

## 5. Enterprise Feature Scope

## 5.1 Request Profiler

### Goal

Capture the full lifecycle of a request.

### Data Captured

```text
request_id
trace_id
method
uri
route_name
controller
status_code
duration_ms
memory_start
memory_peak
response_size
user_id_hash
ip_hash
middleware_stack
query_count
cache_hits
cache_misses
event_count
exception_summary
```

### CLI Commands

```bash
php ml devtools:requests
php ml devtools:request {id}
php ml devtools:slow --threshold=100ms
php ml devtools:failed
php ml devtools:clear
```

### Enterprise Additions

- Request comparison
- Slow request ranking
- Error request grouping
- Export request profile as JSON
- Shareable sanitized profile bundle

---

## 5.2 Debug Toolbar

### Goal

Provide local development visibility similar to mature PHP ecosystem profilers, but lighter and MonkeysLegion-native.

### Panels

```text
Request
Route
Controller
Middleware
Queries
Cache
Events
Container
Entity
Config
Session
Auth
Validation
OpenAPI
Logs
Telemetry
Exceptions
```

### Toolbar Rules

- Enabled only when `APP_DEBUG=true`.
- Disabled by default in production.
- Never expose secrets.
- Should inject into HTML responses only.
- Should skip JSON/API responses unless explicitly configured.

### Config

```mlc
devtools.toolbar {
  enabled = ${APP_DEBUG:false}
  inject_html = true
  api_header = "X-MonkeysLegion-Profile"
  max_payload_kb = 256
}
```

---

## 5.3 Query Profiler

### Goal

Expose database behavior clearly and detect performance issues.

### Data Captured

```text
connection_name
driver
sql_template
bindings_redacted
duration_ms
memory_delta
transaction_depth
row_count
source_file
source_line
```

### Detection Rules

```text
slow_query
duplicate_query
n_plus_one_pattern
transaction_too_long
missing_index_candidate
read_query_on_write_connection
write_query_outside_transaction
```

### Commands

```bash
php ml db:profile
php ml db:slow
php ml db:duplicates
php ml db:n-plus-one
php ml db:transactions
```

### Future Enterprise Features

- Query plan integration
- Index recommendation hints
- SQL fingerprinting
- Per-route database cost
- Per-entity query tracing

---

## 5.4 Cache Inspector

### Goal

Make cache behavior visible.

### Data Captured

```text
store
key_hash
key_preview
operation
hit
miss
ttl
duration_ms
tags
lock_wait_ms
serializer
payload_size
```

### Commands

```bash
php ml cache:stats
php ml cache:keys
php ml cache:hot
php ml cache:tags
php ml cache:locks
php ml cache:misses
```

### Enterprise Features

- Stampede protection visibility
- Lock contention warnings
- Hot key ranking
- Tag invalidation timeline
- Cache hit ratio by route
- Cache hit ratio by service

---

## 5.5 Container Inspector

### Goal

Make compiled dependency injection transparent.

### Data Captured

```text
service_id
class
lifetime
factory
dependencies
aliases
tags
resolved_count
compile_source
is_lazy
is_singleton
```

### Commands

```bash
php ml container:list
php ml container:show App\Service\UserService
php ml container:graph
php ml container:unused
php ml container:compile-check
php ml container:why App\Repository\UserRepository
```

### Enterprise Features

- Dependency graph export
- Circular dependency detection
- Unused service detection
- Missing binding detection
- Environment-specific service diff
- Compile-time validation report

---

## 5.6 Route Inspector

### Goal

Make route registration, matching, middleware, and API documentation visible.

### Commands

```bash
php ml route:list
php ml route:tree
php ml route:show users.index
php ml route:middleware
php ml route:conflicts
php ml route:security
php ml route:openapi-check
```

### Detection Rules

```text
duplicate_route_name
conflicting_route_path
unreachable_route
missing_auth_middleware
missing_validation_dto
missing_openapi_metadata
unsafe_cors_policy
missing_rate_limit
```

### Enterprise Features

- Route security score
- API route coverage report
- OpenAPI completeness report
- Public/private endpoint separation
- Route diff between releases

---

## 5.7 Event Timeline

### Goal

Show what happened during a request in chronological order.

### Example

```text
00.000ms KernelBooted
01.204ms RequestReceived
02.008ms RouteMatched
03.402ms MiddlewareStarted: AuthMiddleware
04.118ms ControllerResolved
05.884ms QueryExecuted
08.120ms CacheMiss
12.442ms ResponseCreated
13.001ms ResponseSent
```

### Commands

```bash
php ml events:list
php ml events:listeners
php ml events:trace {request_id}
php ml events:slow
```

### Enterprise Features

- Event storm detection
- Listener duration ranking
- Failed listener reporting
- Async event/queue correlation
- Event-to-request timeline view

---

## 5.8 Entity Inspector

### Goal

Expose entity metadata, mappings, relationships, validation, indexes, and schema drift.

### Commands

```bash
php ml entity:list
php ml entity:show User
php ml entity:relationships
php ml entity:indexes
php ml entity:audit
php ml entity:diff-schema
php ml entity:validate-metadata
```

### Detection Rules

```text
entity_field_missing_in_database
database_column_not_mapped
relationship_missing_index
invalid_cast
invalid_enum_mapping
missing_version_column
immutable_entity_update_path
audit_trail_missing_columns
```

### Enterprise Features

- Entity relationship graph
- Schema drift report
- Entity performance profile
- Hydration benchmark command
- Migration suggestion generator
- Multi-tenant query filter audit

---

## 5.9 Config Inspector

### Goal

Make MLC configuration transparent and safe.

### Commands

```bash
php ml config:show
php ml config:doctor
php ml config:diff --env=staging --env=production
php ml config:sources
php ml config:secrets-check
```

### Data Captured

```text
config_key
resolved_value_redacted
source_file
source_line
env_override
default_used
type
validation_status
```

### Enterprise Features

- Environment diff
- Missing required config detection
- Secret leakage detection
- Invalid type detection
- Runtime config snapshot
- Config compile report

---

## 5.10 OpenAPI Inspector

### Goal

Improve confidence in API documentation.

### Commands

```bash
php ml openapi:preview
php ml openapi:validate
php ml openapi:coverage
php ml openapi:diff
```

### Detection Rules

```text
route_missing_openapi_schema
response_missing_schema
request_missing_schema
auth_missing_from_spec
undocumented_status_code
dto_schema_mismatch
```

### Enterprise Features

- API contract diff between releases
- Breaking change detection
- OpenAPI coverage score
- DTO-to-schema validation
- Export docs bundle

---

## 5.11 Auth and Session Inspector

### Goal

Help debug authentication, authorization, sessions, CSRF, and role-based access.

### Commands

```bash
php ml auth:guards
php ml auth:roles
php ml auth:permissions
php ml session:inspect
php ml security:csrf
php ml security:headers
```

### Detection Rules

```text
route_missing_auth
route_missing_csrf
session_cookie_insecure
jwt_expiration_too_long
weak_password_hash_config
missing_security_headers
rbac_permission_conflict
```

### Enterprise Features

- Route-level security report
- Permission matrix export
- Session storage health check
- CSRF coverage report
- Security hardening checklist

---

## 5.12 Validation Inspector

### Goal

Improve debugging for DTO binding and attribute-based validation.

### Commands

```bash
php ml validation:list
php ml validation:show App\DTO\CreateUserDTO
php ml validation:coverage
```

### Detection Rules

```text
route_without_validation
dto_missing_required_rules
invalid_rule_configuration
unhandled_validation_exception
```

---

## 5.13 Logs and Exception Inspector

### Goal

Group, trace, and explain runtime exceptions.

### Commands

```bash
php ml logs:tail
php ml logs:errors
php ml exceptions:list
php ml exceptions:show {hash}
```

### Features

- Exception grouping by fingerprint
- Source frame highlighting
- Related request ID
- Related query/event/cache activity
- Sanitized stack trace export
- Error trend over time

---

## 5.14 Telemetry Bridge

### Goal

Connect DevTools with MonkeysLegion Telemetry.

### Features

```text
trace_id propagation
span collection
metrics export
request duration histograms
query duration histograms
cache hit/miss metrics
queue job metrics
route-level latency metrics
```

### Export Targets

```text
OpenTelemetry
Prometheus
JSON profile export
MonkeysCloud future dashboard
```

---

## 6. Architecture

## 6.1 High-Level Components

```text
MonkeysLegion-DevTools/
├─ src/
│  ├─ Attribute/
│  ├─ Collector/
│  ├─ Contract/
│  ├─ Event/
│  ├─ Exception/
│  ├─ Middleware/
│  ├─ Profiler/
│  ├─ Redaction/
│  ├─ Reporter/
│  ├─ Storage/
│  ├─ Toolbar/
│  ├─ Command/
│  ├─ Integration/
│  └─ DevToolsServiceProvider.php
├─ config/
│  └─ devtools.mlc
├─ resources/
│  ├─ toolbar/
│  └─ views/
├─ tests/
├─ composer.json
├─ phpunit.xml
└─ README.md
```

---

## 6.2 Collector Architecture

### Interface

```php
<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Contract;

interface CollectorInterface
{
  public function name(): string;

  public function isEnabled(): bool;

  public function start(ProfileContext $context): void;

  public function stop(ProfileContext $context): void;

  public function collect(ProfileContext $context): array;
}
```

### Collector Examples

```text
RequestCollector
RouteCollector
MiddlewareCollector
QueryCollector
CacheCollector
EventCollector
ContainerCollector
EntityCollector
ConfigCollector
AuthCollector
SessionCollector
ValidationCollector
OpenApiCollector
ExceptionCollector
TelemetryCollector
```

---

## 6.3 Profile Context

```php
<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Profiler;

final class ProfileContext
{
  public function __construct(
    public readonly string $id,
    public readonly string $traceId,
    public readonly float $startedAt,
    public readonly string $environment,
    public readonly bool $sampled,
  ) {}
}
```

---

## 6.4 Profile Storage

### Interface

```php
<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Storage;

interface ProfileStorageInterface
{
  public function save(Profile $profile): void;

  public function find(string $id): ?Profile;

  public function latest(int $limit = 50): array;

  public function delete(string $id): void;

  public function clear(): int;
}
```

### Storage Drivers

```text
MemoryProfileStorage
FileProfileStorage
SqliteProfileStorage
RedisProfileStorage
NullProfileStorage
TelemetryProfileStorage
```

### Recommended Defaults

```text
local: file or sqlite
test: memory
staging: sqlite or redis
production: redis or telemetry
```

---

## 6.5 Middleware Integration

```php
<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Middleware;

final class DevToolsMiddleware
{
  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
    // 1. Decide sampling
    // 2. Start profile context
    // 3. Start collectors
    // 4. Execute request
    // 5. Stop collectors
    // 6. Save profile
    // 7. Attach profile header
    // 8. Optionally inject toolbar
  }
}
```

---

## 6.6 Attributes

```php
#[Profile]
#[IgnoreProfile]
#[Trace]
#[TraceQuery]
#[Redact]
#[Sensitive]
#[DevToolsPanel]
```

Example:

```php
#[Profile(name: 'user.index')]
#[Route('GET', '/users')]
public function index(): Response
{
  // ...
}
```

---

## 7. Configuration

## 7.1 Full `devtools.mlc` Example

```mlc
devtools {
  enabled = ${APP_ENV:local} == "local"
  environment = ${APP_ENV:local}
  sample_rate = 1.0

  toolbar {
    enabled = ${APP_DEBUG:false}
    inject_html = true
    api_header = "X-MonkeysLegion-Profile"
    max_payload_kb = 256
  }

  storage {
    driver = "file"
    path = "var/devtools/profiles"
    retention_days = 7
    max_profiles = 1000
  }

  collectors {
    request = true
    route = true
    middleware = true
    query = true
    cache = true
    event = true
    container = true
    entity = true
    config = true
    auth = true
    session = true
    validation = true
    openapi = true
    exception = true
    telemetry = true
  }

  redaction {
    enabled = true
    keys = [
      "authorization",
      "cookie",
      "set-cookie",
      "password",
      "password_hash",
      "token",
      "secret",
      "api_key",
      "private_key",
      "database_url"
    ]
  }

  production {
    enabled = false
    sample_rate = 0.01
    toolbar = false
    require_signed_access = true
    export_sanitized_only = true
  }

  security {
    allowed_ips = ["127.0.0.1", "::1"]
    require_auth = true
    role = "developer"
  }
}
```

---

## 8. CLI Command Roadmap

## 8.1 Core Commands

```bash
php ml devtools:install
php ml devtools:status
php ml devtools:requests
php ml devtools:request {id}
php ml devtools:slow
php ml devtools:failed
php ml devtools:clear
php ml devtools:export {id}
```

## 8.2 Route Commands

```bash
php ml route:tree
php ml route:conflicts
php ml route:security
php ml route:openapi-check
```

## 8.3 Container Commands

```bash
php ml container:list
php ml container:show {service}
php ml container:graph
php ml container:unused
php ml container:compile-check
```

## 8.4 Database Commands

```bash
php ml db:profile
php ml db:slow
php ml db:duplicates
php ml db:n-plus-one
php ml db:transactions
```

## 8.5 Cache Commands

```bash
php ml cache:stats
php ml cache:hot
php ml cache:tags
php ml cache:locks
php ml cache:misses
```

## 8.6 Entity Commands

```bash
php ml entity:list
php ml entity:show {entity}
php ml entity:relationships
php ml entity:indexes
php ml entity:diff-schema
php ml entity:validate-metadata
```

## 8.7 Config Commands

```bash
php ml config:doctor
php ml config:diff
php ml config:sources
php ml config:secrets-check
```

## 8.8 Security Commands

```bash
php ml security:routes
php ml security:headers
php ml security:csrf
php ml auth:permissions
```

## 8.9 Telemetry Commands

```bash
php ml telemetry:profile
php ml telemetry:export
php ml telemetry:trace {trace_id}
```

---

## 9. Web UI Roadmap

## 9.1 Local Toolbar

The toolbar should be small, fast, and framework-native.

### Panels

```text
Overview
Route
Queries
Cache
Events
Container
Entity
Config
Logs
Exception
OpenAPI
Security
```

## 9.2 DevTools Dashboard

Route:

```text
/_ml/devtools
```

Views:

```text
Requests
Slow Requests
Failed Requests
Query Explorer
Cache Explorer
Route Explorer
Container Graph
Entity Graph
Config Doctor
Security Report
OpenAPI Coverage
```

## 9.3 Access Control

Local:

```text
127.0.0.1 only by default
```

Staging:

```text
authenticated developer role required
```

Production:

```text
disabled by default
signed temporary links only if enabled
sanitized output only
```

---

## 10. Enterprise Security Requirements

## 10.1 Redaction

Must redact:

```text
Authorization headers
Cookies
Session IDs
JWTs
API keys
Passwords
Database URLs
Private keys
OAuth tokens
Webhook secrets
Payment data
```

## 10.2 Data Retention

```mlc
devtools.storage {
  retention_days = 7
  max_profiles = 1000
  rotate = true
}
```

## 10.3 Access Control

```mlc
devtools.security {
  require_auth = true
  role = "developer"
  allowed_ips = ["127.0.0.1"]
}
```

## 10.4 Compliance Considerations

DevTools should support:

```text
PII redaction
profile retention policies
audit logs for profile access
export controls
production-safe sampling
environment-based disable
```

---

## 11. Testing Strategy

## 11.1 Unit Tests

Target:

```text
90%+ coverage for core collectors, redaction, storage, and commands
```

Test areas:

```text
Profiler lifecycle
Collector registration
Storage drivers
Redaction rules
Sampling logic
Toolbar injection
CLI output
Config loading
Profile export
Exception grouping
```

## 11.2 Integration Tests

Test with:

```text
Router
HTTP middleware
DI container
Database/query
Cache
Events
Entity
Telemetry
OpenAPI
Auth/session
Validation
```

## 11.3 Performance Tests

Required benchmarks:

```text
disabled overhead
enabled local overhead
query collector overhead
cache collector overhead
event collector overhead
toolbar rendering time
profile serialization time
```

Acceptance target:

```text
disabled overhead: near zero
local profiling overhead: acceptable for development
production sampling overhead: minimal and bounded
```

## 11.4 Security Tests

Test:

```text
secret redaction
header redaction
cookie redaction
body redaction
signed access
IP allowlist
role enforcement
production disable behavior
```

---

## 12. Release Roadmap

## Phase 0 — Research and Contracts

### Goal

Define architecture and stable contracts before implementation.

### Deliverables

```text
CollectorInterface
ProfileContext
Profile model
ProfileStorageInterface
RedactorInterface
SamplerInterface
DevToolsServiceProvider
devtools.mlc
```

### Exit Criteria

```text
Contracts documented
Package skeleton created
CI configured
PHPStan/Psalm baseline clean
PHPUnit running
```

---

## Phase 1 — MVP Profiler

### Goal

Ship a usable request profiler.

### Features

```text
Request profiler
Route collector
Middleware collector
Exception collector
File storage
Basic CLI commands
Basic profile export
```

### Commands

```bash
php ml devtools:install
php ml devtools:requests
php ml devtools:request {id}
php ml devtools:clear
```

### Exit Criteria

```text
Can capture request lifecycle
Can inspect latest requests in CLI
Can export sanitized JSON
No toolbar yet required
```

---

## Phase 2 — Toolbar and Local DX

### Goal

Deliver a strong local developer experience.

### Features

```text
HTML debug toolbar
Overview panel
Route panel
Query panel
Cache panel
Exception panel
Events panel
Config panel
```

### Exit Criteria

```text
Toolbar works on HTML responses
Toolbar skips API responses by default
Secrets are redacted
Can open full profile from toolbar
```

---

## Phase 3 — Database, Cache, and Events Intelligence

### Goal

Move beyond visibility into diagnostics.

### Features

```text
Slow query detection
Duplicate query detection
N+1 heuristic
Cache hit/miss reports
Hot cache keys
Event timeline
Slow listeners
```

### Exit Criteria

```text
Can identify slowest route/query/cache/event contributors
Can rank problems by request
Can generate diagnostics report
```

---

## Phase 4 — Container, Entity, and Config Inspectors

### Goal

Expose MonkeysLegion internals clearly.

### Features

```text
Container graph
Service inspector
Unused service detection
Entity metadata inspector
Schema drift detector
Config doctor
Environment config diff
```

### Exit Criteria

```text
Can inspect compiled DI
Can detect entity/schema mismatch
Can detect config issues before deployment
```

---

## Phase 5 — OpenAPI, Security, and Validation Reports

### Goal

Make DevTools useful for enterprise API governance.

### Features

```text
OpenAPI coverage score
Route security report
Validation coverage report
CSRF coverage report
Security headers check
Permission matrix export
```

### Exit Criteria

```text
Can run pre-release API readiness report
Can detect missing auth/validation/docs
Can export security report
```

---

## Phase 6 — Production Diagnostics

### Goal

Enable safe diagnostics in staging and production.

### Features

```text
Sampling
Redis storage
Telemetry export
Signed profile access
PII redaction
Retention policies
Profile access audit logs
```

### Exit Criteria

```text
Production disabled by default
Sampling works when enabled
All profile output sanitized
Telemetry bridge stable
```

---

## Phase 7 — MonkeysCloud Integration

### Goal

Prepare DevTools for hosted diagnostics inside MonkeysCloud.

### Features

```text
MonkeysCloud project profile sync
Environment dashboard
Release comparison
Trace-based deploy analysis
Slow endpoint trends
Error groups
Team access control
```

### Exit Criteria

```text
Can export sanitized profiles to MonkeysCloud
Can compare release before/after
Can support future hosted DevTools dashboard
```

---

## 13. Versioning Plan

## 13.1 v0.1.0

```text
Contracts
Request profiler
File storage
CLI request list/show
Redaction
```

## 13.2 v0.2.0

```text
Route collector
Middleware collector
Exception collector
Profile export
```

## 13.3 v0.3.0

```text
Query collector
Cache collector
Event collector
Slow query reports
```

## 13.4 v0.4.0

```text
Toolbar MVP
Overview panel
Route panel
Query panel
Cache panel
Exception panel
```

## 13.5 v0.5.0

```text
Container inspector
Config inspector
Entity inspector
```

## 13.6 v0.6.0

```text
OpenAPI coverage
Security report
Validation coverage
```

## 13.7 v0.7.0

```text
Redis storage
Telemetry bridge
Sampling
Signed access
```

## 13.8 v1.0.0

```text
Stable contracts
Stable CLI
Stable toolbar
Production-safe diagnostics
Full documentation
Benchmarks published
```

---

## 14. Recommended Internal Milestones

## Milestone A — Package Foundation

```text
Repository setup
composer.json
CI
PHPUnit
PHPStan
Coding standard
README
LICENSE
devtools.mlc
Service provider
```

## Milestone B — Profile Engine

```text
ProfileContext
Profile model
Profiler
Sampler
Redactor
Storage interface
File storage
```

## Milestone C — HTTP Integration

```text
DevToolsMiddleware
Request lifecycle capture
Response headers
Exception capture
Profile persistence
```

## Milestone D — CLI Integration

```text
devtools:install
devtools:status
devtools:requests
devtools:request
devtools:clear
devtools:export
```

## Milestone E — Framework Collectors

```text
RouteCollector
MiddlewareCollector
QueryCollector
CacheCollector
EventCollector
ContainerCollector
EntityCollector
ConfigCollector
```

## Milestone F — Toolbar

```text
Toolbar renderer
Toolbar assets
Panel registry
HTML injection
Panel templates
Full profile page
```

## Milestone G — Enterprise Reports

```text
Performance report
Security report
OpenAPI report
Validation report
Config report
Schema drift report
```

---

## 15. Repository Structure

```text
MonkeysLegion-DevTools/
├─ .github/
│  └─ workflows/
│     ├─ ci.yml
│     ├─ static-analysis.yml
│     └─ release.yml
├─ config/
│  └─ devtools.mlc
├─ resources/
│  ├─ toolbar/
│  │  ├─ toolbar.html.php
│  │  ├─ toolbar.css
│  │  └─ toolbar.js
│  └─ views/
│     ├─ requests.html.php
│     ├─ profile.html.php
│     └─ panels/
├─ src/
│  ├─ Attribute/
│  ├─ Collector/
│  ├─ Command/
│  ├─ Contract/
│  ├─ Event/
│  ├─ Exception/
│  ├─ Integration/
│  │  ├─ Cache/
│  │  ├─ Database/
│  │  ├─ Entity/
│  │  ├─ Events/
│  │  ├─ Http/
│  │  ├─ OpenApi/
│  │  └─ Telemetry/
│  ├─ Middleware/
│  ├─ Profiler/
│  ├─ Redaction/
│  ├─ Reporter/
│  ├─ Sampler/
│  ├─ Security/
│  ├─ Storage/
│  ├─ Toolbar/
│  └─ DevToolsServiceProvider.php
├─ tests/
│  ├─ Unit/
│  ├─ Integration/
│  ├─ Security/
│  └─ Performance/
├─ composer.json
├─ phpstan.neon
├─ phpunit.xml
├─ README.md
└─ CHANGELOG.md
```

---

## 16. Example `composer.json`

```json
{
  "name": "monkeyscloud/monkeyslegion-devtools",
  "description": "Enterprise debugging, profiling, inspection, and diagnostics package for MonkeysLegion.",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": "^8.4",
    "psr/http-message": "^2.0",
    "psr/http-server-middleware": "^1.0",
    "psr/http-server-handler": "^1.0",
    "psr/container": "^2.0",
    "psr/log": "^3.0",
    "monkeyscloud/monkeyslegion-contracts": "^2.0",
    "monkeyscloud/monkeyslegion-core": "^2.0",
    "monkeyscloud/monkeyslegion-mlc": "^3.2"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0",
    "phpstan/phpstan": "^2.0",
    "squizlabs/php_codesniffer": "^3.10"
  },
  "suggest": {
    "monkeyscloud/monkeyslegion-router": "Route collector support",
    "monkeyscloud/monkeyslegion-database": "Query profiling support",
    "monkeyscloud/monkeyslegion-cache": "Cache profiling support",
    "monkeyscloud/monkeyslegion-events": "Event timeline support",
    "monkeyscloud/monkeyslegion-entity": "Entity inspection support",
    "monkeyscloud/monkeyslegion-telemetry": "Telemetry export support",
    "monkeyscloud/monkeyslegion-openapi": "OpenAPI coverage support"
  },
  "autoload": {
    "psr-4": {
      "MonkeysLegion\\DevTools\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "MonkeysLegion\\DevTools\\Tests\\": "tests/"
    }
  },
  "bin": [
    "bin/ml-devtools"
  ],
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

---

## 17. Enterprise Reports

## 17.1 Performance Report

```bash
php ml devtools:report performance
```

Output:

```text
Slowest routes
Slowest controllers
Slowest queries
Duplicate queries
N+1 candidates
Highest memory routes
Cache miss hotspots
Slow listeners
```

## 17.2 Security Report

```bash
php ml devtools:report security
```

Output:

```text
Public routes
Routes missing auth
Routes missing CSRF
Routes missing rate limits
Session cookie config
Security headers
JWT expiration config
Permission matrix
```

## 17.3 API Governance Report

```bash
php ml devtools:report api
```

Output:

```text
OpenAPI coverage
Routes missing schemas
Breaking changes
Validation coverage
Undocumented status codes
DTO/schema mismatch
```

## 17.4 Deployment Readiness Report

```bash
php ml devtools:report readiness
```

Output:

```text
Config validation
Secret leakage check
Route conflict check
Entity/schema drift
Migration status
Cache health
Queue health
Scheduler health
Telemetry status
```

---

## 18. MonkeysCloud Integration Strategy

This package should become the bridge between MonkeysLegion and MonkeysCloud.

### Future MonkeysCloud Features

```text
Project diagnostics dashboard
Request profile upload
Release performance comparison
Error grouping
Slow endpoint alerts
Deployment readiness checks
Team developer access
Environment comparison
```

### CLI Example

```bash
php ml devtools:cloud:connect
php ml devtools:cloud:upload-profile {id}
php ml devtools:cloud:compare --release=2026.04.1 --release=2026.04.2
```

### Business Advantage

This creates a natural upgrade path:

```text
Open-source framework → DevTools package → Hosted MonkeysCloud diagnostics
```

---

## 19. Documentation Plan

## 19.1 Required Docs

```text
README.md
Installation guide
Configuration guide
Toolbar guide
CLI command guide
Collector development guide
Storage driver guide
Security/redaction guide
Production diagnostics guide
MonkeysCloud integration guide
Benchmark report
```

## 19.2 Example Docs Structure

```text
docs/
├─ getting-started.md
├─ configuration.md
├─ toolbar.md
├─ cli.md
├─ collectors.md
├─ storage.md
├─ security.md
├─ production.md
├─ telemetry.md
├─ extending-devtools.md
└─ benchmarks.md
```

---

## 20. Marketing Copy

## 20.1 Short Positioning

```text
MonkeysLegion DevTools gives PHP 8.4+ teams the visibility of an enterprise profiler without sacrificing the zero-magic runtime model that makes MonkeysLegion fast.
```

## 20.2 README Hero

```md
# MonkeysLegion DevTools

Enterprise-grade debugging, profiling, and diagnostics for MonkeysLegion.

Inspect every request, route, query, cache operation, event, service, entity, config value, and exception — without adding heavyweight runtime magic to your application.

Built for PHP 8.4+.  
Designed for local speed.  
Safe for enterprise diagnostics.  
Ready for MonkeysCloud.
```

## 20.3 Launch Post

```text
Laravel has Telescope.
Symfony has the Profiler.
MonkeysLegion now has DevTools.

A lightweight, PHP 8.4+ inspection layer for routes, requests, queries, cache, events, compiled DI, entities, config, OpenAPI, and production-safe diagnostics.

Performance should not be a black box.
```

---

## 21. Definition of Done for v1.0

DevTools is v1.0-ready when:

```text
Core profile lifecycle is stable
Toolbar is stable
CLI commands are documented
Collectors are extensible
Redaction is tested
Storage drivers are tested
Production diagnostics are safe by default
Telemetry bridge works
Security report works
Performance report works
OpenAPI coverage works
Entity/config/container inspectors work
Documentation is complete
Benchmarks are published
```

---

## 22. Recommended First Implementation Sprint

### Sprint Goal

Create the foundation of `monkeyslegion-devtools` and ship the first usable CLI profiler.

### Sprint Tasks

```text
Create repository
Create composer.json
Create DevToolsServiceProvider
Create devtools.mlc
Create ProfileContext
Create Profile model
Create CollectorInterface
Create RequestCollector
Create RouteCollector
Create ExceptionCollector
Create Redactor
Create Sampler
Create FileProfileStorage
Create DevToolsMiddleware
Create devtools:requests command
Create devtools:request command
Create devtools:clear command
Add PHPUnit tests
Add PHPStan
Write README MVP
```

### First Sprint Output

```bash
composer require monkeyscloud/monkeyslegion-devtools --dev
php ml devtools:install
php ml devtools:requests
php ml devtools:request latest
```

---

## 23. Final Recommendation

Build `monkeyslegion-devtools` before `monkeyslegion-admin`, `monkeyslegion-billing`, or `monkeyslegion-saas-kit`.

Reason:

```text
DevTools improves every future package.
```

Admin needs entity visibility.  
Billing needs webhook and queue visibility.  
SaaS Kit needs auth/session/security visibility.  
Deploy needs readiness checks and telemetry.  
MonkeysCloud needs diagnostics data.

DevTools becomes the foundation for the enterprise ecosystem.

The strategic message becomes:

```text
MonkeysLegion is not just fast.
MonkeysLegion is fast, explicit, inspectable, and enterprise-ready.
```
