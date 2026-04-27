<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Overview panel — request summary, performance at a glance.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class OverviewPanel extends AbstractPanel
{
    public function id(): string
    {
        return 'overview';
    }

    public function label(): string
    {
        return 'Overview';
    }

    public function icon(): string
    {
        return '📊';
    }

    public function priority(): int
    {
        return 1000;
    }

    public function badge(Profile $profile): string
    {
        return $profile->durationFormatted;
    }

    public function badgeSeverity(Profile $profile): string
    {
        return $profile->isSlow ? 'warning' : 'ok';
    }

    public function render(Profile $profile): string
    {
        $html = $this->section('Request', $this->renderTable([
            'Method'       => $profile->method,
            'URI'          => $profile->uri,
            'Status'       => (string) $profile->statusCode,
            'Duration'     => $profile->durationFormatted,
            'Memory Peak'  => $profile->memoryPeakFormatted,
            'Response'     => $profile->responseSize . ' bytes',
            'Environment'  => $profile->environment,
            'Profile ID'   => $profile->id,
            'Trace ID'     => $profile->traceId,
            'Time'         => $profile->createdAtFormatted,
        ]));

        // Performance alerts
        $alerts = '';
        if ($profile->isSlow) {
            $alerts .= $this->renderStatus(false, "Slow request ({$profile->durationFormatted})");
        }
        if ($profile->isError) {
            $alerts .= $this->renderStatus(false, "Error response ({$profile->statusCode})");
        }

        // Collector badges
        $badges = '';
        foreach ($profile->collectors as $name => $data) {
            $count = $this->extractCount($name, $data);
            if ($count !== null) {
                $badges .= $this->renderBadge($name, $count);
            }
        }

        if ($alerts !== '') {
            $html .= $this->section('Alerts', $alerts);
        }

        if ($badges !== '') {
            $html .= $this->section('Collectors', "<div class=\"ml-dt-badges\">{$badges}</div>");
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractCount(string $name, array $data): ?string
    {
        return match ($name) {
            'query'      => isset($data['count']) ? "{$data['count']} queries" : null,
            'cache'      => isset($data['hit_ratio_display']) ? $data['hit_ratio_display'] : null,
            'event'      => isset($data['event_count']) ? "{$data['event_count']} events" : null,
            'exception'  => isset($data['count']) && $data['count'] > 0 ? "{$data['count']} errors" : null,
            'middleware'  => isset($data['count']) ? "{$data['count']} mw" : null,
            default      => null,
        };
    }
}
