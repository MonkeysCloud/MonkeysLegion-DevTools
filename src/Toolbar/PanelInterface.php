<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar;

use MonkeysLegion\DevTools\Profiler\Profile;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Contract for toolbar panels.
 * Each panel renders a tab badge and a detail panel.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
interface PanelInterface
{
    /**
     * Unique panel identifier.
     */
    public function id(): string;

    /**
     * Display label for the panel tab.
     */
    public function label(): string;

    /**
     * Icon for the tab (emoji or SVG identifier).
     */
    public function icon(): string;

    /**
     * Short badge text for the tab (e.g., "42ms", "3 queries").
     */
    public function badge(Profile $profile): string;

    /**
     * Badge severity class: 'ok', 'warning', 'error'.
     */
    public function badgeSeverity(Profile $profile): string;

    /**
     * Render the panel detail HTML.
     */
    public function render(Profile $profile): string;

    /**
     * Panel display priority (higher = further left in toolbar).
     */
    public function priority(): int;
}
