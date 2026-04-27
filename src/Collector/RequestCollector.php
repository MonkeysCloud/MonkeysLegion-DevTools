<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Collector;

use MonkeysLegion\DevTools\Contract\CollectorInterface;
use MonkeysLegion\DevTools\Profiler\ProfileContext;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Captures the full HTTP request/response lifecycle.
 * Beyond Symfony/Laravel: includes IP hashing, content-type analysis,
 * response timing breakdown, and request fingerprinting.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class RequestCollector implements CollectorInterface
{
    // ── State ───────────────────────────────────────────────────

    private ?ServerRequestInterface $request = null;
    private ?ResponseInterface $response = null;
    private float $startTime = 0.0;
    private float $endTime = 0.0;
    private int $memoryBefore = 0;

    /**
     * Captured request method — available after setRequest().
     */
    public string $capturedMethod {
        get => $this->request?->getMethod() ?? '';
    }

    /**
     * Captured request URI — available after setRequest().
     */
    public string $capturedUri {
        get => (string) ($this->request?->getUri() ?? '');
    }

    /**
     * Response status code — available after setResponse().
     */
    public int $capturedStatusCode {
        get => $this->response?->getStatusCode() ?? 0;
    }

    public function __construct(
        private readonly bool $enabled = true,
        private readonly bool $captureHeaders = true,
        private readonly bool $captureQueryParams = true,
    ) {}

    public function name(): string
    {
        return 'request';
    }

    public function label(): string
    {
        return 'Request';
    }

    public function icon(): string
    {
        return '🌐';
    }

    public function priority(): int
    {
        return 1000;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function start(ProfileContext $context): void
    {
        $this->startTime = hrtime(true) / 1e6;
        $this->memoryBefore = memory_get_usage(true);
    }

    public function stop(ProfileContext $context): void
    {
        $this->endTime = hrtime(true) / 1e6;
    }

    /**
     * Inject the PSR-7 request for data collection.
     */
    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Inject the PSR-7 response for data collection.
     */
    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;
    }

    public function collect(ProfileContext $context): array
    {
        $data = [
            'request_id'    => $context->id,
            'method'        => $this->capturedMethod,
            'uri'           => $this->capturedUri,
            'status_code'   => $this->capturedStatusCode,
            'duration_ms'   => round($this->endTime - $this->startTime, 3),
            'memory_before' => $this->memoryBefore,
            'memory_after'  => memory_get_usage(true),
            'memory_peak'   => memory_get_peak_usage(true),
        ];

        if ($this->request !== null) {
            $data['protocol'] = $this->request->getProtocolVersion();
            $data['content_type'] = $this->request->getHeaderLine('Content-Type') ?: null;
            $data['content_length'] = (int) ($this->request->getHeaderLine('Content-Length') ?: 0);
            $data['host'] = $this->request->getUri()->getHost();
            $data['scheme'] = $this->request->getUri()->getScheme();
            $data['path'] = $this->request->getUri()->getPath();

            // IP hashing for privacy
            $serverParams = $this->request->getServerParams();
            $ip = (string) ($serverParams['REMOTE_ADDR'] ?? '');
            $data['ip_hash'] = $ip !== '' ? hash('sha256', $ip) : null;

            // User agent (first 200 chars)
            $ua = $this->request->getHeaderLine('User-Agent');
            $data['user_agent'] = $ua !== '' ? substr($ua, 0, 200) : null;

            if ($this->captureHeaders) {
                $data['request_headers'] = $this->flattenHeaders($this->request->getHeaders());
            }

            if ($this->captureQueryParams) {
                $data['query_params'] = $this->request->getQueryParams();
            }
        }

        if ($this->response !== null) {
            $data['response_size'] = $this->response->getBody()->getSize() ?? 0;
            $data['response_content_type'] = $this->response->getHeaderLine('Content-Type') ?: null;

            if ($this->captureHeaders) {
                $data['response_headers'] = $this->flattenHeaders($this->response->getHeaders());
            }
        }

        // Request fingerprint for deduplication and grouping
        $data['fingerprint'] = hash('xxh3', "{$data['method']}:{$data['path']}:{$data['status_code']}");

        return $data;
    }

    // ── Private ─────────────────────────────────────────────────

    /**
     * @param array<string, list<string>> $headers
     *
     * @return array<string, string>
     */
    private function flattenHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $values) {
            $flat[strtolower($name)] = implode(', ', $values);
        }

        return $flat;
    }
}
