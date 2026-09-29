<?php
declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar\Panel;

use MonkeysLegion\DevTools\Profiler\Profile;
use MonkeysLegion\DevTools\Toolbar\AbstractPanel;

/**
 * Request panel — HTTP request details.
 *
 * Shows: method, URI, headers, query params, body, and uploaded files.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RequestPanel extends AbstractPanel
{
    /** @var list<string> Header keys to redact (case-insensitive). */
    private const array REDACT_HEADERS = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-token'];

    public function id(): string
    {
        return 'request';
    }

    public function label(): string
    {
        return 'Request';
    }

    public function icon(): string
    {
        return '📦';
    }

    public function priority(): int
    {
        return 750;
    }

    public function badge(Profile $profile): string
    {
        return $profile->method;
    }

    public function badgeSeverity(Profile $profile): string
    {
        return 'ok';
    }

    public function render(Profile $profile): string
    {
        $data = $profile->collector('request');

        if ($data === []) {
            // Fall back to profile metadata
            $html = $this->section('Request', $this->renderTable([
                'Method' => $profile->method,
                'URI'    => $profile->uri,
            ]));
            return $html;
        }

        $html = '';

        // Basic info
        $html .= $this->section('Overview', $this->renderTable([
            'Method'        => $data['method'] ?? $profile->method,
            'URI'           => $data['uri'] ?? $profile->uri,
            'Content Type'  => $data['content_type'] ?? '—',
            'Content Length'=> $data['content_length'] ?? '—',
        ]));

        // Query parameters
        $query = $data['query'] ?? [];
        if ($query !== []) {
            $html .= $this->section('Query Parameters', $this->renderTable($query));
        }

        // Request body
        $body = $data['body'] ?? null;
        if ($body !== null && $body !== '') {
            $html .= $this->section('Request Body', '<pre class="ml-dt-pre">' . $this->e((string) $body) . '</pre>');
        }

        // Parsed body (JSON/form)
        $parsed = $data['parsed_body'] ?? [];
        if ($parsed !== []) {
            $html .= $this->section('Parsed Body', $this->renderTable($parsed));
        }

        // Headers (redacted)
        $headers = $data['headers'] ?? [];
        if ($headers !== []) {
            $safe = [];
            foreach ($headers as $name => $value) {
                if ($this->shouldRedactHeader($name)) {
                    $safe[$name] = '•••••••• (redacted)';
                } else {
                    $safe[$name] = $value;
                }
            }
            $html .= $this->section('Headers', $this->renderTable($safe));
        }

        // Uploaded files
        $files = $data['files'] ?? [];
        if ($files !== []) {
            $rows = [];
            foreach ($files as $file) {
                $rows[] = [
                    $file['name'] ?? '?',
                    $file['tmp_name'] ?? '?',
                    $this->formatBytes((int) ($file['size'] ?? 0)),
                    $file['error'] ?? '0',
                ];
            }
            $html .= $this->renderGrid(['Field', 'Temp Path', 'Size', 'Error'], $rows, 'Uploaded Files');
        }

        return $html;
    }

    private function shouldRedactHeader(string $name): bool
    {
        $lower = strtolower($name);
        foreach (self::REDACT_HEADERS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }
        return false;
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
