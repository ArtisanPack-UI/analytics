<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

/**
 * Optional capability for providers whose tracking runs in the browser.
 *
 * A provider implementing {@see AnalyticsProviderInterface} is asked to record
 * page views and events server-side. Some providers cannot: their tracking
 * half is a vendor snippet that has to reach the page, and their server-side
 * track methods are necessarily no-ops. Those providers implement this
 * interface, and `@analyticsScripts` emits whatever they return alongside the
 * package's own tracker.
 *
 * Implementations are responsible for the safety of the markup they return.
 * The output is echoed unescaped, so anything interpolated into it — a
 * measurement ID, a config value — must be encoded by the implementation.
 *
 * @since   1.5.0
 *
 * @package ArtisanPackUI\Analytics\Contracts
 */
interface ProvidesTrackerScript
{
	/**
	 * The client-side snippet to emit for this provider.
	 *
	 * Return an empty string when the provider is not configured; callers
	 * emit the result verbatim and do not filter it.
	 *
	 * @return string
	 *
	 * @since 1.5.0
	 */
	public function trackerScript(): string;
}
