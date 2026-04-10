<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\AnalyticsServiceProvider;

test( 'react components are registered as publishable assets', function (): void {
	$publishableGroups = AnalyticsServiceProvider::$publishGroups ?? [];

	// Get all publishable paths for the 'analytics-react' tag
	$paths           = app()->make( 'migrator' ); // just need to verify the tag exists
	$serviceProvider = app()->getProvider( AnalyticsServiceProvider::class );

	expect( $serviceProvider )->not->toBeNull();

	// Verify the publish tag is registered
	$publishes = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-react' );

	expect( $publishes )->not->toBeEmpty();
} );

test( 'react component source files exist', function (): void {
	$reactDir = __DIR__ . '/../../resources/js/react';

	expect( is_dir( $reactDir ) )->toBeTrue();
} );

test( 'all react widget components exist', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/react/widgets/{$component}.tsx";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'StatsCards',
	'VisitorsChart',
	'TopPages',
	'TrafficSources',
	'RealtimeVisitors',
] );

test( 'all react dashboard components exist', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/react/{$component}.tsx";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'AnalyticsDashboard',
	'PageAnalytics',
	'SiteSelector',
	'MultiTenantDashboard',
] );

test( 'react barrel export file exists', function (): void {
	$filePath = __DIR__ . '/../../resources/js/react/index.ts';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'react hook file exists', function (): void {
	$filePath = __DIR__ . '/../../resources/js/react/useAnalyticsApi.ts';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'react components publish to correct destination', function (): void {
	$publishes = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-react' );

	$destination = resource_path( 'js/vendor/artisanpack-analytics/react' );

	expect( array_values( $publishes ) )->toContain( $destination );
} );

test( 'react barrel export references all components', function (): void {
	$indexContent = file_get_contents( __DIR__ . '/../../resources/js/react/index.ts' );

	expect( $indexContent )
		->toContain( 'StatsCards' )
		->toContain( 'VisitorsChart' )
		->toContain( 'TopPages' )
		->toContain( 'TrafficSources' )
		->toContain( 'RealtimeVisitors' )
		->toContain( 'AnalyticsDashboard' )
		->toContain( 'PageAnalytics' )
		->toContain( 'SiteSelector' )
		->toContain( 'MultiTenantDashboard' )
		->toContain( 'useAnalyticsApi' );
} );
