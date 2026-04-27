<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Query panel — SQL queries, timing, duplicates, N+1 detection.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class QueryPanel extends AbstractPanel
{
    public function id(): string
    {
        return 'query';
    }

    public function label(): string
    {
        return 'Queries';
    }

    public function icon(): string
    {
        return '🗄️';
    }

    public function priority(): int
    {
        return 800;
    }

    public function badge(Profile $profile): string
    {
        $data = $profile->collector('query');
        $count = (int) ($data['count'] ?? 0);
        $ms = (float) ($data['total_duration_ms'] ?? 0);

        return "{$count} / {$ms}ms";
    }

    public function badgeSeverity(Profile $profile): string
    {
        $data = $profile->collector('query');

        if (($data['has_n_plus_one'] ?? false)) {
            return 'error';
        }

        if (($data['duplicate_count'] ?? 0) > 0 || ($data['slow_count'] ?? 0) > 0) {
            return 'warning';
        }

        return 'ok';
    }

    public function render(Profile $profile): string
    {
        $data = $profile->collector('query');
        if ($data === []) {
            return '<p class="ml-dt-empty">No query data collected.</p>';
        }

        $html = '';

        // Summary metrics
        $html .= '<div class="ml-dt-metrics">';
        $html .= $this->renderBadge('Queries', $data['count'] ?? 0);
        $html .= $this->renderBadge('Total', ($data['total_duration_ms'] ?? 0) . 'ms');
        $html .= $this->renderBadge(
            'Duplicates',
            $data['duplicate_count'] ?? 0,
            ($data['duplicate_count'] ?? 0) > 0 ? 'warning' : 'ok',
        );
        $html .= $this->renderBadge(
            'N+1',
            ($data['has_n_plus_one'] ?? false) ? 'DETECTED' : 'None',
            ($data['has_n_plus_one'] ?? false) ? 'error' : 'ok',
        );
        $html .= '</div>';

        // Duplicate warnings
        $duplicates = $data['duplicates'] ?? [];
        if ($duplicates !== []) {
            $rows = [];
            foreach ($duplicates as $dup) {
                $severity = ($dup['is_n_plus_one'] ?? false) ? '🔴 N+1' : '🟡 Duplicate';
                $sql = strlen($dup['sql_sample']) > 80
                    ? substr($dup['sql_sample'], 0, 77) . '...'
                    : $dup['sql_sample'];
                $rows[] = [$severity, (string) $dup['count'], $sql];
            }
            $html .= $this->renderGrid(['Severity', 'Count', 'SQL Pattern'], $rows, 'Duplicates & N+1');
        }

        // Query list
        $queries = $data['queries'] ?? [];
        if ($queries !== []) {
            $rows = [];
            foreach ($queries as $q) {
                $flags = '';
                if ($q['is_slow'] ?? false) {
                    $flags .= '🐌 ';
                }
                if ($q['is_duplicate'] ?? false) {
                    $flags .= '♻️ ';
                }

                $sql = strlen($q['sql']) > 100
                    ? substr($q['sql'], 0, 97) . '...'
                    : $q['sql'];

                $rows[] = [
                    (string) $q['index'],
                    $flags . $sql,
                    (string) $q['duration_ms'] . 'ms',
                    $q['connection'],
                    $q['source'],
                ];
            }

            $html .= $this->renderGrid(
                ['#', 'SQL', 'Duration', 'Connection', 'Source'],
                $rows,
                'All Queries',
            );
        }

        return $html;
    }
}
