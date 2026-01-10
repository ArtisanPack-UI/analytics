<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\Analytics\AnalyticsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base test case for Analytics package tests.
 *
 * @since   1.0.0
 *
 * @package Tests
 */
abstract class TestCase extends BaseTestCase
{
	/**
	 * Get package providers.
	 *
	 * @param \Illuminate\Foundation\Application $app
	 *
	 * @return array<int, class-string>
	 */
	protected function getPackageProviders( $app ): array
	{
		return [
			AnalyticsServiceProvider::class,
		];
	}

	/**
	 * Get package aliases.
	 *
	 * @param \Illuminate\Foundation\Application $app
	 *
	 * @return array<string, class-string>
	 */
	protected function getPackageAliases( $app ): array
	{
		return [
			'Analytics' => \ArtisanPackUI\Analytics\Facades\Analytics::class,
		];
	}

	/**
	 * Define environment setup.
	 *
	 * @param \Illuminate\Foundation\Application $app
	 */
	protected function defineEnvironment( $app ): void
	{
		// Use SQLite in-memory database for testing
		$app['config']->set( 'database.default', 'testing' );
		$app['config']->set( 'database.connections.testing', [
			'driver'   => 'sqlite',
			'database' => ':memory:',
			'prefix'   => '',
		] );

		// Set default analytics config
		$app['config']->set( 'artisanpack.analytics.enabled', true );
		$app['config']->set( 'artisanpack.analytics.local.queue_processing', false );
	}
}
