<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\AnalyticsServiceProvider;

test( 'react components are registered as publishable assets', function (): void {
	$serviceProvider = app()->getProvider( AnalyticsServiceProvider::class );

	expect( $serviceProvider )->not->toBeNull();

	$publishes = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-react' );

	expect( $publishes )->not->toBeEmpty();
} );

test( 'react component source files exist', function (): void {
	$reactDir = __DIR__ . '/../../resources/js/react';

	expect( is_dir( $reactDir ) )->toBeTrue();
} );

test( 'react directory structure has required subdirectories', function ( string $subdir ): void {
	$path = __DIR__ . "/../../resources/js/react/{$subdir}";

	expect( is_dir( $path ) )->toBeTrue();
} )->with( [
	'components',
	'pages',
	'hooks',
	'types',
] );

test( 'all react widget components exist in components directory', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/react/components/{$component}.tsx";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'StatsCards',
	'VisitorsChart',
	'TopPages',
	'TrafficSources',
	'RealtimeVisitors',
] );

test( 'all react consent components exist in components directory', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/react/components/{$component}.tsx";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'ConsentBanner',
	'ConsentPreferences',
	'ConsentStatus',
] );

test( 'site selector component exists in components directory', function (): void {
	$filePath = __DIR__ . '/../../resources/js/react/components/SiteSelector.tsx';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'all react dashboard page components exist in pages directory', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/react/pages/{$component}.tsx";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'AnalyticsDashboard',
	'PageAnalytics',
	'MultiTenantDashboard',
] );

test( 'react hook files exist in hooks directory', function ( string $hook ): void {
	$filePath = __DIR__ . "/../../resources/js/react/hooks/{$hook}.ts";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'useAnalyticsApi',
	'useConsent',
] );

test( 'react types re-export file exists', function (): void {
	$filePath = __DIR__ . '/../../resources/js/react/types/index.ts';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'react barrel export file exists', function (): void {
	$filePath = __DIR__ . '/../../resources/js/react/index.ts';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'react components publish to correct destination', function (): void {
	$publishes = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-react' );

	$destination = resource_path( 'js/vendor/artisanpack-analytics/react' );

	expect( array_values( $publishes ) )->toContain( $destination );
} );

test( 'react publish tag includes shared types', function (): void {
	$publishes = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-react' );

	$destination = resource_path( 'js/vendor/artisanpack-analytics/types' );

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
		->toContain( 'ConsentBanner' )
		->toContain( 'ConsentPreferences' )
		->toContain( 'ConsentStatus' )
		->toContain( 'useAnalyticsApi' )
		->toContain( 'useConsent' );
} );

test( 'react barrel export uses new directory structure', function (): void {
	$indexContent = file_get_contents( __DIR__ . '/../../resources/js/react/index.ts' );

	expect( $indexContent )
		->toContain( './components/' )
		->toContain( './pages/' )
		->toContain( './hooks/' );
} );
