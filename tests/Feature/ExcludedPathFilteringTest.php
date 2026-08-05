<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

/**
 * A real browser user agent. `canTrack()` runs bot detection on every
 * ingest request, so an absent or synthetic agent would drop the beacon
 * for a reason unrelated to what these tests are asserting.
 */
const EXCLUDED_PATH_TEST_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );

	// `/api/*` ships in the package's default excluded_paths, and the ingest
	// endpoints all live under /api — which is precisely the collision this
	// suite guards against.
	config()->set( 'artisanpack.analytics.privacy.excluded_paths', [
		'/admin/*',
		'/api/*',
	] );
} );

/**
 * Post a payload to an ingest endpoint as a real browser would.
 *
 * @param string               $endpoint The ingest endpoint path.
 * @param array<string, mixed> $payload  The beacon payload.
 */
function postBeacon( string $endpoint, array $payload ): Illuminate\Testing\TestResponse
{
	return test()
		->withHeaders( [ 'User-Agent' => EXCLUDED_PATH_TEST_AGENT ] )
		->postJson( $endpoint, $payload );
}

/**
 * Build a single batch item wrapping a tracked page view.
 *
 * @param string $path The tracked path.
 *
 * @return array<string, mixed>
 */
function batchPageView( string $path ): array
{
	return [
		'type' => 'pageview',
		'data' => [
			'visitor_id' => 'visitor-' . md5( $path ),
			'session_id' => '11111111-2222-4333-8444-555555555555',
			'path'       => $path,
			'title'      => 'Page ' . $path,
		],
	];
}

test( 'batched page views are recorded even though the ingest route itself matches an exclusion', function (): void {
	// Regression test for the bug where PrivacyFilter resolved the excluded
	// path via `$request->input( 'path', $request->path() )`. A batch payload
	// carries no top-level `path`, so it fell through to the request URI —
	// `api/analytics/batch` — which matches `/api/*`, and the middleware
	// discarded the entire batch with a 204 before the controller ever ran.
	$response = postBeacon( '/api/analytics/batch', [
		'items' => [
			batchPageView( '/docs/getting-started' ),
			batchPageView( '/docs/installation' ),
		],
	] );

	$response->assertNoContent();

	expect( PageView::query()->pluck( 'path' )->all() )
		->toEqualCanonicalizing( [ '/docs/getting-started', '/docs/installation' ] );
} );

test( 'a batch drops only its excluded items and keeps the rest', function (): void {
	// Rejecting the whole request because one item is excluded would be its
	// own data-loss bug, so exclusion has to be decided per tracked item.
	$response = postBeacon( '/api/analytics/batch', [
		'items' => [
			batchPageView( '/docs/getting-started' ),
			batchPageView( '/admin/settings' ),
			batchPageView( '/docs/configuration' ),
		],
	] );

	$response->assertNoContent();

	expect( PageView::query()->pluck( 'path' )->all() )
		->toEqualCanonicalizing( [ '/docs/getting-started', '/docs/configuration' ] )
		->not->toContain( '/admin/settings' );
} );

test( 'a batch of entirely excluded page views records nothing', function (): void {
	$response = postBeacon( '/api/analytics/batch', [
		'items' => [
			batchPageView( '/admin/settings' ),
			batchPageView( '/admin/users' ),
		],
	] );

	$response->assertNoContent();

	expect( PageView::query()->count() )->toBe( 0 );
} );

test( 'single page view beacons still honour excluded paths', function (): void {
	// The exclusion moved from PrivacyFilter into TrackingService; this is the
	// behaviour that move must not regress.
	postBeacon( '/api/analytics/pageview', [
		'visitor_id' => 'visitor-excluded',
		'session_id' => '22222222-3333-4444-8555-666666666666',
		'path'       => '/admin/settings',
		'title'      => 'Settings',
	] )->assertNoContent();

	expect( PageView::query()->count() )->toBe( 0 );
} );

test( 'single page view beacons on a tracked path are recorded', function (): void {
	postBeacon( '/api/analytics/pageview', [
		'visitor_id' => 'visitor-tracked',
		'session_id' => '33333333-4444-4555-8666-777777777777',
		'path'       => '/docs/getting-started',
		'title'      => 'Getting Started',
	] )->assertNoContent();

	expect( PageView::query()->pluck( 'path' )->all() )->toBe( [ '/docs/getting-started' ] );
} );

test( 'events are filtered by the path they were fired on, not the ingest route', function (): void {
	postBeacon( '/api/analytics/event', [
		'visitor_id' => 'visitor-event',
		'session_id' => '44444444-5555-4666-8777-888888888888',
		'name'       => 'docs_code_copy',
		'path'       => '/docs/getting-started',
	] )->assertNoContent();

	postBeacon( '/api/analytics/event', [
		'visitor_id' => 'visitor-event-admin',
		'session_id' => '55555555-6666-4777-8888-999999999999',
		'name'       => 'admin_action',
		'path'       => '/admin/settings',
	] )->assertNoContent();

	expect( Event::query()->pluck( 'name' )->all() )->toBe( [ 'docs_code_copy' ] );
} );

test( 'a tracked path carrying a query string is matched on its path only', function (): void {
	postBeacon( '/api/analytics/batch', [
		'items' => [
			batchPageView( '/admin/settings?tab=general' ),
			batchPageView( '/docs/search?q=install' ),
		],
	] )->assertNoContent();

	expect( PageView::query()->pluck( 'path' )->all() )
		->toBe( [ '/docs/search?q=install' ] );
} );

test( 'an item with no path is kept rather than silently discarded', function (): void {
	// Nothing to match against is not the same as "excluded". Dropping these
	// would reintroduce invisible data loss through a different door.
	postBeacon( '/api/analytics/event', [
		'visitor_id' => 'visitor-pathless',
		'session_id' => '66666666-7777-4888-8999-aaaaaaaaaaaa',
		'name'       => 'pathless_event',
	] )->assertNoContent();

	expect( Event::query()->pluck( 'name' )->all() )->toBe( [ 'pathless_event' ] );
} );

test( 'the deprecated PrivacyFilter path hooks are still present and callable', function (): void {
	// Both methods were protected, so removing them would break any subclass
	// that calls or overrides them. 1.x cannot take that break, so they stay
	// until 2.0 — this test is what stops them being dropped by accident.
	$filter = new ReflectionClass( ArtisanPackUI\Analytics\Http\Middleware\PrivacyFilter::class );

	expect( $filter->hasMethod( 'isExcludedPath' ) )->toBeTrue()
		->and( $filter->hasMethod( 'pathMatches' ) )->toBeTrue();

	foreach ( [ 'isExcludedPath', 'pathMatches' ] as $name ) {
		expect( $filter->getMethod( $name )->isProtected() )
			->toBeTrue( "PrivacyFilter::{$name}() must stay protected for subclasses" )
			->and( $filter->getMethod( $name )->getDocComment() )
			->toContain( '@deprecated 1.5.0' );
	}
} );

test( 'a subclass overriding isExcludedPath still governs single beacons', function (): void {
	// The BC guarantee that matters: handle() must keep calling the overridable
	// hook, not just keep the method around as dead code.
	$filter = new class extends ArtisanPackUI\Analytics\Http\Middleware\PrivacyFilter {
		protected function isExcludedPath( Illuminate\Http\Request $request ): bool
		{
			return '/docs/blocked-by-subclass' === $request->input( 'path' );
		}
	};

	$request = Illuminate\Http\Request::create( '/api/analytics/pageview', 'POST', [
		'path' => '/docs/blocked-by-subclass',
	] );

	$response = $filter->handle( $request, fn (): Symfony\Component\HttpFoundation\Response => response( 'reached', 200 ) );

	expect( $response->getStatusCode() )->toBe( 204 );

	$allowed = Illuminate\Http\Request::create( '/api/analytics/pageview', 'POST', [
		'path' => '/docs/getting-started',
	] );

	expect( $filter->handle( $allowed, fn (): Symfony\Component\HttpFoundation\Response => response( 'reached', 200 ) )->getStatusCode() )
		->toBe( 200 );
} );

test( 'the deprecated middleware hook no longer matches the ingest route itself', function (): void {
	// The exact regression: a batch request has no top-level `path`, so the
	// hook must not fall back to `api/analytics/batch` and match `/api/*`.
	$filter  = new ArtisanPackUI\Analytics\Http\Middleware\PrivacyFilter();
	$request = Illuminate\Http\Request::create( '/api/analytics/batch', 'POST', [
		'items' => [ batchPageView( '/docs/getting-started' ) ],
	] );

	$response = $filter->handle( $request, fn (): Symfony\Component\HttpFoundation\Response => response( 'reached', 200 ) );

	expect( $response->getStatusCode() )->toBe( 200 );
} );

test( 'an empty exclusion list tracks everything', function (): void {
	config()->set( 'artisanpack.analytics.privacy.excluded_paths', [] );

	postBeacon( '/api/analytics/batch', [
		'items' => [
			batchPageView( '/admin/settings' ),
			batchPageView( '/docs/getting-started' ),
		],
	] )->assertNoContent();

	expect( PageView::query()->count() )->toBe( 2 );
} );
