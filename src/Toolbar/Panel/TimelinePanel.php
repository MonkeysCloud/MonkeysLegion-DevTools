<?php
declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Timeline panel — request lifecycle phases with timings.
 *
 * Shows: bootstrap, routing, middleware, controller, rendering, total.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class TimelinePanel extends AbstractPanel
{
    public function id(): string
    {
        return 'timeline';
    }

    public function label(): string
    {
        return 'Timeline';
    }

    public function icon(): string
    {
        return '⏱️';
    }

    public function priority(): int
    {
        return 900;
    }

    public function badge(Profile $profile): string
    {
        return $profile->durationFormatted;
    }

    public function badgeSeverity(Profile $profile): string
    {
        if ($profile->durationMs > 1000.0) {
            return 'error';
        }
        if ($profile->durationMs > 200.0) {
            return 'warning';
        }
        return 'ok';
    }

    public function render(Profile $profile): string
    {
        $html = '';

        // Summary metrics
        $html .= '<div class="ml-dt-metrics">';
        $html .= $this->renderBadge('Total', $profile->durationFormatted);
        $html .= $this->renderBadge('Memory', $this->formatBytes($profile->memoryPeak));
        $html .= $this->renderBadge('Status', (string) $profile->statusCode, $profile->statusCode >= 400 ? 'error' : 'ok');
        $html .= '</div>';

        // Timeline phases (from collectors)
        $timeline = $profile->collector('timeline');

        if ($timeline === []) {
            $html .= '<p class="ml-dt-empty">No timeline data collected.</p>';
            return $html;
        }

        $phases = $timeline['phases'] ?? [];

        if ($phases !== []) {
            $rows = [];
            $totalMs = $profile->durationMs;

            foreach ($phases as $phase) {
                $ms      = (float) ($phase['duration_ms'] ?? 0);
                $pct     = $totalMs > 0 ? ($ms / $totalMs) * 100 : 0;
                $bar     = $this->renderBar($pct);
                $rows[]  = [
                    $phase['name'] ?? 'Unknown',
                    sprintf('%.2fms', $ms),
                    sprintf('%.1f%%', $pct),
                    $bar,
                ];
            }

            $html .= $this->renderGrid(['Phase', 'Duration', '%', 'Bar'], $rows, 'Lifecycle Phases');
        }

        // Request metadata
        $html .= $this->section('Request Details', $this->renderTable([
            'Method'         => $profile->method,
            'URI'            => $profile->uri,
            'Status Code'    => $profile->statusCode,
            'Response Size'  => $this->formatBytes($profile->responseSize),
            'Started At'     => $profile->startedAt,
            'Ended At'       => $profile->endedAt,
            'Memory Start'   => $this->formatBytes($profile->memoryStart),
            'Memory Peak'    => $this->formatBytes($profile->memoryPeak),
        ]));

        return $html;
    }

    private function renderBar(float $pct): string
    {
        $width = min(100, max(2, $pct));
        $color = $pct > 50 ? 'ml-dt-bar--error' : ($pct > 25 ? 'ml-dt-bar--warning' : 'ml-dt-bar--ok');
        return '<div class="ml-dt-bar"><div class="ml-dt-bar-fill ' . $color . '" style="width:' . $width . '%"></div></div>';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1048576, 2) . ' MB';
    }
}
