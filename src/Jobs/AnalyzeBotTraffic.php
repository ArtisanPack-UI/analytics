<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Jobs;

use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\BotDetector;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to perform post-hoc behavioral bot analysis on recent visitors.
 *
 * Scores visitors that have not yet been evaluated using the multi-signal
 * BotDetector, then persists the resulting confidence score and bot flag to
 * the visitor record. Visitors are processed in chunks so the job scales to
 * large traffic volumes without exhausting memory.
 *
 * @since   1.2.0
 *
 * @package ArtisanPackUI\Analytics\Jobs
 */
class AnalyzeBotTraffic implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	/**
	 * The number of visitors to process per chunk.
	 *
	 * @var int
	 */
	protected const CHUNK_SIZE = 200;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public int $tries = 1;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public int $timeout = 1800; // 30 minutes

	/**
	 * Create a new job instance.
	 *
	 * @param int|null        $siteId   Optional site ID to scope analysis.
	 * @param int|string|null $tenantId Optional tenant ID to scope analysis.
	 *
	 * @since 1.2.0
	 */
	public function __construct(
		protected ?int $siteId = null,
		protected int|string|null $tenantId = null,
	) {
		$this->onQueue( config( 'artisanpack.analytics.local.queue_name', 'analytics' ) );
	}

	/**
	 * Execute the job.
	 *
	 * @param BotDetector $detector The bot detection service.
	 *
	 * @return void
	 *
	 * @since 1.2.0
	 */
	public function handle( BotDetector $detector ): void
	{
		if ( ! $detector->enabled() ) {
			Log::info( __( '[Analytics] Bot detection disabled, skipping behavioral analysis.' ) );

			return;
		}

		$window = (int) config( 'artisanpack.analytics.bot_detection.analysis_window', 60 );

		if ( $window <= 0 ) {
			Log::warning( __( '[Analytics] Bot analysis window must be a positive number of minutes, got :window. Skipping.', [
				'window' => $window,
			] ) );

			return;
		}

		$cutoff = now()->subMinutes( $window );

		Log::info( __( '[Analytics] Starting bot behavioral analysis for visitors seen since :date', [
			'date' => $cutoff->toDateTimeString(),
		] ) );

		$threshold = $detector->threshold();
		$processed = 0;
		$flagged   = 0;

		$this->unscoredVisitors( $cutoff )->chunkById(
			self::CHUNK_SIZE,
			function ( Collection $visitors ) use ( $detector, $threshold, &$processed, &$flagged ): void {
				foreach ( $visitors as $visitor ) {
					$score = $detector->score( $visitor );
					$isBot = $score >= $threshold;

					$visitor->forceFill( [
						'bot_score'       => $score,
						'is_bot'          => $isBot,
						'bot_detected_at' => now(),
					] )->save();

					$processed++;

					if ( $isBot ) {
						$flagged++;
					}
				}
			},
		);

		Log::info( __( '[Analytics] Bot behavioral analysis completed. Processed :processed visitors, flagged :flagged as bots.', [
			'processed' => $processed,
			'flagged'   => $flagged,
		] ) );
	}

	/**
	 * Build the query for unscored visitors within the analysis window.
	 *
	 * @param CarbonInterface $cutoff The earliest last-seen timestamp to consider.
	 *
	 * @return Builder<Visitor>
	 *
	 * @since 1.2.0
	 */
	protected function unscoredVisitors( CarbonInterface $cutoff ): Builder
	{
		$query = Visitor::query()
			->with( 'pageViews' )
			->whereNull( 'bot_detected_at' )
			->where( 'last_seen_at', '>=', $cutoff );

		if ( null !== $this->siteId ) {
			$query->where( 'site_id', $this->siteId );
		}

		if ( null !== $this->tenantId ) {
			$query->where( 'tenant_id', $this->tenantId );
		}

		return $query;
	}
}
