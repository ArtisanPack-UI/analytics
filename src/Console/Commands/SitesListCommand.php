<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Console\Command;

/**
 * Command to list all analytics sites.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class SitesListCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:sites
		{--active : Only show active sites}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'List all analytics sites';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function handle(): int
	{
		$query = Site::query()->orderBy( 'name' );

		if ( $this->option( 'active' ) ) {
			$query->where( 'is_active', true );
		}

		$sites = $query->get();

		if ( $sites->isEmpty() ) {
			$this->info( __( 'No sites found.' ) );
			$this->line( __( 'Create a site with: php artisan analytics:site:create' ) );

			return self::SUCCESS;
		}

		$this->info( sprintf( __( 'Found %d sites:' ), $sites->count() ) );
		$this->newLine();

		$this->table(
			[ __( 'ID' ), __( 'Name' ), __( 'Domain' ), __( 'Status' ), __( 'API Key' ), __( 'Created' ) ],
			$sites->map( fn ( Site $site ) => [
				$site->id,
				$site->name,
				$site->domain ?? '-',
				$site->is_active ? '<fg=green>' . __( 'Active' ) . '</>' : '<fg=red>' . __( 'Inactive' ) . '</>',
				$site->hasApiKey() ? __( 'Set' ) : '-',
				$site->created_at?->format( 'Y-m-d' ) ?? '-',
			] )->toArray(),
		);

		return self::SUCCESS;
	}
}
