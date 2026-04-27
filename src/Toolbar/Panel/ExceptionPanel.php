<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Exception panel — error display with trace and chain.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ExceptionPanel extends AbstractPanel
{
    public function id(): string
    {
        return 'exception';
    }

    public function label(): string
    {
        return 'Exceptions';
    }

    public function icon(): string
    {
        return '💥';
    }

    public function priority(): int
    {
        return 500;
    }

    public function badge(Profile $profile): string
    {
        $data = $profile->collector('exception');
        $count = (int) ($data['count'] ?? 0);

        return $count > 0 ? (string) $count : '—';
    }

    public function badgeSeverity(Profile $profile): string
    {
        $data = $profile->collector('exception');

        return ($data['count'] ?? 0) > 0 ? 'error' : 'ok';
    }

    public function render(Profile $profile): string
    {
        $data = $profile->collector('exception');
        if ($data === [] || ($data['count'] ?? 0) === 0) {
            return '<p class="ml-dt-empty">No exceptions captured.</p>';
        }

        $html = '<div class="ml-dt-metrics">';
        $html .= $this->renderBadge('Exceptions', $data['count'] ?? 0, 'error');
        $html .= '</div>';

        $exceptions = $data['exceptions'] ?? [];
        foreach ($exceptions as $i => $ex) {
            $exClass = (string) ($ex['class'] ?? '');
            $exMessage = (string) ($ex['message'] ?? '');
            $exFile = (string) ($ex['file'] ?? '');
            $exLine = (int) ($ex['line'] ?? 0);

            $html .= '<div class="ml-dt-exception">';
            $html .= '<h4 class="ml-dt-exception-class">' . $this->e($exClass) . '</h4>';
            $html .= '<p class="ml-dt-exception-message">' . $this->e($exMessage) . '</p>';
            $html .= '<p class="ml-dt-exception-location">' . $this->e($exFile) . ':' . $exLine . '</p>';

            // Stack trace
            $trace = $ex['trace'] ?? [];
            if ($trace !== []) {
                $rows = [];
                foreach ($trace as $frame) {
                    $frameClass = (string) ($frame['class'] ?? '');
                    $frameFunction = (string) ($frame['function'] ?? '');
                    $frameFile = (string) ($frame['file'] ?? '');
                    $frameLine = (int) ($frame['line'] ?? 0);

                    $fn = $frameClass !== ''
                        ? $frameClass . '::' . $frameFunction
                        : $frameFunction;
                    $loc = $frameFile . ':' . $frameLine;
                    $rows[] = [$fn, $loc];
                }
                $html .= $this->renderGrid(['Function', 'Location'], $rows);
            }

            // Previous exception
            if (isset($ex['previous'])) {
                $prev = $ex['previous'];
                $prevFile = (string) ($prev['file'] ?? '');
                $prevLine = (int) ($prev['line'] ?? 0);

                $html .= $this->renderTable([
                    'Previous' => (string) ($prev['class'] ?? ''),
                    'Message'  => (string) ($prev['message'] ?? ''),
                    'Location' => $prevFile . ':' . $prevLine,
                ], 'Caused By');
            }

            $html .= '</div>';
        }

        return $html;
    }
}
