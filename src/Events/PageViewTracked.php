<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Events;

use ArtisanPackUI\Analytics\Analytics;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Listeners\ForwardPageViewToRemoteProviders;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a page view is ingested from the JavaScript tracker.
 *
 * Dispatched from the ingest pipeline only — never from the
 * {@see Analytics::trackPageView()} facade, which
 * already fans out to every active provider itself. Before this event existed
 * an ingested page view reached only the local provider, so server-side
 * forwarders such as the GA4 Measurement Protocol provider — listed in
 * `active_providers` but never called on the ingest path — silently received
 * nothing. {@see ForwardPageViewToRemoteProviders}
 * closes that gap by forwarding this event to every active provider except
 * `local`.
 *
 * Carries the enriched {@see PageViewData} rather than the stored model so the
 * listener can hand each provider the same value object the facade fan-out
 * would, and so the event serializes cleanly onto the queue.
 *
 * @since 1.5.1
 */
class PageViewTracked
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  PageViewData  $data  The enriched page view data that was stored.
     * @param  int|null  $siteId  The resolved site the page view belongs to.
     *
     * @since 1.5.1
     */
    public function __construct(
        public PageViewData $data,
        public ?int $siteId = null,
    ) {}
}
