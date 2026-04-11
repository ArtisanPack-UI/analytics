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

test( 'vue directory structure has required subdirectories', function ( string $subdir ): void {
	$path = __DIR__ . "/../../resources/js/vue/{$subdir}";

	expect( is_dir( $path ) )->toBeTrue();
} )->with( [
	'components',
	'pages',
	'composables',
	'types',
] );

test( 'all vue widget components exist in components directory', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/vue/components/{$component}.vue";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'StatsCards',
	'VisitorsChart',
	'TopPages',
	'TrafficSources',
	'RealtimeVisitors',
] );

test( 'all vue consent components exist in components directory', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/vue/components/{$component}.vue";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'ConsentBanner',
	'ConsentPreferences',
	'ConsentStatus',
] );

test( 'site selector component exists in components directory', function (): void {
	$filePath = __DIR__ . '/../../resources/js/vue/components/SiteSelector.vue';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'all vue dashboard page components exist in pages directory', function ( string $component ): void {
	$filePath = __DIR__ . "/../../resources/js/vue/pages/{$component}.vue";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'AnalyticsDashboard',
	'PageAnalytics',
	'MultiTenantDashboard',
] );

test( 'vue composable files exist in composables directory', function ( string $composable ): void {
	$filePath = __DIR__ . "/../../resources/js/vue/composables/{$composable}.ts";

	expect( file_exists( $filePath ) )->toBeTrue();
} )->with( [
	'useAnalyticsApi',
	'useConsent',
] );

test( 'vue types re-export file exists', function (): void {
	$filePath = __DIR__ . '/../../resources/js/vue/types/index.ts';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'vue barrel export file exists', function (): void {
	$filePath = __DIR__ . '/../../resources/js/vue/index.ts';

	expect( file_exists( $filePath ) )->toBeTrue();
} );

test( 'vue components publish to correct destination', function (): void {
	$publishes   = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-vue' );
	$destination = resource_path( 'js/vendor/artisanpack-analytics/vue' );

	expect( array_values( $publishes ) )->toContain( $destination );
} );

test( 'vue publish tag includes shared types', function (): void {
	$publishes   = AnalyticsServiceProvider::pathsToPublish( AnalyticsServiceProvider::class, 'analytics-vue' );
	$destination = resource_path( 'js/vendor/artisanpack-analytics/types' );

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

test( 'vue barrel export uses new directory structure', function (): void {
	$indexContent = file_get_contents( __DIR__ . '/../../resources/js/vue/index.ts' );

	expect( $indexContent )
		->toContain( './components/' )
		->toContain( './pages/' )
		->toContain( './composables/' )
		->toContain( './types' );
} );
