<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Storage;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use MonkeysLegion\DevTools\Storage\FileProfileStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileProfileStorage::class)]
final class FileProfileStorageTest extends TestCase
{
    private string $tempDir;
    private FileProfileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ml-devtools-test-' . bin2hex(random_bytes(4));
        $this->storage = new FileProfileStorage(
            path: $this->tempDir,
            maxProfiles: 10,
            retentionDays: 30,
        );
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    #[Test]
    public function save_and_find(): void
    {
        $profile = $this->createProfile();
        $this->storage->save($profile);

        $found = $this->storage->find($profile->id);
        $this->assertNotNull($found);
        $this->assertSame($profile->id, $found->id);
        $this->assertSame($profile->method, $found->method);
    }

    #[Test]
    public function find_returns_null_for_missing(): void
    {
        $this->assertNull($this->storage->find('nonexistent'));
    }

    #[Test]
    public function latest_returns_newest_first(): void
    {
        $p1 = $this->createProfile('GET', '/a');
        $p2 = $this->createProfile('POST', '/b');

        $this->storage->save($p1);
        $this->storage->save($p2);

        $latest = $this->storage->latest(2);
        $this->assertCount(2, $latest);
        $this->assertSame($p2->id, $latest[0]->id);
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

        $cleared = $this->storage->clear();
        $this->assertSame(2, $cleared);
        $this->assertSame(0, $this->storage->count());
    }

    #[Test]
    public function prune_enforces_max_profiles(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->storage->save($this->createProfile());
        }

        // maxProfiles is 10, so after prune there should be ≤10
        $this->assertLessThanOrEqual(10, $this->storage->count());
    }

    #[Test]
    public function count_returns_correct_value(): void
    {
        $this->assertSame(0, $this->storage->count());

        $this->storage->save($this->createProfile());
        $this->assertSame(1, $this->storage->count());

        $this->storage->save($this->createProfile());
        $this->assertSame(2, $this->storage->count());
    }

    #[Test]
    public function profile_count_hook(): void
    {
        $this->assertSame(0, $this->storage->profileCount);

        $this->storage->save($this->createProfile());
        $this->assertSame(1, $this->storage->profileCount);
    }

    #[Test]
    public function storage_path_hook(): void
    {
        $this->assertSame($this->tempDir, $this->storage->storagePath);
    }

    #[Test]
    public function query_filters_by_method(): void
    {
        $this->storage->save($this->createProfile('GET', '/a'));
        $this->storage->save($this->createProfile('POST', '/b'));

        $results = $this->storage->query(['method' => 'GET']);
        $this->assertCount(1, $results);
        $this->assertSame('GET', $results[0]->method);
    }

    #[Test]
    public function query_filters_by_uri(): void
    {
        $this->storage->save($this->createProfile('GET', '/api/users'));
        $this->storage->save($this->createProfile('GET', '/api/posts'));

        $results = $this->storage->query(['uri' => 'users']);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function query_filters_by_status(): void
    {
        $this->storage->save($this->createProfile('GET', '/ok', 200));
        $this->storage->save($this->createProfile('GET', '/fail', 500));

        $results = $this->storage->query(['status_min' => 400]);
        $this->assertCount(1, $results);
        $this->assertSame(500, $results[0]->statusCode);
    }

    #[Test]
    public function query_with_offset_and_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->storage->save($this->createProfile());
        }

        $results = $this->storage->query([], limit: 2, offset: 1);
        $this->assertCount(2, $results);
    }

    // ── Helpers ─────────────────────────────────────────────────

    private function createProfile(string $method = 'GET', string $uri = '/test', int $status = 200): Profile
    {
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

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
