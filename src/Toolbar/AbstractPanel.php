<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar;

use MonkeysLegion\DevTools\Profiler\Profile;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Abstract base for toolbar panels with common rendering helpers.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
abstract class AbstractPanel implements PanelInterface
{
    public function priority(): int
    {
        return 100;
    }

    public function badgeSeverity(Profile $profile): string
    {
        return 'ok';
    }

    // ── Rendering Helpers ───────────────────────────────────────

    /**
     * Render a key-value table.
     *
     * @param array<string, string|int|float|bool|null> $rows
     */
    protected function renderTable(array $rows, string $label = ''): string
    {
        $html = '';
        if ($label !== '') {
            $html .= "<h4 class=\"ml-dt-section-title\">{$this->e($label)}</h4>";
        }

        $html .= '<table class="ml-dt-table"><tbody>';

        foreach ($rows as $key => $value) {
            $formatted = $this->formatValue($value);
            $html .= "<tr><td class=\"ml-dt-key\">{$this->e($key)}</td><td class=\"ml-dt-value\">{$formatted}</td></tr>";
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Render a data grid table.
     *
     * @param list<string>                       $headers
     * @param list<list<string|int|float|null>>   $rows
     */
    protected function renderGrid(array $headers, array $rows, string $label = ''): string
    {
        $html = '';
        if ($label !== '') {
            $html .= "<h4 class=\"ml-dt-section-title\">{$this->e($label)}</h4>";
        }

        $html .= '<div class="ml-dt-grid-wrap"><table class="ml-dt-grid"><thead><tr>';

        foreach ($headers as $h) {
            $html .= "<th>{$this->e($h)}</th>";
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= "<td>{$this->formatValue($cell)}</td>";
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Render a metric badge inline.
     */
    protected function renderBadge(string $label, string|int|float $value, string $severity = 'ok'): string
    {
        return "<span class=\"ml-dt-badge ml-dt-badge--{$severity}\">"
            . "<span class=\"ml-dt-badge-label\">{$this->e($label)}</span>"
            . "<span class=\"ml-dt-badge-value\">{$this->e((string) $value)}</span>"
            . '</span>';
    }

    /**
     * Render a status indicator.
     */
    protected function renderStatus(bool $ok, string $label): string
    {
        $icon = $ok ? '✓' : '✗';
        $cls = $ok ? 'ml-dt-status--ok' : 'ml-dt-status--error';

        return "<span class=\"ml-dt-status {$cls}\">{$icon} {$this->e($label)}</span>";
    }

    /**
     * Wrap content in a panel section.
     */
    protected function section(string $title, string $content): string
    {
        return "<div class=\"ml-dt-section\">"
            . "<h3 class=\"ml-dt-section-heading\">{$this->e($title)}</h3>"
            . "<div class=\"ml-dt-section-body\">{$content}</div>"
            . '</div>';
    }

    /**
     * HTML-escape a string.
     */
    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Format a value for display.
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '<span class="ml-dt-null">null</span>';
        }

        if (is_bool($value)) {
            $cls = $value ? 'ml-dt-bool--true' : 'ml-dt-bool--false';
            $text = $value ? 'true' : 'false';

            return "<span class=\"{$cls}\">{$text}</span>";
        }

        if (is_float($value)) {
            return $this->e(sprintf('%.3f', $value));
        }

        return $this->e((string) $value);
    }
}
