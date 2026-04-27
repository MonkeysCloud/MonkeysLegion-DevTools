<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Tests\Unit\Toolbar;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Profiler\ProfileContext;
use MonkeysLegion\DevTools\Toolbar\Panel\OverviewPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\QueryPanel;
use MonkeysLegion\DevTools\Toolbar\Panel\ExceptionPanel;
use MonkeysLegion\DevTools\Toolbar\ToolbarRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolbarRenderer::class)]
final class ToolbarRendererTest extends TestCase
{
    #[Test]
    public function renders_complete_toolbar_html(): void
    {
        $renderer = new ToolbarRenderer();
        $renderer->addPanel(new OverviewPanel());

        $profile = $this->createProfile();
        $html = $renderer->render($profile);

        $this->assertStringContainsString('ml-devtools', $html);
        $this->assertStringContainsString('ml-dt-bar', $html);
        $this->assertStringContainsString('ml-dt-body', $html);
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('<script>', $html);
    }

    #[Test]
    public function includes_profile_id_in_root(): void
    {
        $renderer = new ToolbarRenderer();
        $renderer->addPanel(new OverviewPanel());

        $profile = $this->createProfile();
        $html = $renderer->render($profile);

        $this->assertStringContainsString($profile->id, $html);
    }

    #[Test]
    public function panel_count_property_hook(): void
    {
        $renderer = new ToolbarRenderer();
        $this->assertSame(0, $renderer->panelCount);

        $renderer->addPanel(new OverviewPanel());
        $renderer->addPanel(new QueryPanel());
        $this->assertSame(2, $renderer->panelCount);
    }

    #[Test]
    public function panels_sorted_by_priority(): void
    {
        $renderer = new ToolbarRenderer();
        $renderer->addPanel(new ExceptionPanel()); // priority 500
        $renderer->addPanel(new OverviewPanel());  // priority 1000

        $profile = $this->createProfile();
        $html = $renderer->render($profile);

        // Overview (1000) should appear before Exception (500)
        $overviewPos = strpos($html, 'data-panel="overview"');
        $exceptionPos = strpos($html, 'data-panel="exception"');

        $this->assertNotFalse($overviewPos);
        $this->assertNotFalse($exceptionPos);
        $this->assertLessThan($exceptionPos, $overviewPos);
    }

    #[Test]
    public function contains_keyboard_shortcut(): void
    {
        $renderer = new ToolbarRenderer();
        $renderer->addPanel(new OverviewPanel());

        $html = $renderer->render($this->createProfile());

        $this->assertStringContainsString('Ctrl', $html);
        $this->assertStringContainsString('Shift', $html);
    }

    private function createProfile(): Profile
    {
        $ctx = ProfileContext::create('test', true);

        return Profile::fromContext(
            context: $ctx,
            endedAt: $ctx->startedAt + 42.0,
            collectorData: [],
            method: 'GET',
            uri: '/test',
            statusCode: 200,
        );
    }
}
