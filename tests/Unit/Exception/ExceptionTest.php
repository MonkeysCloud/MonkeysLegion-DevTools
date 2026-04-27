<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Exception;

use MonkeysLegion\DevTools\Exception\DevToolsException;
use MonkeysLegion\DevTools\Exception\ProfileNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DevToolsException::class)]
#[CoversClass(ProfileNotFoundException::class)]
final class ExceptionTest extends TestCase
{
    #[Test]
    public function devtools_exception_extends_runtime(): void
    {
        $e = new DevToolsException('test');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertSame('test', $e->getMessage());
    }

    #[Test]
    public function profile_not_found_extends_devtools(): void
    {
        $e = ProfileNotFoundException::withId('abc123');
        $this->assertInstanceOf(DevToolsException::class, $e);
        $this->assertStringContainsString('abc123', $e->getMessage());
    }

    #[Test]
    public function profile_not_found_factory(): void
    {
        $e = ProfileNotFoundException::withId('test-id');
        $this->assertSame("Profile 'test-id' not found.", $e->getMessage());
    }
}
