<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Profiler;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Profile::class)]
final class ProfileTest extends TestCase
{
    #[Test]
    public function from_context_creates_profile_with_computed_properties(): void
    {
        $ctx = ProfileContext::create('local', true);
        $endedAt = $ctx->startedAt + 150.0; // 150ms duration

        $profile = Profile::fromContext(
            context: $ctx,
            endedAt: $endedAt,
            collectorData: ['request' => ['method' => 'GET']],
            method: 'GET',
            uri: '/api/users',
            statusCode: 200,
            responseSize: 1024,
        );

        $this->assertSame($ctx->id, $profile->id);
        $this->assertSame($ctx->traceId, $profile->traceId);
        $this->assertSame('GET', $profile->method);
        $this->assertSame('/api/users', $profile->uri);
        $this->assertSame(200, $profile->statusCode);
        $this->assertSame(1024, $profile->responseSize);
        $this->assertEqualsWithDelta(150.0, $profile->durationMs, 0.001);
    }

    #[Test]
    public function is_error_property_hook_detects_errors(): void
    {
        $profile200 = $this->createProfileWithStatus(200);
        $profile404 = $this->createProfileWithStatus(404);
        $profile500 = $this->createProfileWithStatus(500);

        $this->assertFalse($profile200->isError);
        $this->assertTrue($profile404->isError);
        $this->assertTrue($profile500->isError);
    }

    #[Test]
    public function is_slow_property_hook_detects_slow_requests(): void
    {
        $fast = $this->createProfileWithDuration(50.0);
        $slow = $this->createProfileWithDuration(500.0);

        $this->assertFalse($fast->isSlow);
        $this->assertTrue($slow->isSlow);
    }

    #[Test]
    #[DataProvider('statusBadgeProvider')]
    public function status_badge_property_hook_returns_correct_emoji(int $status, string $expected): void
    {
        $profile = $this->createProfileWithStatus($status);
        $this->assertSame($expected, $profile->statusBadge);
    }

    public static function statusBadgeProvider(): iterable
    {
        yield '200 OK'        => [200, '🟢'];
        yield '301 Redirect'  => [301, '🔵'];
        yield '404 Not Found' => [404, '🟠'];
        yield '500 Server'    => [500, '🔴'];
        yield '0 Unknown'     => [0, '⚪'];
    }

    #[Test]
    public function duration_formatted_hook_shows_correct_units(): void
    {
        $micro = $this->createProfileWithDuration(0.5);
        $milli = $this->createProfileWithDuration(42.7);
        $second = $this->createProfileWithDuration(2500.0);

        $this->assertStringContainsString('μs', $micro->durationFormatted);
        $this->assertStringContainsString('ms', $milli->durationFormatted);
        $this->assertStringContainsString('s', $second->durationFormatted);
    }

    #[Test]
    public function memory_peak_formatted_hook_formats_bytes(): void
    {
        $profile = $this->createProfileWithStatus(200);
        // memoryPeakFormatted should be a non-empty string
        $this->assertNotEmpty($profile->memoryPeakFormatted);
    }

    #[Test]
    public function to_array_and_from_array_roundtrip(): void
    {
        $ctx = ProfileContext::create('local', true);

        $original = Profile::fromContext(
            context: $ctx,
            endedAt: $ctx->startedAt + 100.0,
            collectorData: [
                'request' => ['method' => 'POST', 'uri' => '/login'],
                'exception' => ['count' => 0],
            ],
            method: 'POST',
            uri: '/login',
            statusCode: 201,
            responseSize: 256,
        );

        $array = $original->toArray();
        $restored = Profile::fromArray($array);

        $this->assertSame($original->id, $restored->id);
        $this->assertSame($original->traceId, $restored->traceId);
        $this->assertSame($original->method, $restored->method);
        $this->assertSame($original->uri, $restored->uri);
        $this->assertSame($original->statusCode, $restored->statusCode);
        $this->assertEqualsWithDelta($original->durationMs, $restored->durationMs, 0.001);
        $this->assertSame($original->collectors, $restored->collectors);
    }

    #[Test]
    public function collector_access_methods_work(): void
    {
        $ctx = ProfileContext::create('local', true);
        $profile = Profile::fromContext(
            context: $ctx,
            endedAt: $ctx->startedAt + 10.0,
            collectorData: ['request' => ['method' => 'GET']],
        );

        $this->assertTrue($profile->hasCollector('request'));
        $this->assertFalse($profile->hasCollector('nonexistent'));
        $this->assertSame(['method' => 'GET'], $profile->collector('request'));
        $this->assertSame([], $profile->collector('nonexistent'));
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function createProfileWithStatus(int $status): Profile
    {
        $ctx = ProfileContext::create('local', true);

        return Profile::fromContext(
            context: $ctx,
            endedAt: $ctx->startedAt + 50.0,
            collectorData: [],
            statusCode: $status,
        );
    }

    private function createProfileWithDuration(float $durationMs): Profile
    {
        $ctx = ProfileContext::create('local', true);

        return Profile::fromContext(
            context: $ctx,
            endedAt: $ctx->startedAt + $durationMs,
            collectorData: [],
            statusCode: 200,
        );
    }
}
