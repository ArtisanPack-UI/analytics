<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Console\Command;

/**
 * Command to manage site API keys.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class SiteApiKeyCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:site:api-key
		{site : The site ID or name}
		{--rotate : Generate a new API key}
		{--show : Show API key status}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Manage site API keys';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function handle(): int
	{
		$siteIdentifier = $this->argument( 'site' );

		$site = $this->findSite( $siteIdentifier );

		if ( null === $site ) {
			$this->error( sprintf( __( 'Site "%s" not found.' ), $siteIdentifier ) );

			return self::FAILURE;
		}

		if ( $this->option( 'rotate' ) ) {
			return $this->rotateApiKey( $site );
		}

		if ( $this->option( 'show' ) ) {
			return $this->showApiKey( $site );
		}

		// Default: show API key status
		$this->info( sprintf( __( 'Site: %s' ), $site->name ) );
		$this->line( sprintf( __( 'API Key: %s' ), $site->hasApiKey() ? __( 'Set (hash stored)' ) : __( 'Not set' ) ) );
		$this->newLine();
		$this->line( __( 'Use --show to view API key status and last used time' ) );
		$this->line( __( 'Use --rotate to generate a new API key' ) );

		return self::SUCCESS;
	}

	/**
	 * Find a site by ID or name.
	 *
	 * @param string $identifier The site ID or name.
	 *
	 * @return Site|null
	 *
	 * @since 1.0.0
	 */
	protected function findSite( string $identifier ): ?Site
	{
		if ( is_numeric( $identifier ) ) {
			return Site::find( (int) $identifier );
		}

		return Site::where( 'name', $identifier )->first();
	}

	/**
	 * Rotate the site's API key.
	 *
	 * @param Site $site The site.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	protected function rotateApiKey( Site $site ): int
	{
		if ( ! $this->confirm( sprintf( __( 'This will invalidate the current API key for "%s". Continue?' ), $site->name ) ) ) {
			$this->info( __( 'API key rotation cancelled.' ) );

			return self::SUCCESS;
		}

		// Use the model's secure key rotation method
		$newApiKey = $site->rotateApiKey();

		$this->newLine();
		$this->info( __( 'API key rotated successfully!' ) );
		$this->newLine();
		$this->line( sprintf( __( 'New API Key: %s' ), $newApiKey ) );
		$this->newLine();
		$this->warn( __( 'Important: Save the new API key above. It will not be shown again.' ) );

		return self::SUCCESS;
	}

	/**
	 * Show the site's API key status.
	 *
	 * Note: API keys are stored as SHA-256 hashes and cannot be retrieved.
	 * Only the hash status and last usage time are available.
	 *
	 * @param Site $site The site.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	protected function showApiKey( Site $site ): int
	{
		$this->info( sprintf( __( 'Site: %s' ), $site->name ) );
		$this->line( sprintf( __( 'API Key Status: %s' ), $site->hasApiKey() ? __( 'Set' ) : __( 'Not set' ) ) );

		if ( null !== $site->api_key_last_used_at ) {
			$this->line( sprintf( __( 'Last Used: %s' ), $site->api_key_last_used_at->diffForHumans() ) );
		} else {
			$this->line( __( 'Last Used: Never' ) );
		}

		$this->newLine();
		$this->line( __( 'Note: API keys are stored as hashes and cannot be retrieved.' ) );
		$this->line( __( 'Use --rotate to generate a new key if needed.' ) );

		return self::SUCCESS;
	}
}
