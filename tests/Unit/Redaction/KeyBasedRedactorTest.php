<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Redaction;

use MonkeysLegion\DevTools\Redaction\KeyBasedRedactor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyBasedRedactor::class)]
final class KeyBasedRedactorTest extends TestCase
{
    private KeyBasedRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new KeyBasedRedactor();
    }

    #[Test]
    public function redacts_sensitive_keys(): void
    {
        $data = ['username' => 'john', 'password' => 'secret123'];
        $result = $this->redactor->redact($data);

        $this->assertSame('john', $result['username']);
        $this->assertSame('████████', $result['password']);
    }

    #[Test]
    public function redacts_case_insensitively(): void
    {
        $data = ['Authorization' => 'Bearer xyz', 'COOKIE' => 'session=abc'];
        $result = $this->redactor->redact($data);

        $this->assertSame('████████', $result['Authorization']);
        $this->assertSame('████████', $result['COOKIE']);
    }

    #[Test]
    public function redacts_nested_arrays(): void
    {
        $data = ['config' => ['host' => 'localhost', 'password' => 'dbpass']];
        $result = $this->redactor->redact($data);

        $this->assertSame('localhost', $result['config']['host']);
        $this->assertSame('████████', $result['config']['password']);
    }

    #[Test]
    public function preserves_non_sensitive_keys(): void
    {
        $data = ['method' => 'GET', 'uri' => '/api/users', 'status_code' => 200];
        $this->assertSame($data, $this->redactor->redact($data));
    }

    #[Test]
    #[DataProvider('redactableKeyProvider')]
    public function is_redactable_detects_sensitive_keys(string $key, bool $expected): void
    {
        $this->assertSame($expected, $this->redactor->isRedactable($key));
    }

    public static function redactableKeyProvider(): iterable
    {
        yield 'password' => ['password', true];
        yield 'token'    => ['token', true];
        yield 'safe'     => ['username', false];
        yield 'method'   => ['method', false];
    }

    #[Test]
    public function last_redaction_count_tracks(): void
    {
        $this->redactor->redact(['password' => 's', 'token' => 't', 'safe' => 'v']);
        $this->assertSame(2, $this->redactor->lastRedactionCount);
    }

    #[Test]
    public function preserves_chars_when_configured(): void
    {
        $redactor = new KeyBasedRedactor(preserveChars: 3);
        $result = $redactor->redactValue('api_key', 'sk-1234567890abcdef');

        $this->assertStringStartsWith('sk-', $result);
        $this->assertStringContainsString('████████', $result);
    }
}
