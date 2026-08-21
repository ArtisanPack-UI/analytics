<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Jobs;

use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Events\PageViewTracked;
use ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to process page views asynchronously.
 *
 * This job handles the actual storage of page view data,
 * allowing the tracking endpoint to respond quickly.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Jobs
 */
class ProcessPageView implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public int $tries = 3;

	/**
	 * The number of seconds to wait before retrying.
	 *
	 * @var int
	 */
	public int $backoff = 10;

	/**
	 * Create a new job instance.
	 *
	 * @param PageViewData $data               The page view data to process.
	 * @param int|null     $siteId             The resolved site the page view belongs to.
	 * @param bool         $forwardToProviders Whether to dispatch PageViewTracked
	 *                                         after storage so the ingest page view
	 *                                         reaches secondary active providers.
	 *                                         Defaults to false so the facade path
	 *                                         (LocalAnalyticsProvider::trackPageView),
	 *                                         which fans out to providers itself, does
	 *                                         not double-send.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public PageViewData $data,
		public ?int $siteId = null,
		public bool $forwardToProviders = false,
	) {
	}

	/**
	 * Execute the job.
	 *
	 * @param LocalAnalyticsProvider $provider The local analytics provider.
	 *
	 * @since 1.0.0
	 */
	public function handle( LocalAnalyticsProvider $provider ): void
	{
		$provider->storePageView( $this->data );

		// Forward only after local persistence has succeeded, so a secondary
		// provider never holds a page view the local database does not. Gated
		// on the ingest flag so the facade path, which fans out to providers on
		// its own, is not forwarded a second time here.
		if ( $this->forwardToProviders ) {
			PageViewTracked::dispatch( $this->data, $this->siteId );
		}
	}

	/**
	 * Get the tags that should be assigned to the job.
	 *
	 * @return array<int, string>
	 *
	 * @since 1.0.0
	 */
	public function tags(): array
	{
		return [
			'analytics',
			'pageview',
			'path:' . $this->data->path,
		];
	}
}
