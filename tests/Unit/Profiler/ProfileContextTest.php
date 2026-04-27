<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Profiler;

use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProfileContext::class)]
final class ProfileContextTest extends TestCase
{
    #[Test]
    public function create_generates_unique_ids(): void
    {
        $ctx1 = ProfileContext::create('local', true);
        $ctx2 = ProfileContext::create('local', true);

        $this->assertNotSame($ctx1->id, $ctx2->id);
        $this->assertNotSame($ctx1->traceId, $ctx2->traceId);
    }

    #[Test]
    public function create_sets_environment_and_sampled(): void
    {
        $ctx = ProfileContext::create('staging', false);

        $this->assertSame('staging', $ctx->environment);
        $this->assertFalse($ctx->sampled);
    }

    #[Test]
    public function create_records_start_time_and_memory(): void
    {
        $ctx = ProfileContext::create('local', true);

        $this->assertGreaterThan(0.0, $ctx->startedAt);
        $this->assertGreaterThan(0, $ctx->memoryStart);
    }

    #[Test]
    public function elapsed_ms_returns_positive_value(): void
    {
        $ctx = ProfileContext::create('local', true);

        // Even a tiny delay should produce > 0
        $elapsed = $ctx->elapsedMs();
        $this->assertGreaterThanOrEqual(0.0, $elapsed);
    }

    #[Test]
    public function id_is_32_char_hex(): void
    {
        $ctx = ProfileContext::create('local', true);

        $this->assertSame(32, strlen($ctx->id));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $ctx->id);
    }

    #[Test]
    public function trace_id_is_32_char_hex(): void
    {
        $ctx = ProfileContext::create('local', true);

        $this->assertSame(32, strlen($ctx->traceId));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $ctx->traceId);
    }
}
