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
use ArtisanPackUI\Ai\Exceptions\FeatureError;
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

		// Fail closed: `isToggleOn()` already returns false when the feature
		// key is not registered, so a missing key must not bypass the guard.
		if ( ! $registry->isToggleOn( $key ) ) {
			return;
		}

		// Idempotency: skip if a digest for this cadence window has already
		// been sent, so retries + duplicate scheduler ticks can't re-run the
		// paid agent or re-deliver the mail.
		$windowStart = $this->windowStart( $preference->cadence );

		if ( null !== $preference->last_sent_at && CarbonImmutable::instance( $preference->last_sent_at )->greaterThanOrEqualTo( $windowStart ) ) {
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
		} catch ( FeatureError $exception ) {
			// Malformed payload — do not retry, and advance last_sent_at
			// past the current window so the next scheduled tick does not
			// re-enqueue the same bad payload.
			Log::warning( sprintf( 'Analytics digest email failed for user %d: %s', $this->userId, $exception->getMessage() ) );
			$preference->forceFill( [ 'last_sent_at' => CarbonImmutable::now() ] )->save();
			return;
		}

		Mail::to( $this->email )->send( new DigestEmailMailable(
			cadence: $preference->cadence,
			periodLabel: $this->periodLabel,
			digest: $output,
		) );

		$preference->forceFill( [ 'last_sent_at' => CarbonImmutable::now() ] )->save();
	}

	/**
	 * Start of the current cadence window (Monday 00:00 for weekly, day-1
	 * of the month for monthly). Used for idempotency comparisons only.
	 *
	 * @since 1.3.0
	 *
	 * @param  string  $cadence  Preference cadence.
	 *
	 * @return CarbonImmutable
	 */
	protected function windowStart( string $cadence ): CarbonImmutable
	{
		$now = CarbonImmutable::now();

		return AnalyticsDigestPreference::CADENCE_MONTHLY === $cadence
			? $now->startOfMonth()
			: $now->startOfWeek();
	}
}
