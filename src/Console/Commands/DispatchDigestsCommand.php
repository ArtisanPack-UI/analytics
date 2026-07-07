<?php

/**
 * DispatchDigestsCommand.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Events\GatheringDigestItems;
use ArtisanPackUI\Analytics\Jobs\SendDigestEmailJob;
use ArtisanPackUI\Analytics\Models\AnalyticsDigestPreference;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Enqueue SendDigestEmailJob for every user whose cadence window is due.
 *
 * Item gathering is delegated to the {@see GatheringDigestItems} event so
 * host apps decide what metrics to feed the AI. If no listener populates
 * items for a user, that user is skipped with a warning.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class DispatchDigestsCommand extends Command
{
	/**
	 * {@inheritDoc}
	 */
	protected $signature = 'analytics:dispatch-digests
		{--cadence= : Restrict to a single cadence (weekly|monthly)}';

	/**
	 * {@inheritDoc}
	 */
	protected $description = 'Enqueue SendDigestEmailJob for every user whose cadence window is due.';

	/**
	 * Execute the console command.
	 *
	 * @since 1.3.0
	 *
	 * @return int
	 */
	public function handle(): int
	{
		$restrictCadence = $this->option( 'cadence' );
		$now             = CarbonImmutable::now();

		$query = AnalyticsDigestPreference::query()
			->whereIn( 'cadence', AnalyticsDigestPreference::ACTIVE_CADENCES );

		if ( null !== $restrictCadence && in_array( $restrictCadence, AnalyticsDigestPreference::ACTIVE_CADENCES, true ) ) {
			$query->where( 'cadence', $restrictCadence );
		}

		$dispatched = 0;
		$skipped    = 0;

		foreach ( $query->cursor() as $preference ) {
			$windowStart = AnalyticsDigestPreference::CADENCE_MONTHLY === $preference->cadence
				? $now->startOfMonth()
				: $now->startOfWeek();

			if ( null !== $preference->last_sent_at && CarbonImmutable::instance( $preference->last_sent_at )->greaterThanOrEqualTo( $windowStart ) ) {
				continue;
			}

			$gathering = new GatheringDigestItems(
				userId: (int) $preference->user_id,
				cadence: (string) $preference->cadence,
			);
			Event::dispatch( $gathering );

			if ( [] === $gathering->items ) {
				Log::warning( sprintf(
					'analytics:dispatch-digests skipped user %d — no listener populated GatheringDigestItems::$items.',
					$preference->user_id,
				) );
				$skipped++;
				continue;
			}

			$user = $preference->user()->first();

			if ( null === $user || empty( $user->email ) ) {
				Log::warning( sprintf( 'analytics:dispatch-digests skipped user %d — user or email missing.', $preference->user_id ) );
				$skipped++;
				continue;
			}

			SendDigestEmailJob::dispatch(
				userId: (int) $preference->user_id,
				email: (string) $user->email,
				items: $gathering->items,
				periodLabel: $this->periodLabel( $preference->cadence, $windowStart ),
			);

			$dispatched++;
		}

		$this->info( sprintf( 'Dispatched %d digest jobs, skipped %d.', $dispatched, $skipped ) );

		return self::SUCCESS;
	}

	/**
	 * Human-readable label for the window that is about to be sent.
	 *
	 * @since 1.3.0
	 *
	 * @param  string           $cadence      Preference cadence.
	 * @param  CarbonImmutable  $windowStart  Start of the cadence window.
	 *
	 * @return string
	 */
	protected function periodLabel( string $cadence, CarbonImmutable $windowStart ): string
	{
		if ( AnalyticsDigestPreference::CADENCE_MONTHLY === $cadence ) {
			return $windowStart->format( 'F Y' );
		}

		return sprintf( 'Week of %s', $windowStart->format( 'M j, Y' ) );
	}
}
