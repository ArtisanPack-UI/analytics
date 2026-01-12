<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Console\Command;

/**
 * Command to create a new analytics site.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class SiteCreateCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:site:create
		{name? : The site name}
		{--domain= : The site domain}
		{--tenant= : The tenant ID}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new analytics site';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function handle(): int
	{
		$name = $this->argument( 'name' ) ?? $this->ask( __( 'Site name' ) );

		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			$this->error( __( 'Site name is required.' ) );

			return self::FAILURE;
		}

		$domain = $this->option( 'domain' );
		if ( null === $domain ) {
			$domain = $this->ask( __( 'Site domain (optional)' ) );
		}

		$tenantId = $this->option( 'tenant' );

		// Create site without API key first
		$site = Site::create( [
			'name'      => $name,
			'domain'    => $domain && '' !== $domain ? $domain : null,
			'is_active' => true,
			'settings'  => [],
			'tenant_id' => $tenantId,
		] );

		// Generate API key using the model's secure method
		$apiKey = $site->generateApiKey();

		$this->newLine();
		$this->info( sprintf( __( 'Site "%s" created successfully!' ), $site->name ) );
		$this->newLine();

		$this->table(
			[ __( 'Property' ), __( 'Value' ) ],
			[
				[ __( 'ID' ), $site->id ],
				[ __( 'Name' ), $site->name ],
				[ __( 'Domain' ), $site->domain ?? '-' ],
				[ __( 'API Key' ), $apiKey ],
				[ __( 'Status' ), __( 'Active' ) ],
			],
		);

		$this->newLine();
		$this->warn( __( 'Important: Save the API key above. It will not be shown again.' ) );

		return self::SUCCESS;
	}
}
