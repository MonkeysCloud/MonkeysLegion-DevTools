<?php
declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Session panel — session ID and all session keys/values.
 *
 * Sensitive keys (passwords, tokens, secrets) are automatically redacted.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class SessionPanel extends AbstractPanel
{
    /** @var list<string> Session keys to redact (case-insensitive). */
    private const array REDACT_KEYS = [
        'password', 'secret', 'token', 'api_key', 'csrf_token',
        '_token', 'auth', 'oauth', 'jwt',
    ];

    public function id(): string
    {
        return 'session';
    }

    public function label(): string
    {
        return 'Session';
    }

    public function icon(): string
    {
        return '🔑';
    }

    public function priority(): int
    {
        return 600;
    }

    public function badge(Profile $profile): string
    {
        $data = $profile->collector('session');
        $count = count($data['keys'] ?? []);
        return $count > 0 ? (string) $count : '—';
    }

    public function badgeSeverity(Profile $profile): string
    {
        return 'ok';
    }

    public function render(Profile $profile): string
    {
        $data = $profile->collector('session');

        if ($data === []) {
            return '<p class="ml-dt-empty">No session data (session may not be started for this request).</p>';
        }

        $html = '';

        // Session metadata
        $html .= $this->section('Session Info', $this->renderTable([
            'Session ID'   => $data['id'] ?? '—',
            'Session Name' => $data['name'] ?? '—',
            'Key Count'    => (string) count($data['keys'] ?? []),
        ]));

        // Session keys/values (redacted)
        $keys = $data['keys'] ?? [];
        if ($keys !== []) {
            $safe = [];
            foreach ($keys as $key => $value) {
                if ($this->shouldRedact($key)) {
                    $safe[$key] = '•••••••• (redacted)';
                } elseif (is_array($value)) {
                    $safe[$key] = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
                } else {
                    $safe[$key] = $value;
                }
            }
            $html .= $this->section('Session Data', $this->renderTable($safe));
        }

        // Flash data
        $flash = $data['flash'] ?? [];
        if ($flash !== []) {
            $html .= $this->section('Flash Data', $this->renderTable($flash));
        }

        return $html;
    }

    private function shouldRedact(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::REDACT_KEYS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }
        return false;
    }
}
