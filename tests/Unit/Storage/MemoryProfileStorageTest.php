<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Storage;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use MonkeysLegion\DevTools\Storage\MemoryProfileStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemoryProfileStorage::class)]
final class MemoryProfileStorageTest extends TestCase
{
    private MemoryProfileStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new MemoryProfileStorage();
    }

    #[Test]
    public function save_and_find(): void
    {
        $profile = $this->createProfile();
        $this->storage->save($profile);

        $found = $this->storage->find($profile->id);
        $this->assertNotNull($found);
        $this->assertSame($profile->id, $found->id);
    }

    #[Test]
    public function find_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->storage->find('nonexistent'));
    }

    #[Test]
    public function latest_returns_newest_first(): void
    {
        $p1 = $this->createProfile();
        $p2 = $this->createProfile();

        $this->storage->save($p1);
        $this->storage->save($p2);

        $latest = $this->storage->latest(2);
        $this->assertSame($p2->id, $latest[0]->id);
        $this->assertSame($p1->id, $latest[1]->id);
    }

    #[Test]
    public function delete_removes_profile(): void
    {
        $profile = $this->createProfile();
        $this->storage->save($profile);
        $this->assertSame(1, $this->storage->count());

        $this->storage->delete($profile->id);
        $this->assertSame(0, $this->storage->count());
        $this->assertNull($this->storage->find($profile->id));
    }

    #[Test]
    public function clear_removes_all(): void
    {
        $this->storage->save($this->createProfile());
        $this->storage->save($this->createProfile());
        $this->assertSame(2, $this->storage->count());

        $cleared = $this->storage->clear();
        $this->assertSame(2, $cleared);
        $this->assertSame(0, $this->storage->count());
    }

    #[Test]
    public function size_property_hook_works(): void
    {
        $this->assertSame(0, $this->storage->size);

        $this->storage->save($this->createProfile());
        $this->assertSame(1, $this->storage->size);
    }

    #[Test]
    public function query_filters_by_method(): void
    {
        $get = $this->createProfile('GET', '/a', 200);
        $post = $this->createProfile('POST', '/b', 201);

        $this->storage->save($get);
        $this->storage->save($post);

        $results = $this->storage->query(['method' => 'GET']);
        $this->assertCount(1, $results);
        $this->assertSame('GET', $results[0]->method);
    }

    private function createProfile(
        string $method = 'GET',
        string $uri = '/test',
        int $status = 200,
    ): Profile {
        $ctx = ProfileContext::create('test', true);

        return Profile::fromContext(
            context: $ctx,
            endedAt: $ctx->startedAt + 10.0,
            collectorData: [],
            method: $method,
            uri: $uri,
            statusCode: $status,
        );
    }
}
