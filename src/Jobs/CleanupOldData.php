<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Jobs;

use ArtisanPackUI\Analytics\Models\Aggregate;
use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to clean up old analytics data.
 *
 * This job handles the deletion of old analytics data based on
 * the configured retention period, maintaining GDPR compliance.
 *
 * @since   1.0.0
 */
class CleanupOldData implements ShouldQueue
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
	public int $tries = 1;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public int $timeout = 3600; // 1 hour

	/**
	 * Create a new job instance.
	 *
	 * @param int|null $siteId   Optional site ID to scope cleanup.
	 * @param int|string|null $tenantId Optional tenant ID to scope cleanup.
	 *
	 * @since 1.0.0
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
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function handle(): void
	{
		$retentionDays = (int) config( 'artisanpack.analytics.retention.period' );

		if ( 0 === $retentionDays ) {
			Log::info( __( '[Analytics] Data retention disabled, skipping cleanup.' ) );

			return;
		}

		$cutoff = now()->subDays( $retentionDays );

		Log::info( __( '[Analytics] Starting data cleanup for data older than :date', [
			'date' => $cutoff->toDateTimeString(),
		] ) );

		// Delete in order of dependencies (children before parents)
		$this->deleteOldConversions( $cutoff );
		$this->deleteOldEvents( $cutoff );
		$this->deleteOldPageViews( $cutoff );
		$this->deleteOldSessions( $cutoff );
		$this->deleteExpiredConsents();
		$this->deleteOrphanedVisitors( $cutoff );
		$this->deleteOldAggregates();

		Log::info( __( '[Analytics] Data cleanup completed.' ) );
	}

	/**
	 * Delete old page views.
	 *
	 * @param Carbon $cutoff The cutoff date.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function deleteOldPageViews( Carbon $cutoff ): void
	{
		$query = PageView::query()->where( 'created_at', '<', $cutoff );

		if ( null !== $this->siteId ) {
			$query->where( 'site_id', $this->siteId );
		}

		if ( null !== $this->tenantId ) {
			$query->where( 'tenant_id', $this->tenantId );
		}

		$count = $query->delete();

		Log::info( __( '[Analytics] Deleted :count old page views.', [ 'count' => $count ] ) );
	}

	/**
	 * Delete old events.
	 *
	 * @param Carbon $cutoff The cutoff date.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function deleteOldEvents( Carbon $cutoff ): void
	{
		$query = Event::query()->where( 'created_at', '<', $cutoff );

		if ( null !== $this->siteId ) {
			$query->where( 'site_id', $this->siteId );
		}

		if ( null !== $this->tenantId ) {
			$query->where( 'tenant_id', $this->tenantId );
		}

		$count = $query->delete();

		Log::info( __( '[Analytics] Deleted :count old events.', [ 'count' => $count ] ) );
	}

	/**
	 * Delete old conversions.
	 *
	 * @param Carbon $cutoff The cutoff date.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function deleteOldConversions( Carbon $cutoff ): void
	{
		$query = Conversion::query()->where( 'created_at', '<', $cutoff );

		if ( null !== $this->siteId ) {
			$query->where( 'site_id', $this->siteId );
		}

		if ( null !== $this->tenantId ) {
			$query->where( 'tenant_id', $this->tenantId );
		}

		$count = $query->delete();

		Log::info( __( '[Analytics] Deleted :count old conversions.', [ 'count' => $count ] ) );
	}

	/**
	 * Delete old sessions.
	 *
	 * @param Carbon $cutoff The cutoff date.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function deleteOldSessions( Carbon $cutoff ): void
	{
		$query = Session::query()->where( 'started_at', '<', $cutoff );

		if ( null !== $this->siteId ) {
			$query->where( 'site_id', $this->siteId );
		}

		if ( null !== $this->tenantId ) {
			$query->where( 'tenant_id', $this->tenantId );
		}

		$count = $query->delete();

		Log::info( __( '[Analytics] Deleted :count old sessions.', [ 'count' => $count ] ) );
	}

	/**
	 * Delete orphaned visitors with no recent activity.
	 *
	 * @param Carbon $cutoff The cutoff date.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function deleteOrphanedVisitors( Carbon $cutoff ): void
	{
		$query = Visitor::query()
			->where( 'last_seen_at', '<', $cutoff )
			->whereDoesntHave( 'sessions', fn ( $q ) => $q->where( 'started_at', '>=', $cutoff ) );

		if ( null !== $this->siteId ) {
			$query->where( 'site_id', $this->siteId );
		}

		if ( null !== $this->tenantId ) {
			$query->where( 'tenant_id', $this->tenantId );
		}

		$count = $query->delete();

		Log::info( __( '[Analytics] Deleted :count orphaned visitors.', [ 'count' => $count ] ) );
	}

	/**
	 * Delete old aggregates based on aggregate retention period.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function deleteOldAggregates(): void
	{
		$aggregateRetention = (int) config( 'artisanpack.analytics.retention.aggregation_retention' );

		if ( 0 === $aggregateRetention ) {
			return;
		}

		$cutoff = now()->subDays( $aggregateRetention );

		$query = Aggregate::query()->where( 'date', '<', $cutoff );

		if ( null !== $this->siteId ) {
			$query->where( 'site_id', $this->siteId );
		}

		if ( null !== $this->tenantId ) {
			$query->where( 'tenant_id', $this->tenantId );
		}

		$count = $query->delete();

		Log::info( __( '[Analytics] Deleted :count old aggregates.', [ 'count' => $count ] ) );
	}

	/**
	 * Delete expired consents.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function deleteExpiredConsents(): void
	{
		$query = Consent::query()
			->whereNotNull( 'expires_at' )
			->where( 'expires_at', '<', now() );

		if ( null !== $this->siteId ) {
			$query->where( 'site_id', $this->siteId );
		}

		if ( null !== $this->tenantId ) {
			$query->where( 'tenant_id', $this->tenantId );
		}

		$count = $query->delete();

		Log::info( __( '[Analytics] Deleted :count expired consents.', [ 'count' => $count ] ) );
	}
}
