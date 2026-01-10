<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Exceptions\AnalyticsException;
use ArtisanPackUI\Analytics\Exceptions\ProviderException;
use ArtisanPackUI\Analytics\Exceptions\QueryException;
use ArtisanPackUI\Analytics\Exceptions\TrackingException;

test( 'analytics exception can be created', function (): void {
	$exception = new AnalyticsException( 'Test message' );

	expect( $exception->getMessage() )->toBe( 'Test message' );
	expect( $exception )->toBeInstanceOf( Exception::class );
} );

test( 'analytics disabled exception can be created', function (): void {
	$exception = AnalyticsException::analyticsDisabled();

	expect( $exception )->toBeInstanceOf( AnalyticsException::class );
	expect( $exception->getMessage() )->toContain( 'disabled' );
} );

test( 'tracking exception extends analytics exception', function (): void {
	$exception = new TrackingException( 'Tracking error' );

	expect( $exception )->toBeInstanceOf( AnalyticsException::class );
} );

test( 'invalid page view data exception can be created', function (): void {
	$exception = TrackingException::invalidPageViewData( 'Missing path' );

	expect( $exception->getMessage() )->toContain( 'page view' );
	expect( $exception->getMessage() )->toContain( 'Missing path' );
} );

test( 'consent required exception can be created', function (): void {
	$exception = TrackingException::consentRequired();

	expect( $exception->getMessage() )->toContain( 'consent' );
} );

test( 'rate limited exception includes retry after', function (): void {
	$exception = TrackingException::rateLimited( 60 );

	expect( $exception->getMessage() )->toContain( '60' );
} );

test( 'provider exception includes provider name', function (): void {
	$exception = new ProviderException( 'google', 'Connection failed' );

	expect( $exception->getProviderName() )->toBe( 'google' );
	expect( $exception->getMessage() )->toBe( 'Connection failed' );
} );

test( 'provider not found exception can be created', function (): void {
	$exception = ProviderException::providerNotFound( 'unknown' );

	expect( $exception->getProviderName() )->toBe( 'unknown' );
	expect( $exception->getMessage() )->toContain( 'not registered' );
} );

test( 'provider disabled exception can be created', function (): void {
	$exception = ProviderException::providerDisabled( 'plausible' );

	expect( $exception->getProviderName() )->toBe( 'plausible' );
	expect( $exception->getMessage() )->toContain( 'disabled' );
} );

test( 'query exception extends analytics exception', function (): void {
	$exception = new QueryException( 'Query error' );

	expect( $exception )->toBeInstanceOf( AnalyticsException::class );
} );

test( 'invalid date range exception can be created', function (): void {
	$exception = QueryException::invalidDateRange( 'End before start' );

	expect( $exception->getMessage() )->toContain( 'date range' );
	expect( $exception->getMessage() )->toContain( 'End before start' );
} );

test( 'query timeout exception can be created', function (): void {
	$exception = QueryException::queryTimeout();

	expect( $exception->getMessage() )->toContain( 'timed out' );
} );
