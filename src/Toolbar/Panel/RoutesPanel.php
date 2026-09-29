<?php
declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Routes panel — matched route and all registered routes.
 *
 * Shows: matched route name, path, controller@method, middleware,
 * and a list of all registered routes.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RoutesPanel extends AbstractPanel
{
    public function id(): string
    {
        return 'routes';
    }

    public function label(): string
    {
        return 'Routes';
    }

    public function icon(): string
    {
        return '🛣️';
    }

    public function priority(): int
    {
        return 700;
    }

    public function badge(Profile $profile): string
    {
        $data   = $profile->collector('routes');
        $matched = $data['matched_name'] ?? $data['matched_path'] ?? '—';
        return (string) $matched;
    }

    public function badgeSeverity(Profile $profile): string
    {
        return 'ok';
    }

    public function render(Profile $profile): string
    {
        $data = $profile->collector('routes');

        if ($data === []) {
            return '<p class="ml-dt-empty">No route data collected.</p>';
        }

        $html = '';

        // Matched route
        $matched = $data['matched'] ?? [];
        if ($matched !== []) {
            $html .= $this->section('Matched Route', $this->renderTable([
                'Name'        => $matched['name'] ?? '—',
                'Method'      => $matched['method'] ?? '—',
                'Path'        => $matched['path'] ?? '—',
                'Controller'  => $matched['controller'] ?? '—',
                'Action'      => $matched['action'] ?? '—',
                'Middleware'  => implode(', ', $matched['middleware'] ?? []),
            ]));
        }

        // All registered routes
        $all = $data['all'] ?? [];
        if ($all !== []) {
            $rows = [];
            foreach ($all as $route) {
                $isMatched = ($route['path'] ?? '') === ($matched['path'] ?? null)
                    && ($route['method'] ?? '') === ($matched['method'] ?? null);
                $marker    = $isMatched ? '➤ ' : '';
                $rows[]    = [
                    $marker . ($route['method'] ?? '?'),
                    $route['path'] ?? '?',
                    $route['name'] ?? '—',
                    ($route['controller'] ?? '?') . '@' . ($route['action'] ?? '?'),
                ];
            }

            $html .= $this->renderGrid(
                ['Method', 'Path', 'Name', 'Handler'],
                $rows,
                'All Registered Routes (' . count($all) . ')',
            );
        }

        return $html;
    }
}
