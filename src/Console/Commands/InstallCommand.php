<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Command to install the ArtisanPack UI Analytics package.
 *
 * This command publishes configuration, migrations, views, and
 * the JavaScript tracker to the host application.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class InstallCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:install
		{--force : Overwrite existing files}
		{--config : Only publish configuration}
		{--migrations : Only publish migrations}
		{--views : Only publish views}
		{--tracker : Only publish JavaScript tracker}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Install the ArtisanPack UI Analytics package';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function handle(): int
	{
		$this->info( 'Installing ArtisanPack UI Analytics...' );
		$this->newLine();

		$specificPublish = $this->option( 'config' )
			|| $this->option( 'migrations' )
			|| $this->option( 'views' )
			|| $this->option( 'tracker' );

		if ( ! $specificPublish || $this->option( 'config' ) ) {
			$this->publishConfiguration();
		}

		if ( ! $specificPublish || $this->option( 'migrations' ) ) {
			$this->publishMigrations();
		}

		if ( ! $specificPublish || $this->option( 'views' ) ) {
			$this->publishViews();
		}

		if ( ! $specificPublish || $this->option( 'tracker' ) ) {
			$this->publishTracker();
		}

		$this->newLine();
		$this->info( 'ArtisanPack UI Analytics installed successfully!' );
		$this->newLine();

		$this->displayNextSteps();

		return self::SUCCESS;
	}

	/**
	 * Publish the configuration file.
	 *
	 * @since 1.0.0
	 */
	protected function publishConfiguration(): void
	{
		$this->components->task( 'Publishing configuration', function () {
			$this->callSilently( 'vendor:publish', [
				'--tag'   => 'analytics-config',
				'--force' => $this->option( 'force' ),
			] );

			return true;
		} );
	}

	/**
	 * Publish the database migrations.
	 *
	 * @since 1.0.0
	 */
	protected function publishMigrations(): void
	{
		$this->components->task( 'Publishing migrations', function () {
			$this->callSilently( 'vendor:publish', [
				'--tag'   => 'analytics-migrations',
				'--force' => $this->option( 'force' ),
			] );

			return true;
		} );
	}

	/**
	 * Publish the views.
	 *
	 * @since 1.0.0
	 */
	protected function publishViews(): void
	{
		$this->components->task( 'Publishing views', function () {
			$this->callSilently( 'vendor:publish', [
				'--tag'   => 'analytics-views',
				'--force' => $this->option( 'force' ),
			] );

			return true;
		} );
	}

	/**
	 * Publish the JavaScript tracker.
	 *
	 * @since 1.0.0
	 */
	protected function publishTracker(): void
	{
		$this->components->task( 'Publishing JavaScript tracker', function () {
			$this->callSilently( 'vendor:publish', [
				'--tag'   => 'analytics-tracker',
				'--force' => $this->option( 'force' ),
			] );

			return true;
		} );
	}

	/**
	 * Display next steps after installation.
	 *
	 * @since 1.0.0
	 */
	protected function displayNextSteps(): void
	{
		$this->components->info( 'Next steps:' );
		$this->newLine();

		$steps = [
			'Run database migrations: <comment>php artisan migrate</comment>',
			'Add the tracker to your layout: <comment><x-analytics-tracker /></comment>',
			'Configure analytics in <comment>config/artisanpack/analytics.php</comment>',
			'Visit the dashboard at <comment>/analytics</comment> (requires authentication)',
		];

		foreach ( $steps as $index => $step ) {
			$this->line( '  ' . ( $index + 1 ) . '. ' . $step );
		}

		$this->newLine();
		$this->components->info( 'Documentation: https://artisanpackui.dev/docs/analytics' );
	}
}
