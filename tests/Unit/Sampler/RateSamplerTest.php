<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Sampler;

use MonkeysLegion\DevTools\Sampler\RateSampler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateSampler::class)]
final class RateSamplerTest extends TestCase
{
    #[Test]
    public function rate_1_always_samples(): void
    {
        $sampler = new RateSampler(defaultRate: 1.0);

        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($sampler->shouldSample("req-{$i}", 'local'));
        }
    }

    #[Test]
    public function rate_0_never_samples(): void
    {
        $sampler = new RateSampler(defaultRate: 0.0);

        for ($i = 0; $i < 100; $i++) {
            $this->assertFalse($sampler->shouldSample("req-{$i}", 'local'));
        }
    }

    #[Test]
    public function environment_override_takes_precedence(): void
    {
        $sampler = new RateSampler(
            defaultRate: 1.0,
            environmentRates: ['production' => 0.0],
        );

        $this->assertTrue($sampler->shouldSample('req-1', 'local'));
        $this->assertFalse($sampler->shouldSample('req-2', 'production'));
    }

    #[Test]
    public function rate_returns_clamped_value(): void
    {
        $sampler = new RateSampler(
            defaultRate: 2.0, // Over 1.0
            environmentRates: ['test' => -0.5], // Under 0.0
        );

        $this->assertSame(1.0, $sampler->rate('local'));
        $this->assertSame(0.0, $sampler->rate('test'));
    }

    #[Test]
    public function effective_rate_property_hook_clamps(): void
    {
        $sampler = new RateSampler(defaultRate: 1.5);
        $this->assertSame(1.0, $sampler->effectiveRate);

        $sampler2 = new RateSampler(defaultRate: -0.5);
        $this->assertSame(0.0, $sampler2->effectiveRate);
    }

    #[Test]
    public function deterministic_sampling_is_consistent(): void
    {
        $sampler = new RateSampler(defaultRate: 0.5, deterministic: true);

        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $sampler->shouldSample('fixed-request-id', 'local');
        }

        // All results should be identical for the same request ID
        $this->assertCount(1, array_unique($results));
    }
}
