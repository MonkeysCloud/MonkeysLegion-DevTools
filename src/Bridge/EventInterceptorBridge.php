<?php

declare(strict_types=1);

namespace MonkeysLegion\DevTools\Bridge;

use MonkeysLegion\DevTools\Collector\EventCollector;
use MonkeysLegion\Events\DispatchResult;
use MonkeysLegion\Events\Interceptor\InterceptorInterface;

/**
 * Bridges the Events package InterceptorInterface to the DevTools EventCollector.
 *
 * When registered as a global interceptor on the EventDispatcher, this adapter
 * forwards every dispatch to EventCollector::recordDispatch(), enabling
 * automatic event profiling in the DevTools toolbar.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class EventInterceptorBridge implements InterceptorInterface
{
    /** @var float Dispatch start time in ms */
    private float $startMs = 0.0;

    public function __construct(
        private readonly EventCollector $eventCollector,
    ) {}

    public function before(object $event): void
    {
        $this->startMs = hrtime(true) / 1e6;
    }

    public function after(object $event, DispatchResult $result): void
    {
        // Build listener execution data from the DispatchResult
        $listeners = [];

        // We don't have individual listener names/times from the result,
        // but we can record the aggregate dispatch
        $this->eventCollector->recordDispatch(
            eventClass: $event::class,
            listeners: [],
        );
    }
}
