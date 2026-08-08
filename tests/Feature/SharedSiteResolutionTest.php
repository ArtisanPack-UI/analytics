<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\AnalyticsServiceProvider;
use ArtisanPackUI\Analytics\Http\Middleware\ResolveSite;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Resolvers\HeaderResolver;
use ArtisanPackUI\Analytics\Services\TenantManager;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use ArtisanPackUI\Core\MultiTenancy\ChainSiteResolver;
use ArtisanPackUI\Core\MultiTenancy\HookSiteResolver;
use ArtisanPackUI\Core\MultiTenancy\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

uses( RefreshDatabase::class );

/**
 * Stands in for a sibling package that scopes its own data by site.
 *
 * It knows nothing about analytics: it reads the shared context and nothing
 * else, exactly as artisanpack-ui/bookings does. Any test where this and the
 * TenantManager disagree is the bug this refactor exists to prevent.
 */
final class SiblingPackageSiteConsumer
{
	public function __construct( private SiteContext $context )
	{
	}

	public function currentSiteId(): int|string|null
	{
		return $this->context->currentSiteId();
	}
}

/**
 * Create an active site.
 */
function sharedResolutionSite( string $name, ?string $domain = null ): Site
{
	return Site::create( [
		'name'      => $name,
		'domain'    => $domain,
		'is_active' => true,
	] );
}

/**
 * Create a goal belonging to the given site.
 */
function sharedResolutionGoal( string $name, int $siteId ): Goal
{
	return Goal::withoutSiteScopeCallback( fn () => Goal::create( [
		'name'       => $name,
		'site_id'    => $siteId,
		'type'       => 'page_view',
		'conditions' => [ 'path' => '/' ],
	] ) );
}

/**
 * Switch site resolution on with the given shared resolver list.
 *
 * @param array<int, class-string<SiteResolver>> $resolvers
 */
function useSharedResolvers( array $resolvers ): void
{
	config()->set( 'artisanpack.core.multi_tenant.enabled', true );
	config()->set( 'artisanpack.core.multi_tenant.resolvers', $resolvers );

	// The chain and the context are scoped bindings built from configuration,
	// so they have to be rebuilt once the configuration changes.
	app()->forgetInstance( SiteResolver::class );
	app()->forgetInstance( SiteContext::class );
	app()->forgetInstance( TenantManager::class );
}

/**
 * Run the legacy-configuration bridge the service provider runs at boot.
 */
function bridgeLegacyAnalyticsTenancy(): void
{
	$provider = new class( app() ) extends AnalyticsServiceProvider {
		public function bridge(): void
		{
			$this->bridgeLegacyMultiTenantConfig();
		}
	};

	$provider->bridge();
}

it( 'resolves the same site for analytics and a sibling package from one configuration', function (): void {
	$siteOne = sharedResolutionSite( 'Site One' );
	$siteTwo = sharedResolutionSite( 'Site Two' );

	useSharedResolvers( [ HeaderResolver::class ] );

	app()->instance( 'request', Request::create( '/', 'GET', [], [], [], [
		'HTTP_X_SITE_ID' => (string) $siteTwo->id,
	] ) );

	$analytics = app( TenantManager::class );
	$sibling   = new SiblingPackageSiteConsumer( app( SiteContext::class ) );

	expect( $analytics->currentId() )->toBe( $siteTwo->id )
		->and( $analytics->current()?->id )->toBe( $siteTwo->id )
		->and( $sibling->currentSiteId() )->toBe( $siteTwo->id )
		->and( $sibling->currentSiteId() )->not->toBe( $siteOne->id );
} );

it( 'shows a site pinned through analytics to a sibling package, and the reverse', function (): void {
	$site = sharedResolutionSite( 'Pinned' );

	useSharedResolvers( [] );

	$analytics = app( TenantManager::class );
	$sibling   = new SiblingPackageSiteConsumer( app( SiteContext::class ) );

	$analytics->setCurrent( $site );

	expect( $sibling->currentSiteId() )->toBe( $site->id );

	$analytics->forget();

	expect( $sibling->currentSiteId() )->toBeNull()
		->and( $analytics->hasCurrent() )->toBeFalse();

	app( SiteContext::class )->setSiteId( $site->id );

	expect( $analytics->currentId() )->toBe( $site->id )
		->and( $analytics->current()?->id )->toBe( $site->id );
} );

it( 'restores the surrounding context after forSite() and withoutSite()', function (): void {
	$outer = sharedResolutionSite( 'Outer' );
	$inner = sharedResolutionSite( 'Inner' );

	useSharedResolvers( [] );

	$analytics = app( TenantManager::class );
	$sibling   = new SiblingPackageSiteConsumer( app( SiteContext::class ) );

	$analytics->setCurrent( $outer );

	$seen = $analytics->forSite( $inner, fn () => $sibling->currentSiteId() );

	expect( $seen )->toBe( $inner->id )
		->and( $sibling->currentSiteId() )->toBe( $outer->id );

	$seen = $analytics->withoutSite( fn () => $sibling->currentSiteId() );

	expect( $seen )->toBeNull()
		->and( $sibling->currentSiteId() )->toBe( $outer->id );
} );

it( 'accepts a bare identifier for forSite() so console work needs no model', function (): void {
	$site = sharedResolutionSite( 'By identifier' );

	useSharedResolvers( [] );

	$analytics = app( TenantManager::class );

	$seen = $analytics->forSite( $site->id, fn () => $analytics->current()?->id );

	expect( $seen )->toBe( $site->id );
} );

it( 'releases the shared context on flush() for Octane', function (): void {
	$site = sharedResolutionSite( 'Octane' );

	useSharedResolvers( [] );

	$analytics = app( TenantManager::class );
	$analytics->setCurrent( $site );

	$analytics->flush();

	expect( app( SiteContext::class )->currentSiteId() )->toBeNull()
		->and( $analytics->current() )->toBeNull();
} );

it( 'exposes the shared resolver chain rather than a private one', function (): void {
	useSharedResolvers( [ HeaderResolver::class ] );

	$resolvers = app( TenantManager::class )->getResolvers();

	expect( app( SiteContext::class )->resolver() )->toBeInstanceOf( ChainSiteResolver::class )
		->and( $resolvers )->toHaveCount( 1 )
		->and( $resolvers[0] )->toBeInstanceOf( HeaderResolver::class );
} );

it( 'scopes models on the shared context', function (): void {
	$siteOne = sharedResolutionSite( 'Scoped One' );
	$siteTwo = sharedResolutionSite( 'Scoped Two' );

	useSharedResolvers( [] );

	$analytics = app( TenantManager::class );

	sharedResolutionGoal( 'Goal One', $siteOne->id );
	sharedResolutionGoal( 'Goal Two', $siteTwo->id );

	// The site the scope reads is the shared one, so pinning it from outside
	// this package still scopes analytics queries.
	app( SiteContext::class )->setSiteId( $siteOne->id );

	expect( Goal::forCurrentSite()->pluck( 'name' )->all() )->toBe( [ 'Goal One' ] )
		->and( Goal::allSites()->count() )->toBe( 2 )
		->and( Goal::withoutSiteScope()->count() )->toBe( 2 )
		->and( Goal::query()->pluck( 'name' )->all() )->toBe( [ 'Goal One' ] );
} );

it( 'assigns the shared context site to new records', function (): void {
	$site = sharedResolutionSite( 'Creating' );

	useSharedResolvers( [] );

	app( SiteContext::class )->setSiteId( $site->id );

	$goal = Goal::create( [
		'name'       => 'Assigned',
		'type'       => 'page_view',
		'conditions' => [ 'path' => '/assigned' ],
	] );

	expect( $goal->site_id )->toBe( $site->id );
} );

it( 'falls back to the configured default site when nothing resolves', function (): void {
	$site = sharedResolutionSite( 'Default' );

	useSharedResolvers( [] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $site->id );

	$analytics = app( TenantManager::class );

	expect( $analytics->currentId() )->toBe( $site->id )
		->and( $analytics->current()?->id )->toBe( $site->id );
} );

it( 'ignores a default site that is missing or inactive', function (): void {
	$inactive = Site::create( [
		'name'      => 'Inactive',
		'is_active' => false,
	] );

	useSharedResolvers( [] );

	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $inactive->id );
	expect( app( TenantManager::class )->currentId() )->toBeNull();

	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', 9999 );
	app()->forgetInstance( TenantManager::class );

	expect( app( TenantManager::class )->currentId() )->toBeNull();
} );

it( 'does not let the default site stand in for an explicit no-site', function (): void {
	$site = sharedResolutionSite( 'Default' );

	useSharedResolvers( [] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $site->id );

	$analytics = app( TenantManager::class );

	expect( $analytics->withoutSite( fn () => $analytics->currentId() ) )->toBeNull()
		->and( $analytics->currentId() )->toBe( $site->id );

	$analytics->setCurrent( null );

	expect( $analytics->currentId() )->toBeNull();
} );

it( 'bridges a pre-1.5 analytics tenancy configuration onto the shared one', function (): void {
	// The configuration an installation upgrading from 1.4 arrives with:
	// tenancy switched on and resolvers listed under the analytics key, with
	// the shared switch still at its default of off.
	config()->set( 'artisanpack.analytics.multi_tenant.enabled', true );
	config()->set( 'artisanpack.analytics.multi_tenant.resolvers', [ HeaderResolver::class ] );
	config()->set( 'artisanpack.core.multi_tenant.enabled', false );
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [ HookSiteResolver::class ] );

	bridgeLegacyAnalyticsTenancy();

	expect( config( 'artisanpack.core.multi_tenant.enabled' ) )->toBeTrue()
		// The legacy list goes first, so a request that resolved by header
		// before the upgrade still resolves by header after it.
		->and( config( 'artisanpack.core.multi_tenant.resolvers' ) )
		->toBe( [ HeaderResolver::class, HookSiteResolver::class ] );
} );

it( 'leaves a migrated configuration alone', function (): void {
	config()->set( 'artisanpack.analytics.multi_tenant.enabled', false );
	config()->set( 'artisanpack.analytics.multi_tenant.resolvers', [ HeaderResolver::class ] );
	config()->set( 'artisanpack.core.multi_tenant.enabled', true );
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [ HookSiteResolver::class ] );

	bridgeLegacyAnalyticsTenancy();

	expect( config( 'artisanpack.core.multi_tenant.resolvers' ) )->toBe( [ HookSiteResolver::class ] );
} );

it( 'bridges nothing twice when the legacy resolvers are already shared', function (): void {
	config()->set( 'artisanpack.analytics.multi_tenant.enabled', true );
	config()->set( 'artisanpack.analytics.multi_tenant.resolvers', [ HeaderResolver::class ] );
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [ HeaderResolver::class ] );

	bridgeLegacyAnalyticsTenancy();
	bridgeLegacyAnalyticsTenancy();

	expect( config( 'artisanpack.core.multi_tenant.resolvers' ) )->toBe( [ HeaderResolver::class ] );
} );

it( 'shares the context-resolved site through the ResolveSite middleware', function (): void {
	$site = sharedResolutionSite( 'Middleware', 'middleware.test' );

	useSharedResolvers( [ HeaderResolver::class ] );

	$request = Request::create( '/', 'GET', [], [], [], [
		'HTTP_X_SITE_ID' => (string) $site->id,
	] );

	app()->instance( 'request', $request );

	$response = app( ResolveSite::class )->handle( $request, fn () => new Response );

	expect( $response->getStatusCode() )->toBe( 200 )
		->and( $request->attributes->get( 'site_id' ) )->toBe( $site->id )
		->and( $request->attributes->get( 'site' )?->id )->toBe( $site->id )
		->and( View::shared( 'currentSiteId' ) )->toBe( $site->id );
} );

it( 'leaves the request alone when tenancy is switched off everywhere', function (): void {
	sharedResolutionSite( 'Unused', 'unused.test' );

	config()->set( 'artisanpack.analytics.multi_tenant.enabled', false );
	config()->set( 'artisanpack.core.multi_tenant.enabled', false );

	$request  = Request::create( '/', 'GET' );
	$response = app( ResolveSite::class )->handle( $request, fn () => new Response );

	expect( $response->getStatusCode() )->toBe( 200 )
		->and( $request->attributes->has( 'site_id' ) )->toBeFalse();
} );

it( 'does not resurrect a soft-deleted site as the current one', function (): void {
	$site = sharedResolutionSite( 'Deleted' );

	useSharedResolvers( [] );

	app( SiteContext::class )->setSiteId( $site->id );
	$site->delete();

	expect( app( TenantManager::class )->current() )->toBeNull();
} );

it( 'ignores a soft-deleted default site', function (): void {
	$site = sharedResolutionSite( 'Deleted default' );
	$site->delete();

	useSharedResolvers( [] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $site->id );

	expect( app( TenantManager::class )->currentId() )->toBeNull();
} );

it( 'pins the resolved site for the rest of the request', function (): void {
	$site = sharedResolutionSite( 'Pinned by middleware' );

	useSharedResolvers( [ HeaderResolver::class ] );

	$request = Request::create( '/', 'GET', [], [], [], [
		'HTTP_X_SITE_ID' => (string) $site->id,
	] );

	app()->instance( 'request', $request );
	app( ResolveSite::class )->handle( $request, fn () => new Response );

	// Nothing left for a resolver to answer from, so a site still in context
	// can only have come from the pin the middleware set.
	app()->instance( 'request', Request::create( '/', 'GET' ) );

	expect( app( SiteContext::class )->currentSiteId() )->toBe( $site->id );
} );

it( 'leaves queries unscoped when the shared site is not an analytics site ID', function (): void {
	$default = sharedResolutionSite( 'Default' );

	useSharedResolvers( [] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $default->id );

	// A sibling package's resolver can legitimately name a site by slug.
	app( SiteContext::class )->setSiteId( 'acme' );

	$analytics = app( TenantManager::class );

	// Not the default site: a site is in context, it is simply not one this
	// package has a record for, and filing the work under a different site
	// would be worse than not scoping it.
	expect( $analytics->currentId() )->toBeNull()
		->and( $analytics->current() )->toBeNull();
} );

it( 'restores the default-site suppression after withoutSite()', function (): void {
	$default = sharedResolutionSite( 'Default' );

	useSharedResolvers( [] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $default->id );

	$analytics = app( TenantManager::class );

	// A callback that pins "no site" must not leave that suppression behind
	// for the rest of the request.
	$analytics->withoutSite( function () use ( $analytics ): void {
		$analytics->setCurrent( null );
	} );

	expect( $analytics->currentId() )->toBe( $default->id );
} );

it( 'rejects identifiers that only look numeric', function ( int|string $siteId ): void {
	useSharedResolvers( [] );

	app( SiteContext::class )->setSiteId( $siteId );

	expect( app( TenantManager::class )->currentId() )->toBeNull();
} )->with( [
	'decimal'     => '12.5',
	'exponent'    => '1e3',
	'padded'      => ' 12',
	'negative'    => '-3',
	'hexadecimal' => '0x1A',
] );

it( 'accepts an integer-shaped identifier from another package', function (): void {
	$site = sharedResolutionSite( 'Stringly typed' );

	useSharedResolvers( [] );

	app( SiteContext::class )->setSiteId( (string) $site->id );

	expect( app( TenantManager::class )->currentId() )->toBe( $site->id );
} );

it( 'scopes models when tenancy is switched on after the model booted', function (): void {
	$site = sharedResolutionSite( 'Late' );

	// Boot the model while tenancy is off, the way a model touched by another
	// provider during boot would be.
	config()->set( 'artisanpack.analytics.multi_tenant.enabled', false );
	config()->set( 'artisanpack.core.multi_tenant.enabled', false );
	Goal::query()->count();

	sharedResolutionGoal( 'Scoped late', $site->id );
	sharedResolutionGoal( 'Other site', $site->id + 100 );

	useSharedResolvers( [] );
	app( SiteContext::class )->setSiteId( $site->id );

	expect( Goal::query()->pluck( 'name' )->all() )->toBe( [ 'Scoped late' ] );
} );

it( 'does not publish the analytics default site into the shared context', function (): void {
	$default = sharedResolutionSite( 'Default' );

	useSharedResolvers( [ HeaderResolver::class ] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $default->id );

	$request = Request::create( '/', 'GET' );
	app()->instance( 'request', $request );

	app( ResolveSite::class )->handle( $request, fn () => new Response );

	// Analytics still falls back to its own default...
	expect( app( TenantManager::class )->currentId() )->toBe( $default->id )
		->and( $request->attributes->get( 'site_id' ) )->toBe( $default->id )
		// ...but a sibling package must not be scoped to a default it never
		// configured.
		->and( app( SiteContext::class )->currentSiteId() )->toBeNull();
} );
