<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Auth\ApiKeyGuard;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\SiteSettingsService;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'auth.guards.analytics-api', [ 'driver' => 'analytics-api' ] );
} );

/**
 * Queue workers and Octane workers keep one container alive across many jobs
 * and requests. Laravel forgets *scoped* instances at each of those boundaries,
 * which is what stops a site pinned by one job scoping the next — but only for
 * services that are themselves scoped. Anything that captures a scoped instance
 * and outlives it puts the leak straight back.
 */

/**
 * Create an active site.
 */
function scopedStateSite( string $name ): Site
{
	return Site::create( [
		'name'      => $name,
		'is_active' => true,
	] );
}

/**
 * End the current request or job the way the framework does between them.
 */
function endScopedLifecycle(): void
{
	app()->forgetScopedInstances();
}

it( 'reads settings for the site the current job pinned, not a previous one', function (): void {
	$first  = scopedStateSite( 'First job' );
	$second = scopedStateSite( 'Second job' );

	$first->setSetting( 'tracking.enabled', true )->save();
	$second->setSetting( 'tracking.enabled', false )->save();

	app( TenantManager::class )->setCurrent( $first );

	expect( app( SiteSettingsService::class )->get( 'tracking.enabled' ) )->toBeTrue();

	endScopedLifecycle();

	app( TenantManager::class )->setCurrent( $second );

	expect( app( SiteSettingsService::class )->get( 'tracking.enabled' ) )->toBeFalse();
} );

it( 'does not hold a forgotten tenant manager after the job that built it', function (): void {
	$first  = scopedStateSite( 'First' );
	$second = scopedStateSite( 'Second' );

	app( TenantManager::class )->setCurrent( $first );
	$captured = app( SiteSettingsService::class );

	endScopedLifecycle();

	app( TenantManager::class )->setCurrent( $second );

	// Resolving again must not hand back the instance that captured job one's
	// TenantManager — that is the whole failure mode.
	expect( app( SiteSettingsService::class ) )->not->toBe( $captured );
} );

it( 'authenticates the api key of the request in hand, not the one before it', function (): void {
	$first  = scopedStateSite( 'First caller' );
	$second = scopedStateSite( 'Second caller' );

	$firstRequest = Request::create( '/api/analytics/track', 'POST' );
	$firstRequest->attributes->set( 'site', $first );
	app()->instance( 'request', $firstRequest );

	// AuthManager memoises the guard, so this is the same object for the rest
	// of the worker's life.
	$guard = app( 'auth' )->guard( 'analytics-api' );

	expect( $guard )->toBeInstanceOf( ApiKeyGuard::class )
		->and( $guard->user()?->id )->toBe( $first->id );

	endScopedLifecycle();

	$secondRequest = Request::create( '/api/analytics/track', 'POST' );
	$secondRequest->attributes->set( 'site', $second );
	app()->instance( 'request', $secondRequest );

	expect( app( 'auth' )->guard( 'analytics-api' )->user()?->id )->toBe( $second->id );
} );

it( 'pins the site it authenticates onto the current tenant manager', function (): void {
	$first  = scopedStateSite( 'First caller' );
	$second = scopedStateSite( 'Second caller' );

	$firstRequest = Request::create( '/api/analytics/track', 'POST' );
	$firstRequest->attributes->set( 'site', $first );
	app()->instance( 'request', $firstRequest );

	app( 'auth' )->guard( 'analytics-api' )->user();

	endScopedLifecycle();

	$secondRequest = Request::create( '/api/analytics/track', 'POST' );
	$secondRequest->attributes->set( 'site', $second );
	app()->instance( 'request', $secondRequest );

	app( 'auth' )->guard( 'analytics-api' )->user();

	// A guard holding job one's TenantManager would have pinned site two onto a
	// manager nothing else can see, leaving this one answering null.
	expect( app( TenantManager::class )->currentId() )->toBe( $second->id );
} );
