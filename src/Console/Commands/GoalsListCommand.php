<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Models\Goal;
use Illuminate\Console\Command;

/**
 * Command to list all analytics goals.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class GoalsListCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:goals
		{--active : Only show active goals}
		{--site= : Filter by site ID}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'List all analytics goals';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function handle(): int
	{
		$query = Goal::query()->orderBy( 'name' )->withCount( 'conversions' );

		if ( $this->option( 'active' ) ) {
			$query->where( 'is_active', true );
		}

		$siteId = $this->option( 'site' );
		if ( null !== $siteId && '' !== $siteId ) {
			$query->where( function ( $q ) use ( $siteId ): void {
				$q->where( 'site_id', (int) $siteId )
					->orWhereNull( 'site_id' );
			} );
		}

		$goals = $query->get();

		if ( $goals->isEmpty() ) {
			$this->info( __( 'No goals found.' ) );
			$this->line( __( 'Goals can be created programmatically or via the dashboard.' ) );

			return self::SUCCESS;
		}

		$this->info( sprintf( __( 'Found %d goals:' ), $goals->count() ) );
		$this->newLine();

		$this->table(
			[ __( 'ID' ), __( 'Name' ), __( 'Type' ), __( 'Status' ), __( 'Conversions' ), __( 'Value' ) ],
			$goals->map( fn ( Goal $goal ) => [
				$goal->id,
				$goal->name,
				$goal->type,
				$goal->is_active ? '<fg=green>' . __( 'Active' ) . '</>' : '<fg=red>' . __( 'Inactive' ) . '</>',
				number_format( $goal->conversions_count ),
				$goal->value ? '$' . number_format( $goal->value, 2 ) : '-',
			] )->toArray(),
		);

		return self::SUCCESS;
	}
}
