<?php

declare( strict_types=1 );

test( 'configuration is loaded', function (): void {
	$config = config( 'artisanpack.analytics' );

	expect( $config )->toBeArray();
	expect( $config )->toHaveKeys( [
		'enabled',
		'default',
		'local',
		'session',
		'privacy',
		'retention',
		'dashboard',
		'rate_limiting',
	] );
} );

test( 'default provider is set to local', function (): void {
	$default = config( 'artisanpack.analytics.default' );

	expect( $default )->toBe( 'local' );
} );

test( 'analytics is enabled by default', function (): void {
	$enabled = config( 'artisanpack.analytics.enabled' );

	expect( $enabled )->toBeTrue();
} );

test( 'local provider is enabled by default', function (): void {
	$enabled = config( 'artisanpack.analytics.local.enabled' );

	expect( $enabled )->toBeTrue();
} );

test( 'ip anonymization is enabled by default', function (): void {
	$anonymize = config( 'artisanpack.analytics.local.anonymize_ip' );

	expect( $anonymize )->toBeTrue();
} );

test( 'session timeout default is 30 minutes', function (): void {
	$timeout = config( 'artisanpack.analytics.session.timeout' );

	expect( $timeout )->toBe( 30 );
} );

test( 'respect dnt is enabled by default', function (): void {
	$respectDnt = config( 'artisanpack.analytics.privacy.respect_dnt' );

	expect( $respectDnt )->toBeTrue();
} );

test( 'data retention default is 90 days', function (): void {
	$retention = config( 'artisanpack.analytics.retention.period' );

	expect( $retention )->toBe( 90 );
} );

test( 'rate limiting is enabled by default', function (): void {
	$enabled = config( 'artisanpack.analytics.rate_limiting.enabled' );

	expect( $enabled )->toBeTrue();
} );

test( 'multi-tenant is disabled by default', function (): void {
	$enabled = config( 'artisanpack.analytics.multi_tenant.enabled' );

	expect( $enabled )->toBeFalse();
} );

test( 'configuration can be overridden', function (): void {
	config( [ 'artisanpack.analytics.enabled' => false ] );

	expect( config( 'artisanpack.analytics.enabled' ) )->toBeFalse();
} );

test( 'getAnalyticsConfig helper works', function (): void {
	$enabled = getAnalyticsConfig( 'enabled' );

	expect( $enabled )->toBeTrue();
} );

test( 'getAnalyticsConfig returns default for missing key', function (): void {
	$value = getAnalyticsConfig( 'nonexistent.key', 'default' );

	expect( $value )->toBe( 'default' );
} );
