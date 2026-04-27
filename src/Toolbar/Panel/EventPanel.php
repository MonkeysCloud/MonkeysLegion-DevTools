<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Events panel — event timeline, slow listeners, storm alerts.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class EventPanel extends AbstractPanel
{
    public function id(): string
    {
        return 'event';
    }

    public function label(): string
    {
        return 'Events';
    }

    public function icon(): string
    {
        return '📡';
    }

    public function priority(): int
    {
        return 600;
    }

    public function badge(Profile $profile): string
    {
        $data = $profile->collector('event');

        return (string) ($data['event_count'] ?? 0);
    }

    public function badgeSeverity(Profile $profile): string
    {
        $data = $profile->collector('event');

        if (($data['has_storm'] ?? false)) {
            return 'error';
        }

        if (($data['failed_listener_count'] ?? 0) > 0) {
            return 'warning';
        }

        return 'ok';
    }

    public function render(Profile $profile): string
    {
        $data = $profile->collector('event');
        if ($data === []) {
            return '<p class="ml-dt-empty">No event data collected.</p>';
        }

        $html = '<div class="ml-dt-metrics">';
        $html .= $this->renderBadge('Events', $data['event_count'] ?? 0);
        $html .= $this->renderBadge('Listeners', $data['listener_count'] ?? 0);
        $html .= $this->renderBadge('Total', ($data['total_listener_ms'] ?? 0) . 'ms');
        $html .= $this->renderBadge(
            'Failed',
            $data['failed_listener_count'] ?? 0,
            ($data['failed_listener_count'] ?? 0) > 0 ? 'error' : 'ok',
        );
        $html .= $this->renderBadge(
            'Storm',
            ($data['has_storm'] ?? false) ? 'DETECTED' : 'None',
            ($data['has_storm'] ?? false) ? 'error' : 'ok',
        );
        $html .= '</div>';

        // Storm warnings
        $storms = $data['storms'] ?? [];
        if ($storms !== []) {
            $rows = [];
            foreach ($storms as $s) {
                $rows[] = ['🌪️ ' . $s['event'], (string) $s['count']];
            }
            $html .= $this->renderGrid(['Event', 'Dispatches'], $rows, 'Event Storms');
        }

        // Timeline
        $timeline = $data['timeline'] ?? [];
        if ($timeline !== []) {
            $rows = [];
            foreach ($timeline as $t) {
                $rows[] = [
                    sprintf('+%.1fms', $t['relative_ms']),
                    $t['event'],
                    (string) $t['listener_count'],
                ];
            }
            $html .= $this->renderGrid(['Time', 'Event', 'Listeners'], $rows, 'Timeline');
        }

        return $html;
    }
}
