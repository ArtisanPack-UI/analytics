<?php

declare( strict_types=1 );

test( 'dashboard driver defaults to livewire', function (): void {
	expect( config( 'artisanpack.analytics.dashboard_driver' ) )->toBe( 'livewire' );
} );

test( 'dashboard driver can be set to inertia', function (): void {
	config( ['artisanpack.analytics.dashboard_driver' => 'inertia'] );

	expect( config( 'artisanpack.analytics.dashboard_driver' ) )->toBe( 'inertia' );
} );

test( 'inertia page component defaults are configured', function (): void {
	expect( config( 'artisanpack.analytics.inertia.pages.dashboard' ) )->toBe( 'Analytics/Dashboard' );
	expect( config( 'artisanpack.analytics.inertia.pages.pages' ) )->toBe( 'Analytics/Pages' );
	expect( config( 'artisanpack.analytics.inertia.pages.traffic' ) )->toBe( 'Analytics/Traffic' );
	expect( config( 'artisanpack.analytics.inertia.pages.audience' ) )->toBe( 'Analytics/Audience' );
	expect( config( 'artisanpack.analytics.inertia.pages.events' ) )->toBe( 'Analytics/Events' );
	expect( config( 'artisanpack.analytics.inertia.pages.realtime' ) )->toBe( 'Analytics/Realtime' );
} );

test( 'inertia share data defaults to true', function (): void {
	expect( config( 'artisanpack.analytics.inertia.share_data' ) )->toBeTrue();
} );

test( 'inertia routes are not registered when driver is livewire', function (): void {
	// Default driver is livewire, so Inertia-specific dashboard sub-routes
	// (analytics.dashboard.pages etc.) should not be registered.
	$routes     = app( 'router' )->getRoutes();
	$routeNames = collect( $routes->getRoutesByName() )->keys();

	$inertiaRoutes = $routeNames->filter( fn ( $name ) => str_starts_with( $name, 'analytics.dashboard.' ) );

	expect( $inertiaRoutes )->toBeEmpty();
} );

test( 'inertia page component names can be customized via config', function (): void {
	config( ['artisanpack.analytics.inertia.pages.dashboard' => 'CustomApp/AnalyticsDashboard'] );

	expect( config( 'artisanpack.analytics.inertia.pages.dashboard' ) )->toBe( 'CustomApp/AnalyticsDashboard' );
} );

test( 'inertia share data can be disabled via config', function (): void {
	config( ['artisanpack.analytics.inertia.share_data' => false] );

	expect( config( 'artisanpack.analytics.inertia.share_data' ) )->toBeFalse();
} );

test( 'dashboard driver supports livewire and inertia values', function ( string $driver ): void {
	config( ['artisanpack.analytics.dashboard_driver' => $driver] );

	expect( config( 'artisanpack.analytics.dashboard_driver' ) )->toBe( $driver );
} )->with( ['livewire', 'inertia'] );
