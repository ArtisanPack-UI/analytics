<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Controllers\InertiaDashboardController;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Inertia Dashboard Tests
|--------------------------------------------------------------------------
|
| Since routes are registered at boot time and we need the inertia driver
| config set before boot, we manually register Inertia routes in beforeEach.
|
*/

beforeEach( function (): void {
	// Mock the AnalyticsQuery service
	$this->analyticsQuery = Mockery::mock( AnalyticsQuery::class );
	$this->app->instance( AnalyticsQuery::class, $this->analyticsQuery );

	// Create a minimal Inertia root view for testing
	$viewPath = resource_path( 'views' );
	if ( ! is_dir( $viewPath ) ) {
		mkdir( $viewPath, 0755, true );
	}
	file_put_contents( $viewPath . '/app.blade.php', '<!DOCTYPE html><html><head></head><body>@inertia</body></html>' );

	// Disable page component file existence check in Inertia testing
	config( ['inertia.testing.ensure_pages_exist' => false] );

	// Track the view file path for cleanup
	$this->inertiaViewFile = resource_path( 'views/app.blade.php' );

	// Register the Inertia dashboard routes manually for testing
	Route::middleware( ['web'] )->group( function (): void {
		Route::prefix( 'analytics' )->group( function (): void {
			Route::get( '/', [InertiaDashboardController::class, 'index'] )
				->name( 'analytics.inertia.dashboard' );

			Route::get( '/pages', [InertiaDashboardController::class, 'pages'] )
				->name( 'analytics.inertia.pages' );

			Route::get( '/traffic', [InertiaDashboardController::class, 'traffic'] )
				->name( 'analytics.inertia.traffic' );

			Route::get( '/audience', [InertiaDashboardController::class, 'audience'] )
				->name( 'analytics.inertia.audience' );

			Route::get( '/events', [InertiaDashboardController::class, 'events'] )
				->name( 'analytics.inertia.events' );

			Route::get( '/realtime', [InertiaDashboardController::class, 'realtime'] )
				->name( 'analytics.inertia.realtime' );
		} );
	} );
} );

afterEach( function (): void {
	// Clean up the temporary Inertia root view
	if ( isset( $this->inertiaViewFile ) && file_exists( $this->inertiaViewFile ) ) {
		unlink( $this->inertiaViewFile );
	}
} );

test( 'dashboard index returns inertia response with correct props', function (): void {
	$this->analyticsQuery->shouldReceive( 'getStats' )
		->once()
		->andReturn( [
			'pageviews'            => 100,
			'visitors'             => 50,
			'sessions'             => 75,
			'bounce_rate'          => 40.0,
			'avg_session_duration' => 120,
			'pages_per_session'    => 1.3,
			'realtime_visitors'    => 2,
			'comparison'           => null,
		] );

	$this->analyticsQuery->shouldReceive( 'getPageViews' )
		->once()
		->andReturn( collect( [
			['date' => '2024-01-01', 'pageviews' => 10, 'visitors' => 5],
		] ) );

	$this->analyticsQuery->shouldReceive( 'getTopPages' )
		->once()
		->andReturn( collect( [
			['path' => '/', 'title' => 'Home', 'views' => 50, 'unique_views' => 40],
		] ) );

	$this->analyticsQuery->shouldReceive( 'getTrafficSources' )
		->once()
		->andReturn( collect( [
			['source' => 'google', 'medium' => 'organic', 'sessions' => 30, 'visitors' => 25],
		] ) );

	$this->get( '/analytics' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->component( 'Analytics/Dashboard' )
			->has( 'stats' )
			->has( 'chartData' )
			->has( 'topPages' )
			->has( 'trafficSources' )
			->has( 'dateRange' )
			->has( 'dateRangePreset' )
			->has( 'dateRangePresets' )
			->has( 'filters' ),
		);
} );

test( 'pages route returns inertia response with page data', function (): void {
	$this->analyticsQuery->shouldReceive( 'getTopPages' )
		->once()
		->andReturn( collect( [
			['path' => '/', 'title' => 'Home', 'views' => 50, 'unique_views' => 40],
		] ) );

	$this->analyticsQuery->shouldReceive( 'getPageViews' )
		->once()
		->andReturn( collect( [] ) );

	$this->get( '/analytics/pages' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->component( 'Analytics/Pages' )
			->has( 'topPages' )
			->has( 'chartData' ),
		);
} );

test( 'traffic route returns inertia response with traffic source data', function (): void {
	$this->analyticsQuery->shouldReceive( 'getTrafficSources' )
		->once()
		->andReturn( collect( [
			['source' => 'google', 'medium' => 'organic', 'sessions' => 30, 'visitors' => 25],
		] ) );

	$this->get( '/analytics/traffic' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->component( 'Analytics/Traffic' )
			->has( 'trafficSources' ),
		);
} );

test( 'audience route returns inertia response with audience data', function (): void {
	$this->analyticsQuery->shouldReceive( 'getDeviceBreakdown' )
		->once()
		->andReturn( collect( [
			['device_type' => 'desktop', 'sessions' => 60, 'percentage' => 60.0],
		] ) );

	$this->analyticsQuery->shouldReceive( 'getBrowserBreakdown' )
		->once()
		->andReturn( collect( [] ) );

	$this->analyticsQuery->shouldReceive( 'getCountryBreakdown' )
		->once()
		->andReturn( collect( [] ) );

	$this->get( '/analytics/audience' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->component( 'Analytics/Audience' )
			->has( 'deviceBreakdown' )
			->has( 'browserBreakdown' )
			->has( 'countryBreakdown' ),
		);
} );

test( 'events route returns inertia response with event data', function (): void {
	$this->analyticsQuery->shouldReceive( 'getEventBreakdown' )
		->once()
		->andReturn( collect( [] ) );

	$this->get( '/analytics/events' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->component( 'Analytics/Events' )
			->has( 'eventBreakdown' ),
		);
} );

test( 'realtime route returns inertia response with realtime data', function (): void {
	$this->analyticsQuery->shouldReceive( 'getRealtime' )
		->once()
		->andReturn( [
			'active_visitors' => 3,
			'timestamp'       => now()->toIso8601String(),
		] );

	$this->get( '/analytics/realtime' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->component( 'Analytics/Realtime' )
			->has( 'realtime' ),
		);
} );

test( 'custom page component names from config are used', function (): void {
	config( ['artisanpack.analytics.inertia.pages.dashboard' => 'MyApp/CustomDashboard'] );

	$this->analyticsQuery->shouldReceive( 'getStats' )->andReturn( [] );
	$this->analyticsQuery->shouldReceive( 'getPageViews' )->andReturn( collect() );
	$this->analyticsQuery->shouldReceive( 'getTopPages' )->andReturn( collect() );
	$this->analyticsQuery->shouldReceive( 'getTrafficSources' )->andReturn( collect() );

	$this->get( '/analytics' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->component( 'MyApp/CustomDashboard' ),
		);
} );

test( 'date range filter is applied from query parameters', function (): void {
	$this->analyticsQuery->shouldReceive( 'getStats' )->once()->andReturn( [] );
	$this->analyticsQuery->shouldReceive( 'getPageViews' )->once()->andReturn( collect() );
	$this->analyticsQuery->shouldReceive( 'getTopPages' )->once()->andReturn( collect() );
	$this->analyticsQuery->shouldReceive( 'getTrafficSources' )->once()->andReturn( collect() );

	$this->get( '/analytics?period=7d' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->where( 'dateRangePreset', '7d' ),
		);
} );

test( 'site_id filter is applied from query parameters', function (): void {
	$this->analyticsQuery->shouldReceive( 'getStats' )->once()->andReturn( [] );
	$this->analyticsQuery->shouldReceive( 'getPageViews' )->once()->andReturn( collect() );
	$this->analyticsQuery->shouldReceive( 'getTopPages' )->once()->andReturn( collect() );
	$this->analyticsQuery->shouldReceive( 'getTrafficSources' )->once()->andReturn( collect() );

	$this->get( '/analytics?site_id=5' )
		->assertOk()
		->assertInertia( fn ( AssertableInertia $page ) => $page
			->where( 'filters.site_id', 5 ),
		);
} );
