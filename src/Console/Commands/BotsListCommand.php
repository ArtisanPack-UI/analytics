<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Models\Visitor;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Command to list visitors flagged as bots.
 *
 * @since   1.2.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class BotsListCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:bots
		{--score=70 : Minimum bot score to include}
		{--limit=50 : Maximum number of visitors to list}
		{--site= : Filter by site ID}
		{--since= : Only include visitors last seen on or after this date}
		{--export= : Export results to a file format (csv)}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'List visitors flagged as bots with their confidence scores';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	public function handle(): int
	{
		$score = (int) $this->option( 'score' );
		$limit = max( 1, (int) $this->option( 'limit' ) );

		$query = Visitor::query()
			->where( 'is_bot', true )
			->where( 'bot_score', '>=', $score )
			->orderByDesc( 'bot_score' )
			->orderByDesc( 'last_seen_at' );

		$siteId = $this->option( 'site' );
		if ( null !== $siteId && '' !== $siteId ) {
			if ( false === filter_var( $siteId, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] ) ) {
				$this->error( __( 'Invalid --site value. Provide a positive integer site ID.' ) );

				return self::FAILURE;
			}

			$query->where( 'site_id', (int) $siteId );
		}

		$since = $this->option( 'since' );
		if ( null !== $since && '' !== $since ) {
			try {
				$query->where( 'last_seen_at', '>=', Carbon::parse( $since ) );
			} catch ( Exception ) {
				$this->error( __( 'Invalid --since date provided.' ) );

				return self::FAILURE;
			}
		}

		$visitors = $query->limit( $limit )->get();

		if ( $visitors->isEmpty() ) {
			$this->info( __( 'No bot visitors found matching the given criteria.' ) );

			return self::SUCCESS;
		}

		$export = $this->option( 'export' );
		if ( null !== $export && '' !== $export ) {
			return $this->export( $export, $visitors );
		}

		$this->info( sprintf( __( 'Found %d bot visitors:' ), $visitors->count() ) );
		$this->newLine();

		$this->table(
			[ __( 'Visitor ID' ), __( 'User Agent' ), __( 'Score' ), __( 'Page Views' ), __( 'First Seen' ), __( 'Last Seen' ) ],
			$visitors->map( fn ( Visitor $visitor ) => [
				$visitor->id,
				$this->truncate( $visitor->user_agent ),
				$visitor->bot_score,
				number_format( $visitor->total_pageviews ),
				$visitor->first_seen_at?->toDateTimeString() ?? '-',
				$visitor->last_seen_at?->toDateTimeString() ?? '-',
			] )->toArray(),
		);

		return self::SUCCESS;
	}

	/**
	 * Export the given visitors to a file in the requested format.
	 *
	 * @param string                                                          $format   The export format.
	 * @param \Illuminate\Database\Eloquent\Collection<int, Visitor> $visitors The bot visitors to export.
	 *
	 * @return int
	 *
	 * @since 1.2.0
	 */
	protected function export( string $format, $visitors ): int
	{
		if ( 'csv' !== strtolower( $format ) ) {
			$this->error( sprintf( __( 'Unsupported export format "%s". Supported formats: csv.' ), $format ) );

			return self::FAILURE;
		}

		$directory = storage_path( 'app' );
		File::ensureDirectoryExists( $directory );

		$path   = $directory . '/analytics-bots-' . now()->format( 'Ymd_His' ) . '.csv';
		$handle = fopen( $path, 'w' );

		if ( false === $handle ) {
			$this->error( __( 'Unable to open the export file for writing.' ) );

			return self::FAILURE;
		}

		fputcsv( $handle, [ 'visitor_id', 'user_agent', 'bot_score', 'page_views', 'first_seen_at', 'last_seen_at' ] );

		foreach ( $visitors as $visitor ) {
			fputcsv( $handle, [
				$visitor->id,
				$visitor->user_agent,
				$visitor->bot_score,
				$visitor->total_pageviews,
				$visitor->first_seen_at?->toDateTimeString(),
				$visitor->last_seen_at?->toDateTimeString(),
			] );
		}

		fclose( $handle );

		$this->info( sprintf( __( 'Exported %d bot visitors to: %s' ), $visitors->count(), $path ) );

		return self::SUCCESS;
	}

	/**
	 * Truncate a user agent string for table display.
	 *
	 * @param string|null $userAgent The user agent to truncate.
	 *
	 * @return string
	 *
	 * @since 1.2.0
	 */
	protected function truncate( ?string $userAgent ): string
	{
		if ( null === $userAgent || '' === $userAgent ) {
			return '-';
		}

		return strlen( $userAgent ) > 50 ? substr( $userAgent, 0, 47 ) . '...' : $userAgent;
	}
}
