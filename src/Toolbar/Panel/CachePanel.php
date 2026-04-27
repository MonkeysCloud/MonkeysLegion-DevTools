<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Cache panel — hit/miss ratios, hot keys, per-store breakdown.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class CachePanel extends AbstractPanel
{
    public function id(): string
    {
        return 'cache';
    }

    public function label(): string
    {
        return 'Cache';
    }

    public function icon(): string
    {
        return '⚡';
    }

    public function priority(): int
    {
        return 700;
    }

    public function badge(Profile $profile): string
    {
        $data = $profile->collector('cache');

        return $data['hit_ratio_display'] ?? '—';
    }

    public function badgeSeverity(Profile $profile): string
    {
        $data = $profile->collector('cache');
        $ratio = (float) ($data['hit_ratio'] ?? 1.0);

        if ($ratio < 0.5) {
            return 'error';
        }

        if ($ratio < 0.8) {
            return 'warning';
        }

        return 'ok';
    }

    public function render(Profile $profile): string
    {
        $data = $profile->collector('cache');
        if ($data === []) {
            return '<p class="ml-dt-empty">No cache data collected.</p>';
        }

        $html = '<div class="ml-dt-metrics">';
        $html .= $this->renderBadge('Operations', $data['count'] ?? 0);
        $html .= $this->renderBadge('Hits', $data['hits'] ?? 0);
        $html .= $this->renderBadge('Misses', $data['misses'] ?? 0, ($data['misses'] ?? 0) > 0 ? 'warning' : 'ok');
        $html .= $this->renderBadge('Ratio', $data['hit_ratio_display'] ?? '—');
        $html .= $this->renderBadge('Total', ($data['total_duration_ms'] ?? 0) . 'ms');
        $html .= '</div>';

        // Per-store breakdown
        $stores = $data['stores'] ?? [];
        if ($stores !== []) {
            $rows = [];
            foreach ($stores as $name => $stats) {
                $total = $stats['hits'] + $stats['misses'];
                $ratio = $total > 0 ? sprintf('%.1f%%', ($stats['hits'] / $total) * 100) : '—';
                $rows[] = [$name, (string) $stats['hits'], (string) $stats['misses'], $ratio, sprintf('%.1fms', $stats['total_ms'])];
            }
            $html .= $this->renderGrid(['Store', 'Hits', 'Misses', 'Ratio', 'Time'], $rows, 'Per-Store');
        }

        // Hot keys
        $hotKeys = $data['hot_keys'] ?? [];
        if ($hotKeys !== []) {
            $rows = [];
            foreach ($hotKeys as $hk) {
                $rows[] = [$hk['key'], (string) $hk['count']];
            }
            $html .= $this->renderGrid(['Key', 'Access Count'], $rows, '🔥 Hot Keys');
        }

        return $html;
    }
}
