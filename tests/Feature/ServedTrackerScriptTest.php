<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/**
 * The tracker route is the only way the browser ever sees the tracker's
 * configuration, so anything the server can switch on has to survive the trip
 * through this response. The JS harnesses inject config straight into their VM,
 * which is why they stayed green while nothing reached a real browser.
 */

it( 'serves the tracker to an unauthenticated visitor', function (): void {
	// Every visitor's browser fetches this before anyone has logged in — and
	// anonymous mode exists precisely to track people who never will.
	$this->get( route( 'analytics.tracker.script' ) )->assertOk();
	$this->get( route( 'analytics.tracker.script.min' ) )->assertOk();
} );

it( 'puts no middleware in front of the tracker routes', function (): void {
	// A dashboard gate or `auth` here is what previously made the script
	// unreachable; asserting on the route itself catches a regression that a
	// 200 in the tests above would not, since the test suite runs unauthenticated
	// only because nothing has logged in.
	$routes = collect( Route::getRoutes()->getRoutes() )
		->filter( fn ( $route ) => str_starts_with( (string) $route->getName(), 'analytics.tracker.script' ) );

	expect( $routes )->toHaveCount( 2 );

	$routes->each( fn ( $route ) => expect( $route->gatherMiddleware() )->toBe( [] ) );
} );

it( 'carries anonymous mode through to the served script', function (): void {
	config()->set( 'artisanpack.analytics.privacy.anonymous_mode', true );

	$body = $this->get( route( 'analytics.tracker.script' ) )->assertOk()->getContent();

	expect( $body )->toContain( '"anonymousMode":true' );
} );

it( 'carries the History API opt-out through to the served script', function (): void {
	config()->set( 'artisanpack.analytics.tracker.track_history_changes', false );

	$body = $this->get( route( 'analytics.tracker.script' ) )->assertOk()->getContent();

	expect( $body )->toContain( '"trackHistoryChanges":false' );
} );

it( 'defaults both 1.5 toggles to the configured defaults', function (): void {
	$body = $this->get( route( 'analytics.tracker.script' ) )->assertOk()->getContent();

	expect( $body )->toContain( '"anonymousMode":false' )
		->and( $body )->toContain( '"trackHistoryChanges":true' );
} );

it( 'merges into an inline config the page set rather than clobbering it', function (): void {
	$body = $this->get( route( 'analytics.tracker.script' ) )->assertOk()->getContent();

	expect( $body )->toContain( 'window.__ARTISANPACK_ANALYTICS_CONFIG__ = Object.assign(' )
		->and( $body )->toContain( 'window.__ARTISANPACK_ANALYTICS_CONFIG__ || {});' );
} );

it( 'serves the full tracker on the minified route when no minified asset exists', function (): void {
	expect( file_exists( __DIR__ . '/../../resources/js/tracker.min.js' ) )->toBeFalse();

	$body = $this->get( route( 'analytics.tracker.script.min' ) )->assertOk()->getContent();

	// Markers only the real tracker carries; the inline fallback stub has none
	// of them, and shipping the stub means shipping none of 1.5's features.
	expect( $body )->toContain( '_upgradeToIdentifiedTracking' )
		->and( $body )->toContain( 'trackHistoryChanges' );
} );

it( 'honours anonymous mode on the minified route too', function (): void {
	config()->set( 'artisanpack.analytics.privacy.anonymous_mode', true );

	$body = $this->get( route( 'analytics.tracker.script.min' ) )->assertOk()->getContent();

	expect( $body )->toContain( '"anonymousMode":true' );
} );

it( 'emits page-level tracker overrides ahead of the tracker script', function (): void {
	$html = Blade::render( '@analyticsScripts([ "anonymousMode" => true ])' );

	$overrideAt = strpos( $html, 'window.__ARTISANPACK_ANALYTICS_CONFIG__' );
	$scriptAt   = strpos( $html, '<script src=' );

	// The served script merges onto whatever the page already set, so the
	// override has to be in place before it loads.
	expect( $html )->toContain( '"anonymousMode":true' )
		->and( $overrideAt )->toBeLessThan( $scriptAt );
} );

it( 'emits no override block when the directive is given nothing', function (): void {
	expect( Blade::render( '@analyticsScripts' ) )
		->not->toContain( 'window.__ARTISANPACK_ANALYTICS_CONFIG__' );
} );

it( 'accepts page-level overrides through the component too', function (): void {
	$html = Blade::render( '<x-artisanpack-analytics::tracker-script :config="[ \'anonymousMode\' => true ]" />' );

	expect( $html )->toContain( '"anonymousMode":true' );
} );
