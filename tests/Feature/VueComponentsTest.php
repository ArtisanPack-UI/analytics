<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\AnalyticsServiceProvider;

test( 'vue components are registered as publishable assets', function (): void {
	$serviceProvider = app()->getProvider( AnalyticsServiceProvider::class );

	expect( $serviceProvider )->not->toBeNull();

	$publishes = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-vue' );

	expect( $publishes )->not->toBeEmpty();
} );

test( 'vue component source directory exists', function (): void {
	$vueDir = __DIR__ . '/../../resources/js/vue';

	expect( is_dir( $vueDir ) )->toBeTrue();
} );

test( 'all vue widget components exist', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/vue/widgets/{$component}.vue";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'StatsCards',
	'VisitorsChart',
	'TopPages',
	'TrafficSources',
	'RealtimeVisitors',
] );

test( 'all vue dashboard components exist', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/vue/{$component}.vue";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'AnalyticsDashboard',
	'PageAnalytics',
	'SiteSelector',
	'MultiTenantDashboard',
] );

test( 'all vue consent components exist', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/vue/{$component}.vue";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'ConsentBanner',
	'ConsentPreferences',
	'ConsentStatus',
] );

test( 'vue barrel export file exists', function (): void {
	$filePath = __DIR__ . '/../../resources/js/vue/index.ts';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'vue composable files exist', function ( string $composable ): void {
	$filePath = __DIR__ . "/../../resources/js/vue/{$composable}.ts";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'useAnalyticsApi',
	'useConsent',
] );

test( 'vue components publish to correct destination', function (): void {
	$publishes   = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-vue' );
	$destination = resource_path( 'js/vendor/artisanpack-analytics/vue' );

	expect( array_values( $publishes ) )->toContain( $destination );
} );

test( 'vue barrel export references all components', function (): void {
	$indexContent = file_get_contents( __DIR__ . '/../../resources/js/vue/index.ts' );

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
