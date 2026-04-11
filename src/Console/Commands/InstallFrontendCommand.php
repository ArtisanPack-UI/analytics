<?php

/**
 * Command to install analytics frontend components for React or Vue.
 *
 * Publishes the framework-specific dashboard components and TypeScript
 * types to the host application's resources directory, then adds the
 * required npm dependencies to the application's package.json.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Artisan command for installing analytics frontend assets.
 *
 * Usage:
 *   php artisan analytics:install-frontend --stack=react
 *   php artisan analytics:install-frontend --stack=vue
 *
 * @since   1.1.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class InstallFrontendCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:install-frontend
		{--stack= : The frontend stack to install (react or vue)}
		{--force : Overwrite existing published files}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Install analytics dashboard components for React or Vue';

	/**
	 * npm dependencies required for the React stack.
	 *
	 * @var array<string, string>
	 */
	protected array $reactDependencies = [
		'@artisanpack-ui/react'  => '^1.0',
		'@artisanpack-ui/tokens' => '^1.0',
		'react'                  => '^18.0 || ^19.0',
		'react-dom'              => '^18.0 || ^19.0',
		'react-apexcharts'       => '^1.4',
		'apexcharts'             => '^3.44',
	];

	/**
	 * npm dependencies required for the Vue stack.
	 *
	 * @var array<string, string>
	 */
	protected array $vueDependencies = [
		'@artisanpack-ui/vue'    => '^1.0',
		'@artisanpack-ui/tokens' => '^1.0',
		'vue'                    => '^3.4',
		'vue3-apexcharts'        => '^1.5',
		'apexcharts'             => '^3.44',
	];

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.1.0
	 */
	public function handle(): int
	{
		$stack = $this->option( 'stack' );

		if ( ! $stack ) {
			$this->components->error( 'The --stack option is required. Use --stack=react or --stack=vue.' );

			return self::FAILURE;
		}

		$stack = strtolower( $stack );

		if ( ! in_array( $stack, [ 'react', 'vue' ], true ) ) {
			$this->components->error( "Invalid stack \"{$stack}\". Supported stacks: react, vue." );

			return self::FAILURE;
		}

		$this->info( "Installing analytics {$stack} components..." );
		$this->newLine();

		$this->publishComponents( $stack );
		$this->addNpmDependencies( $stack );

		$this->newLine();
		$this->info( "Analytics {$stack} components installed successfully!" );
		$this->newLine();

		$this->displayNextSteps( $stack );

		return self::SUCCESS;
	}

	/**
	 * Publish the framework-specific components and shared types.
	 *
	 * @param string $stack The frontend stack ('react' or 'vue').
	 *
	 * @since 1.1.0
	 */
	protected function publishComponents( string $stack ): void
	{
		$this->components->task( "Publishing {$stack} components", function () use ( $stack ) {
			$this->callSilently( 'vendor:publish', [
				'--tag'   => "analytics-{$stack}",
				'--force' => $this->option( 'force' ),
			] );

			return true;
		} );
	}

	/**
	 * Add required npm dependencies to the application's package.json.
	 *
	 * Reads the existing package.json, merges in the required dependencies,
	 * and writes the file back. Does not overwrite existing version constraints.
	 *
	 * @param string $stack The frontend stack ('react' or 'vue').
	 *
	 * @since 1.1.0
	 */
	protected function addNpmDependencies( string $stack ): void
	{
		$this->components->task( 'Adding npm dependencies', function () use ( $stack ) {
			$packageJsonPath = base_path( 'package.json' );

			if ( ! File::exists( $packageJsonPath ) ) {
				$this->components->warn( 'No package.json found. Skipping npm dependency installation.' );

				return false;
			}

			$packageJson = json_decode( File::get( $packageJsonPath ), true );

			if ( null === $packageJson ) {
				$this->components->warn( 'Could not parse package.json. Skipping npm dependency installation.' );

				return false;
			}

			$dependencies     = 'react' === $stack ? $this->reactDependencies : $this->vueDependencies;
			$existingDeps     = $packageJson['dependencies'] ?? [];
			$addedDeps        = [];

			foreach ( $dependencies as $package => $version ) {
				if ( ! isset( $existingDeps[ $package ] ) ) {
					$existingDeps[ $package ] = $version;
					$addedDeps[]              = $package;
				}
			}

			if ( empty( $addedDeps ) ) {
				return true;
			}

			ksort( $existingDeps );
			$packageJson['dependencies'] = $existingDeps;

			File::put(
				$packageJsonPath,
				json_encode( $packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n",
			);

			return true;
		} );
	}

	/**
	 * Display next steps after installation.
	 *
	 * @param string $stack The frontend stack ('react' or 'vue').
	 *
	 * @since 1.1.0
	 */
	protected function displayNextSteps( string $stack ): void
	{
		$this->components->info( 'Next steps:' );
		$this->newLine();

		$steps = [
			'Run <comment>npm install</comment> to install the new dependencies',
			"Set <comment>dashboard_driver</comment> to <comment>'inertia'</comment> in <comment>config/artisanpack/analytics.php</comment>",
			"Import components from <comment>resources/js/vendor/artisanpack-analytics/{$stack}</comment>",
			'Run <comment>npm run dev</comment> to compile assets',
		];

		foreach ( $steps as $index => $step ) {
			$this->line( '  ' . ( $index + 1 ) . '. ' . $step );
		}

		$this->newLine();
		$this->components->info( 'Documentation: https://artisanpackui.dev/docs/analytics' );
	}
}
