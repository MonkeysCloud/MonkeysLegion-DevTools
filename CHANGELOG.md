# Changelog

All notable changes to `monkeyscloud/monkeyslegion-devtools` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Contracts**: `CollectorInterface`, `ProfileStorageInterface`, `RedactorInterface`, `SamplerInterface`
- **Profiler Core**: `ProfileContext`, `Profile`, `Profiler` — full request lifecycle capture
- **Collectors**: `RequestCollector`, `RouteCollector`, `MiddlewareCollector`, `ExceptionCollector`
- **Storage**: `FileProfileStorage`, `MemoryProfileStorage`, `NullProfileStorage`
- **Redaction**: `KeyBasedRedactor` with recursive key matching and configurable sensitive keys
- **Sampling**: `RateSampler` with probabilistic rate-based sampling
- **Middleware**: `DevToolsMiddleware` — PSR-15 middleware for automatic request profiling
- **CLI Commands**: `devtools:status`, `devtools:requests`, `devtools:request`, `devtools:clear`, `devtools:export`
- **Attributes**: `#[Profile]`, `#[IgnoreProfile]`, `#[Redact]`
- **Configuration**: `devtools.mlc` with environment-aware defaults
- **Service Provider**: `DevToolsServiceProvider` for DI registration
