<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Storage;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use MonkeysLegion\DevTools\Storage\NullProfileStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullProfileStorage::class)]
final class NullProfileStorageTest extends TestCase
{
    private NullProfileStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new NullProfileStorage();
    }

    #[Test]
    public function save_does_nothing(): void
    {
        $ctx = ProfileContext::create('test', true);
        $profile = Profile::fromContext($ctx, $ctx->startedAt + 1.0, []);

        $this->storage->save($profile);

        $this->assertSame(0, $this->storage->count());
    }

    #[Test]
    public function find_returns_null(): void
    {
        $this->assertNull($this->storage->find('any'));
    }

    #[Test]
    public function latest_returns_empty(): void
    {
        $this->assertSame([], $this->storage->latest());
    }

    #[Test]
    public function query_returns_empty(): void
    {
        $this->assertSame([], $this->storage->query());
    }

    #[Test]
    public function delete_does_nothing(): void
    {
        $this->storage->delete('any');
        $this->assertSame(0, $this->storage->count());
    }

    #[Test]
    public function clear_returns_zero(): void
    {
        $this->assertSame(0, $this->storage->clear());
    }

    #[Test]
    public function count_is_zero(): void
    {
        $this->assertSame(0, $this->storage->count());
    }

    #[Test]
    public function prune_returns_zero(): void
    {
        $this->assertSame(0, $this->storage->prune());
    }
}
