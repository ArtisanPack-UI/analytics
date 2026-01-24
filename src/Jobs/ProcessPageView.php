<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Jobs;

use ArtisanPackUI\Analytics\Data\PageViewData;
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
	 * @param PageViewData $data The page view data to process.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public PageViewData $data,
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
