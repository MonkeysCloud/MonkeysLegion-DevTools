<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Attribute;

use MonkeysLegion\DevTools\Attribute\IgnoreProfile;
use MonkeysLegion\DevTools\Attribute\Profile;
use MonkeysLegion\DevTools\Attribute\Redact;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Profile::class)]
#[CoversClass(IgnoreProfile::class)]
#[CoversClass(Redact::class)]
final class AttributeTest extends TestCase
{
    #[Test]
    public function profile_defaults(): void
    {
        $attr = new Profile();
        $this->assertSame('', $attr->name);
        $this->assertFalse($attr->includePayload);
    }

    #[Test]
    public function profile_with_params(): void
    {
        $attr = new Profile(name: 'checkout', includePayload: true);
        $this->assertSame('checkout', $attr->name);
        $this->assertTrue($attr->includePayload);
    }

    #[Test]
    public function ignore_profile_defaults(): void
    {
        $attr = new IgnoreProfile();
        $this->assertSame('', $attr->reason);
    }

    #[Test]
    public function ignore_profile_with_reason(): void
    {
        $attr = new IgnoreProfile(reason: 'health check');
        $this->assertSame('health check', $attr->reason);
    }

    #[Test]
    public function redact_defaults(): void
    {
        $attr = new Redact();
        $this->assertSame('████████', $attr->replacement);
    }

    #[Test]
    public function redact_custom_replacement(): void
    {
        $attr = new Redact(replacement: '***');
        $this->assertSame('***', $attr->replacement);
    }

    #[Test]
    public function profile_is_attribute(): void
    {
        $ref = new \ReflectionClass(Profile::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertNotEmpty($attrs);
    }

    #[Test]
    public function ignore_profile_is_attribute(): void
    {
        $ref = new \ReflectionClass(IgnoreProfile::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertNotEmpty($attrs);
    }

    #[Test]
    public function redact_is_attribute(): void
    {
        $ref = new \ReflectionClass(Redact::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertNotEmpty($attrs);
    }
}
