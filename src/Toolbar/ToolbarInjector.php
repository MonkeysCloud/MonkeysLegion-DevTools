<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Toolbar;

use MonkeysLegion\DevTools\Profiler\Profile;

use Psr\Http\Message\ResponseInterface;

/**
 * MonkeysLegion Framework — DevTools Package
 *
 * Injects the debug toolbar HTML into HTML responses.
 * Content-type aware — only injects into text/html responses.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ToolbarInjector
{
    /**
     * Number of injections performed — tracked for diagnostics.
     */
    public private(set) int $injectionCount = 0;

    public function __construct(
        private readonly ToolbarRenderer $renderer,
        private readonly int $maxPayloadKb = 256,
    ) {}

    /**
     * Inject toolbar HTML into a PSR-7 response if applicable.
     */
    public function inject(ResponseInterface $response, Profile $profile): ResponseInterface
    {
        // Only inject into HTML responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains(strtolower($contentType), 'text/html')) {
            return $response;
        }

        // Don't inject into streaming or very large responses
        $bodySize = $response->getBody()->getSize();
        if ($bodySize !== null && $bodySize > $this->maxPayloadKb * 1024) {
            return $response;
        }

        // Don't inject if response isn't readable/seekable
        $body = $response->getBody();
        if (!$body->isReadable()) {
            return $response;
        }

        // Read the full body
        $body->rewind();
        $html = $body->getContents();

        // Render toolbar
        $toolbar = $this->renderer->render($profile);

        // Inject before </body> if present, else append
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $toolbar . '</body>', $html);
        } else {
            $html .= $toolbar;
        }

        // Create new body stream
        $newBody = $response->getBody();
        $newBody->rewind();
        $newBody->write($html);

        // Update Content-Length
        $response = $response->withHeader('Content-Length', (string) strlen($html));

        $this->injectionCount++;

        return $response;
    }

    /**
     * Check whether a response is eligible for toolbar injection.
     */
    public function isInjectable(ResponseInterface $response): bool
    {
        $contentType = $response->getHeaderLine('Content-Type');

        return str_contains(strtolower($contentType), 'text/html');
    }
}
