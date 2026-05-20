<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Requests\TrackPageViewRequest;
use Illuminate\Validation\ValidationException;

/**
 * Resolve and validate a page view payload through the form request.
 *
 * @param array<string, mixed> $payload The request payload.
 *
 * @return array<string, mixed> The validated data.
 */
function validatePageViewPayload( array $payload ): array
{
	$request = TrackPageViewRequest::create(
		'/api/analytics/pageview',
		'POST',
		$payload,
		[],
		[],
		[ 'HTTP_ACCEPT' => 'application/json' ],
	);

	$request->headers->set( 'Accept', 'application/json' );
	$request->setContainer( app() );
	$request->setRedirector( app( Illuminate\Routing\Redirector::class ) );
	$request->validateResolved();

	return $request->validated();
}

test( 'page view request accepts structured fingerprint signals', function (): void {
	$validated = validatePageViewPayload( [
		'visitor_id'  => 'visitor-1',
		'session_id'  => 'session-1',
		'path'        => '/landing',
		'fingerprint' => [
			'webdriver'            => true,
			'has_plugins'          => false,
			'has_languages'        => true,
			'has_webgl'            => true,
			'has_canvas'           => true,
			'headless'             => false,
			'missing_apis'         => false,
			'screen_color_depth'   => 24,
			'hardware_concurrency' => 8,
			'device_memory'        => 4.0,
		],
	] );

	expect( $validated['fingerprint']['webdriver'] )->toBeTrue();
	expect( $validated['fingerprint']['screen_color_depth'] )->toBe( 24 );
} );

test( 'page view request does not require fingerprint data', function (): void {
	$validated = validatePageViewPayload( [
		'visitor_id' => 'visitor-1',
		'session_id' => 'session-1',
		'path'       => '/landing',
	] );

	expect( $validated )->not->toHaveKey( 'fingerprint' );
} );

test( 'page view request discards legacy string fingerprint', function (): void {
	$validated = validatePageViewPayload( [
		'visitor_id'  => 'visitor-1',
		'session_id'  => 'session-1',
		'path'        => '/landing',
		'fingerprint' => 'legacy-hash-string',
	] );

	expect( $validated['fingerprint'] ?? null )->toBeNull();
} );

test( 'page view request rejects invalid fingerprint signal types', function (): void {
	validatePageViewPayload( [
		'visitor_id'  => 'visitor-1',
		'session_id'  => 'session-1',
		'path'        => '/landing',
		'fingerprint' => [
			'screen_color_depth' => 'not-a-number',
		],
	] );
} )->throws( ValidationException::class );
