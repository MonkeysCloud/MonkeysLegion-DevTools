<?php
declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Toolbar\Panel;

use MonkeysLegion\DevTools\Toolbar\Panel\TimelinePanel;
use MonkeysLegion\DevTools\Toolbar\Panel\RoutesPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\RequestPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\SessionPanel;
use MonkeysLegion\DevTools\Toolbar\PanelInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that all new Phase 3 panels implement PanelInterface
 * and have correct IDs, labels, and icons.
 */
final class NewPanelsTest extends TestCase
{
    #[Test]
    public function timeline_panel_implements_interface(): void
    {
        $panel = new TimelinePanel();
        self::assertInstanceOf(PanelInterface::class, $panel);
        self::assertSame('timeline', $panel->id());
        self::assertSame('Timeline', $panel->label());
        self::assertSame('⏱️', $panel->icon());
        self::assertSame(900, $panel->priority());
    }

    #[Test]
    public function routes_panel_implements_interface(): void
    {
        $panel = new RoutesPanel();
        self::assertInstanceOf(PanelInterface::class, $panel);
        self::assertSame('routes', $panel->id());
        self::assertSame('Routes', $panel->label());
        self::assertSame('🛣️', $panel->icon());
        self::assertSame(700, $panel->priority());
    }

    #[Test]
    public function request_panel_implements_interface(): void
    {
        $panel = new RequestPanel();
        self::assertInstanceOf(PanelInterface::class, $panel);
        self::assertSame('request', $panel->id());
        self::assertSame('Request', $panel->label());
        self::assertSame('📦', $panel->icon());
        self::assertSame(750, $panel->priority());
    }

    #[Test]
    public function session_panel_implements_interface(): void
    {
        $panel = new SessionPanel();
        self::assertInstanceOf(PanelInterface::class, $panel);
        self::assertSame('session', $panel->id());
        self::assertSame('Session', $panel->label());
        self::assertSame('🔑', $panel->icon());
        self::assertSame(600, $panel->priority());
    }

    #[Test]
    public function all_panel_ids_are_unique(): void
    {
        $panels = [
            new TimelinePanel(),
            new RoutesPanel(),
            new RequestPanel(),
            new SessionPanel(),
        ];

        $ids = array_map(fn($p) => $p->id(), $panels);
        self::assertSame(count($ids), count(array_unique($ids)), 'Panel IDs must be unique');
    }

    #[Test]
    public function timeline_panel_renders_html_with_empty_profile(): void
    {
        // Build a minimal profile via fromArray
        $data = [
            'method'        => 'GET',
            'uri'           => '/test',
            'status_code'   => 200,
            'duration_ms'   => 42.5,
            'memory_start'  => 1048576,
            'memory_peak'   => 2097152,
            'response_size' => 1024,
            'started_at'    => '2026-09-28T10:00:00+00:00',
            'ended_at'      => '2026-09-28T10:00:00.042+00:00',
            'created_at'    => '2026-09-28T10:00:00.000000+00:00',
            'collectors'    => [],
        ];

        $profile = \MonkeysLegion\DevTools\Profiler\Profile::fromArray($data);
        $panel   = new TimelinePanel();

        $html = $panel->render($profile);

        // With empty collectors, the panel shows metrics badges and empty state
        self::assertStringContainsString('Total', $html);
        self::assertStringContainsString('Memory', $html);
        self::assertStringContainsString('Status', $html);
        self::assertStringContainsString('No timeline data', $html);
    }

    #[Test]
    public function routes_panel_renders_empty_state(): void
    {
        $data = [
            'method'        => 'GET',
            'uri'           => '/',
            'status_code'   => 200,
            'duration_ms'   => 10.0,
            'memory_start'  => 0,
            'memory_peak'   => 1048576,
            'response_size' => 0,
            'started_at'    => '2026-09-28T10:00:00+00:00',
            'ended_at'      => '2026-09-28T10:00:00.010+00:00',
            'created_at'    => '2026-09-28T10:00:00.000000+00:00',
            'collectors'    => [],
        ];

        $profile = \MonkeysLegion\DevTools\Profiler\Profile::fromArray($data);
        $panel   = new RoutesPanel();

        $html = $panel->render($profile);

        self::assertStringContainsString('No route data', $html);
    }

    #[Test]
    public function request_panel_renders_with_empty_collector(): void
    {
        $data = [
            'method'        => 'POST',
            'uri'           => '/api/users',
            'status_code'   => 201,
            'duration_ms'   => 50.0,
            'memory_start'  => 0,
            'memory_peak'   => 2097152,
            'response_size' => 256,
            'started_at'    => '2026-09-28T10:00:00+00:00',
            'ended_at'      => '2026-09-28T10:00:00.050+00:00',
            'created_at'    => '2026-09-28T10:00:00.000000+00:00',
            'collectors'    => [],
        ];

        $profile = \MonkeysLegion\DevTools\Profiler\Profile::fromArray($data);
        $panel   = new RequestPanel();

        $html = $panel->render($profile);

        self::assertStringContainsString('POST', $html);
        self::assertStringContainsString('/api/users', $html);
    }

    #[Test]
    public function session_panel_renders_empty_state(): void
    {
        $data = [
            'method'        => 'GET',
            'uri'           => '/',
            'status_code'   => 200,
            'duration_ms'   => 5.0,
            'memory_start'  => 0,
            'memory_peak'   => 1048576,
            'response_size' => 0,
            'started_at'    => '2026-09-28T10:00:00+00:00',
            'ended_at'      => '2026-09-28T10:00:00.005+00:00',
            'created_at'    => '2026-09-28T10:00:00.000000+00:00',
            'collectors'    => [],
        ];

        $profile = \MonkeysLegion\DevTools\Profiler\Profile::fromArray($data);
        $panel   = new SessionPanel();

        $html = $panel->render($profile);

        self::assertStringContainsString('No session data', $html);
    }
}
