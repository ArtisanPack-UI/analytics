<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\PageViewData;

test( 'can create page view data', function (): void {
	$data = new PageViewData(
		path: '/test-page',
		title: 'Test Page',
		referrer: 'https://google.com',
	);

	expect( $data->path )->toBe( '/test-page' );
	expect( $data->title )->toBe( 'Test Page' );
	expect( $data->referrer )->toBe( 'https://google.com' );
} );

test( 'page view data path is required', function (): void {
	$data = new PageViewData( path: '/required-path' );

	expect( $data->path )->toBe( '/required-path' );
	expect( $data->title )->toBeNull();
	expect( $data->referrer )->toBeNull();
} );

test( 'page view data supports utm parameters', function (): void {
	$data = new PageViewData(
		path: '/campaign-page',
		utmSource: 'newsletter',
		utmMedium: 'email',
		utmCampaign: 'summer-sale',
	);

	expect( $data->utmSource )->toBe( 'newsletter' );
	expect( $data->utmMedium )->toBe( 'email' );
	expect( $data->utmCampaign )->toBe( 'summer-sale' );
} );

test( 'page view data supports custom data', function (): void {
	$customData = [ 'user_type' => 'premium', 'experiment' => 'variant_a' ];

	$data = new PageViewData(
		path: '/premium-page',
		customData: $customData,
	);

	expect( $data->customData )->toBe( $customData );
	expect( $data->customData['user_type'] )->toBe( 'premium' );
} );

test( 'page view data can be converted to array', function (): void {
	$data = new PageViewData(
		path: '/test-page',
		title: 'Test',
		sessionId: 'session-123',
	);

	$array = $data->toArray();

	expect( $array )->toBeArray();
	expect( $array['path'] )->toBe( '/test-page' );
	expect( $array['title'] )->toBe( 'Test' );
	expect( $array['session_id'] )->toBe( 'session-123' );
} );

test( 'page view data array excludes null values', function (): void {
	$data = new PageViewData( path: '/test' );

	$array = $data->toArray();

	expect( $array )->toHaveKey( 'path' );
	expect( $array )->not->toHaveKey( 'title' );
	expect( $array )->not->toHaveKey( 'referrer' );
} );

test( 'page view data supports tenant id', function (): void {
	$data = new PageViewData(
		path: '/tenant-page',
		tenantId: 'tenant-123',
	);

	expect( $data->tenantId )->toBe( 'tenant-123' );
} );

test( 'page view data supports load time', function (): void {
	$data = new PageViewData(
		path: '/fast-page',
		loadTime: 1250.5,
	);

	expect( $data->loadTime )->toBe( 1250.5 );
} );

test( 'page view data supports fingerprint signals', function (): void {
	$fingerprint = [
		'webdriver'    => true,
		'has_plugins'  => false,
		'headless'     => true,
		'missing_apis' => false,
	];

	$data = new PageViewData(
		path: '/bot-page',
		fingerprint: $fingerprint,
	);

	expect( $data->fingerprint )->toBe( $fingerprint );
	expect( $data->fingerprint['webdriver'] )->toBeTrue();
} );

test( 'page view data fingerprint defaults to null', function (): void {
	$data = new PageViewData( path: '/no-fingerprint' );

	expect( $data->fingerprint )->toBeNull();
} );

test( 'page view data reads fingerprint from request data', function (): void {
	$request = Illuminate\Http\Request::create( '/track', 'POST' );

	$data = PageViewData::fromRequest( $request, [
		'path'        => '/landing',
		'fingerprint' => [ 'webdriver' => true ],
	] );

	expect( $data->fingerprint )->toBe( [ 'webdriver' => true ] );
} );

test( 'page view data ignores non-array fingerprint from request', function (): void {
	$request = Illuminate\Http\Request::create( '/track', 'POST' );

	$data = PageViewData::fromRequest( $request, [
		'path'        => '/landing',
		'fingerprint' => 'legacy-hash-string',
	] );

	expect( $data->fingerprint )->toBeNull();
} );

test( 'page view data includes fingerprint in array', function (): void {
	$data = new PageViewData(
		path: '/test',
		fingerprint: [ 'headless' => true ],
	);

	$array = $data->toArray();

	expect( $array )->toHaveKey( 'fingerprint' );
	expect( $array['fingerprint'] )->toBe( [ 'headless' => true ] );
} );
