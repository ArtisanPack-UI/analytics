<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Analytics;
use ArtisanPackUI\Analytics\AnalyticsServiceProvider;
use ArtisanPackUI\Analytics\Contracts\AnalyticsServiceInterface;

test( 'service provider is registered', function (): void {
	$providers = app()->getLoadedProviders();

	expect( $providers )->toHaveKey( AnalyticsServiceProvider::class );
} );

test( 'analytics singleton is registered', function (): void {
	$analytics = app( 'analytics' );

	expect( $analytics )->toBeInstanceOf( Analytics::class );
} );

test( 'analytics class is bound to container', function (): void {
	$analytics = app( Analytics::class );

	expect( $analytics )->toBeInstanceOf( Analytics::class );
} );

test( 'analytics service interface is bound', function (): void {
	$analytics = app( AnalyticsServiceInterface::class );

	expect( $analytics )->toBeInstanceOf( Analytics::class );
} );

test( 'analytics facade works', function (): void {
	$result = ArtisanPackUI\Analytics\Facades\Analytics::canTrack();

	expect( $result )->toBeBool();
} );

test( 'same instance is returned for singleton', function (): void {
	$first  = app( 'analytics' );
	$second = app( 'analytics' );

	expect( $first )->toBe( $second );
} );

test( 'analytics helper function works', function (): void {
	$analytics = analytics();

	expect( $analytics )->toBeInstanceOf( Analytics::class );
} );

test( 'local provider is registered by default', function (): void {
	$analytics     = app( Analytics::class );
	$providerNames = $analytics->getProviderNames();

	expect( $providerNames )->toContain( 'local' );
} );
