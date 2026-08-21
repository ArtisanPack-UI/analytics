<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Listeners;

use ArtisanPackUI\Analytics\Analytics;
use ArtisanPackUI\Analytics\Events\PageViewTracked;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Forwards an ingested page view to every active provider except `local`.
 *
 * The local provider has already stored the row — storing it is what
 * dispatched {@see PageViewTracked}. Re-tracking it there would dispatch
 * another storage job and loop, so `local` is skipped. Every other active
 * provider (a server-side forwarder such as the GA4 Measurement Protocol
 * adapter) receives the page view here, which is the only place the ingest
 * path ever reaches them.
 *
 * Each provider is called in isolation: a forwarder that throws is logged and
 * skipped so it can neither take down the others nor fail the caller.
 *
 * The listener is deliberately synchronous. {@see PageViewTracked} is
 * dispatched from wherever the page view was processed — inside the queued
 * `ProcessBatchTracking` job for batched beacons, on the request for a single
 * beacon — so the listener runs in that same context and forwards reliably
 * without depending on a separate queue worker for the `analytics` queue.
 *
 * @since 1.5.1
 */
class ForwardPageViewToRemoteProviders
{
    /**
     * Create a new listener instance.
     *
     * @param  Analytics  $analytics  The analytics manager resolving providers.
     *
     * @since 1.5.1
     */
    public function __construct(
        protected Analytics $analytics,
    ) {}

    /**
     * Forward the page view to every active provider except `local`.
     *
     * @param  PageViewTracked  $event  The ingested page view.
     *
     * @since 1.5.1
     */
    public function handle(PageViewTracked $event): void
    {
        foreach ($this->analytics->getActiveProviders() as $provider) {
            if ($provider->getName() === 'local') {
                continue;
            }

            try {
                $provider->trackPageView($event->data);
            } catch (Throwable $e) {
                Log::warning('Analytics page view forwarding failed', [
                    'provider' => $provider->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
