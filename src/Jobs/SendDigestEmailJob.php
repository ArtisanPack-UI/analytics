<?php

/**
 * SendDigestEmailJob.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Jobs;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Ai\Exceptions\FeatureDisabledException;
use ArtisanPackUI\Ai\Exceptions\MissingCredentialsException;
use ArtisanPackUI\Analytics\Ai\Agents\DigestEmailAgent;
use ArtisanPackUI\Analytics\Mail\DigestEmailMailable;
use ArtisanPackUI\Analytics\Models\AnalyticsDigestPreference;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Generate + send the AI digest email for one user, honoring their opt-in.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class SendDigestEmailJob implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param  int                           $userId      User to send to.
	 * @param  string                        $email       Destination address.
	 * @param  array<int, mixed>             $items       Digest items to summarize (metrics, top pages, etc.).
	 * @param  string                        $periodLabel Human-readable label for the window.
	 */
	public function __construct(
		public readonly int $userId,
		public readonly string $email,
		public readonly array $items,
		public readonly string $periodLabel,
	) {
	}

	/**
	 * Handle the job.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function handle(): void
	{
		/** @var AnalyticsDigestPreference|null $preference */
		$preference = AnalyticsDigestPreference::query()->where( 'user_id', $this->userId )->first();

		if ( null === $preference || ! $preference->isOptedIn() ) {
			return;
		}

		$registry = app( FeatureRegistry::class );
		$key      = 'analytics.digest_email';

		if ( null !== $registry->get( $key ) && ! $registry->isToggleOn( $key ) ) {
			return;
		}

		try {
			$output = DigestEmailAgent::for( [
				'items'  => $this->items,
				'focus'  => 'weekly' === $preference->cadence ? 'week-over-week change' : 'month-over-month change',
				'length' => 'detailed',
			] )->run();
		} catch ( FeatureDisabledException | MissingCredentialsException $exception ) {
			Log::info( sprintf( 'Analytics digest email skipped for user %d: %s', $this->userId, $exception->getMessage() ) );
			return;
		}

		Mail::to( $this->email )->send( new DigestEmailMailable(
			cadence: $preference->cadence,
			periodLabel: $this->periodLabel,
			digest: $output,
		) );

		$preference->forceFill( [ 'last_sent_at' => CarbonImmutable::now() ] )->save();
	}
}
