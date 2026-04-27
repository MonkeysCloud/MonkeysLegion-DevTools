<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Toolbar;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use MonkeysLegion\DevTools\Toolbar\Panel\CachePanel;
use MonkeysLegion\DevTools\Toolbar\Panel\EventPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\ExceptionPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\OverviewPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\QueryPanel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OverviewPanel::class)]
#[CoversClass(QueryPanel::class)]
#[CoversClass(CachePanel::class)]
#[CoversClass(EventPanel::class)]
#[CoversClass(ExceptionPanel::class)]
final class PanelTest extends TestCase
{
    #[Test]
    public function overview_basics(): void
    {
        $p = new OverviewPanel();
        $this->assertSame('overview', $p->id());
        $this->assertSame('Overview', $p->label());
        $this->assertSame(1000, $p->priority());
        $this->assertNotEmpty($p->badge($this->prof()));
        $this->assertSame('ok', $p->badgeSeverity($this->prof()));
        $this->assertSame('warning', $p->badgeSeverity($this->prof(500.0)));
    }

    #[Test]
    public function overview_render(): void
    {
        $html = (new OverviewPanel())->render($this->prof(42.0, 500));
        $this->assertStringContainsString('GET', $html);
        $this->assertStringContainsString('Error', $html);
    }

    #[Test]
    public function overview_collector_badges(): void
    {
        $p = $this->profCol(['query' => ['count' => 5], 'cache' => ['hit_ratio_display' => '85%']]);
        $html = (new OverviewPanel())->render($p);
        $this->assertStringContainsString('5 queries', $html);
    }

    #[Test]
    public function query_basics(): void
    {
        $p = new QueryPanel();
        $this->assertSame('query', $p->id());
        $this->assertSame(800, $p->priority());
        $this->assertStringContainsString('No query', $p->render($this->prof()));
    }

    #[Test]
    public function query_severity(): void
    {
        $p = new QueryPanel();
        $this->assertSame('error', $p->badgeSeverity($this->profCol(['query' => ['has_n_plus_one' => true]])));
        $this->assertSame('warning', $p->badgeSeverity($this->profCol(['query' => ['has_n_plus_one' => false, 'duplicate_count' => 1, 'slow_count' => 0]])));
        $this->assertSame('ok', $p->badgeSeverity($this->profCol(['query' => ['has_n_plus_one' => false, 'duplicate_count' => 0, 'slow_count' => 0]])));
    }

    #[Test]
    public function query_render_with_data(): void
    {
        $profile = $this->profCol(['query' => [
            'count' => 1, 'total_duration_ms' => 5.0, 'duplicate_count' => 0,
            'has_n_plus_one' => false, 'duplicates' => [],
            'queries' => [['index' => 0, 'sql' => 'SELECT 1', 'duration_ms' => 5.0, 'connection' => 'mysql', 'source' => 'f:1', 'is_slow' => false, 'is_duplicate' => false]],
        ]]);
        $this->assertStringContainsString('SELECT', (new QueryPanel())->render($profile));
    }

    #[Test]
    public function query_render_duplicates(): void
    {
        $profile = $this->profCol(['query' => [
            'count' => 2, 'total_duration_ms' => 10.0, 'duplicate_count' => 1,
            'has_n_plus_one' => true,
            'duplicates' => [['fingerprint' => 'abc', 'count' => 5, 'sql_sample' => 'SELECT * FROM users WHERE id = ?', 'is_n_plus_one' => true]],
            'queries' => [],
        ]]);
        $html = (new QueryPanel())->render($profile);
        $this->assertStringContainsString('N+1', $html);
    }

    #[Test]
    public function cache_basics(): void
    {
        $p = new CachePanel();
        $this->assertSame('cache', $p->id());
        $this->assertSame(700, $p->priority());
        $this->assertSame('—', $p->badge($this->prof()));
    }

    #[Test]
    public function cache_severity(): void
    {
        $p = new CachePanel();
        $this->assertSame('ok', $p->badgeSeverity($this->profCol(['cache' => ['hit_ratio' => 0.9]])));
        $this->assertSame('warning', $p->badgeSeverity($this->profCol(['cache' => ['hit_ratio' => 0.6]])));
        $this->assertSame('error', $p->badgeSeverity($this->profCol(['cache' => ['hit_ratio' => 0.3]])));
    }

    #[Test]
    public function cache_render(): void
    {
        $profile = $this->profCol(['cache' => [
            'count' => 5, 'hits' => 4, 'misses' => 1, 'hit_ratio' => 0.8, 'hit_ratio_display' => '80%',
            'total_duration_ms' => 2.0, 'stores' => ['redis' => ['hits' => 4, 'misses' => 1, 'total_ms' => 2.0]],
            'hot_keys' => [['key' => 'hot', 'count' => 10]], 'operations' => [],
        ]]);
        $html = (new CachePanel())->render($profile);
        $this->assertStringContainsString('redis', $html);
        $this->assertStringContainsString('Hot Keys', $html);
    }

    #[Test]
    public function event_basics(): void
    {
        $p = new EventPanel();
        $this->assertSame('event', $p->id());
        $this->assertSame(600, $p->priority());
    }

    #[Test]
    public function event_severity(): void
    {
        $p = new EventPanel();
        $this->assertSame('error', $p->badgeSeverity($this->profCol(['event' => ['has_storm' => true, 'failed_listener_count' => 0]])));
        $this->assertSame('warning', $p->badgeSeverity($this->profCol(['event' => ['has_storm' => false, 'failed_listener_count' => 1]])));
        $this->assertSame('ok', $p->badgeSeverity($this->profCol(['event' => ['has_storm' => false, 'failed_listener_count' => 0]])));
    }

    #[Test]
    public function event_render(): void
    {
        $profile = $this->profCol(['event' => [
            'event_count' => 1, 'listener_count' => 1, 'total_listener_ms' => 5.0,
            'failed_listener_count' => 0, 'has_storm' => false, 'storms' => [],
            'timeline' => [['index' => 0, 'event' => 'UserCreated', 'relative_ms' => 1.0, 'listener_count' => 1, 'listeners' => []]],
        ]]);
        $this->assertStringContainsString('UserCreated', (new EventPanel())->render($profile));
    }

    #[Test]
    public function exception_basics(): void
    {
        $p = new ExceptionPanel();
        $this->assertSame('exception', $p->id());
        $this->assertSame(500, $p->priority());
        $this->assertSame('—', $p->badge($this->profCol(['exception' => ['count' => 0]])));
        $this->assertSame('2', $p->badge($this->profCol(['exception' => ['count' => 2]])));
    }

    #[Test]
    public function exception_severity(): void
    {
        $p = new ExceptionPanel();
        $this->assertSame('error', $p->badgeSeverity($this->profCol(['exception' => ['count' => 1]])));
        $this->assertSame('ok', $p->badgeSeverity($this->profCol(['exception' => ['count' => 0]])));
    }

    #[Test]
    public function exception_render_empty(): void
    {
        $this->assertStringContainsString('No exceptions', (new ExceptionPanel())->render($this->prof()));
    }

    #[Test]
    public function exception_render_with_data(): void
    {
        $profile = $this->profCol(['exception' => [
            'count' => 1,
            'exceptions' => [[
                'class' => 'RuntimeException', 'message' => 'boom', 'file' => '/f.php', 'line' => 10,
                'fingerprint' => 'x', 'trace' => [['class' => 'C', 'function' => 'f', 'file' => '/f.php', 'line' => 10]],
                'previous' => ['class' => 'LogicException', 'message' => 'root', 'file' => '/g.php', 'line' => 5],
            ]],
        ]]);
        $html = (new ExceptionPanel())->render($profile);
        $this->assertStringContainsString('RuntimeException', $html);
        $this->assertStringContainsString('Caused By', $html);
    }

    // ── Helpers ─────────────────

    private function prof(float $ms = 42.0, int $status = 200): Profile
    {
        $ctx = ProfileContext::create('test', true);
        return Profile::fromContext($ctx, $ctx->startedAt + $ms, [], method: 'GET', uri: '/test', statusCode: $status);
    }

    /** @param array<string, array<string, mixed>> $c */
    private function profCol(array $c): Profile
    {
        $ctx = ProfileContext::create('test', true);
        return Profile::fromContext($ctx, $ctx->startedAt + 42.0, $c, method: 'GET', uri: '/test', statusCode: 200);
    }
}
