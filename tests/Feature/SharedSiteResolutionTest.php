<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\AnalyticsServiceProvider;
use ArtisanPackUI\Analytics\Http\Middleware\ResolveSite;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Resolvers\ApiKeyResolver;
use ArtisanPackUI\Analytics\Resolvers\DomainResolver;
use ArtisanPackUI\Analytics\Resolvers\HeaderResolver;
use ArtisanPackUI\Analytics\Services\TenantManager;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use ArtisanPackUI\Core\MultiTenancy\ChainSiteResolver;
use ArtisanPackUI\Core\MultiTenancy\HookSiteResolver;
use ArtisanPackUI\Core\MultiTenancy\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
 * A site resolver exactly as an application would have written it against 1.4:
 * the two methods the deprecated interface asked for, and nothing else. The
 * whole point is that it does not implement core's contract.
 */
final class LegacyShapeResolver
{
	public function resolve( Request $request ): ?Site
	{
		return Site::query()->where( 'domain', $request->getHost() )->first();
	}

	public function priority(): int
	{
		return 50;
	}
}

/**
 * Application code that predates the shared contract and is entitled to fail
 * in its own way. Analytics must not turn that into a failure of whatever
 * unrelated query happened to trigger resolution.
 */
final class ThrowingLegacyResolver
{
	public function resolve( Request $request ): ?Site
	{
		throw new RuntimeException( 'legacy resolver exploded' );
	}

	public function priority(): int
	{
		return 50;
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

	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );
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
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [] );

	bridgeLegacyAnalyticsTenancy();

	expect( config( 'artisanpack.core.multi_tenant.enabled' ) )->toBeTrue()
		->and( config( 'artisanpack.core.multi_tenant.resolvers' ) )
		->toBe( [ HeaderResolver::class ] );
} );

it( 'refuses to reorder a shared resolver list the application configured', function (): void {
	// A populated shared list is an application that migrated. Prepending onto
	// it puts analytics' shipped defaults in front of resolvers the application
	// chose, silently changing which site *every* package resolves.
	config()->set( 'artisanpack.analytics.multi_tenant.enabled', true );
	config()->set( 'artisanpack.analytics.multi_tenant.resolvers', [ HeaderResolver::class ] );
	config()->set( 'artisanpack.core.multi_tenant.enabled', false );
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [ HookSiteResolver::class ] );

	bridgeLegacyAnalyticsTenancy();

	expect( config( 'artisanpack.core.multi_tenant.resolvers' ) )
		->toBe( [ HookSiteResolver::class ] )
		// The flag still bridges: an upgrade that leaves tenancy switched off
		// entirely is the failure the bridge exists to prevent.
		->and( config( 'artisanpack.core.multi_tenant.enabled' ) )->toBeTrue();
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

	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );
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

	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );
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

it( 'resolves for a real request while the process reports it is in console', function (): void {
	$site = sharedResolutionSite( 'Octane', 'octane.test' );

	useSharedResolvers( [ DomainResolver::class ] );

	// An Octane, RoadRunner, or Swoole worker runs under the CLI SAPI, so
	// `runningInConsole()` is true for every HTTP request it serves. Keying the
	// console guard on the SAPI left every request-driven resolver inert there
	// and pooled every site's traffic into whatever `default_site_id` said.
	expect( app()->runningInConsole() )->toBeTrue();

	app()->instance( 'request', Request::create( 'https://octane.test/pricing', 'GET' ) );

	expect( app( TenantManager::class )->currentId() )->toBe( $site->id )
		->and( app( SiteContext::class )->currentSiteId() )->toBe( $site->id );
} );

it( 'stays out of the way of the request a console command synthesises', function (): void {
	sharedResolutionSite( 'Console', 'localhost' );

	useSharedResolvers( [ DomainResolver::class ] );

	// What Laravel binds in console: built from argv, carrying whatever host
	// the machine reports. Attaching a scheduled command's work to whichever
	// site happens to match is the mis-attribution the guard exists to stop.
	$console = Request::create( 'http://localhost', 'GET', [], [], [], [
		'argv' => [ 'artisan', 'analytics:cleanup' ],
	] );
	app()->instance( 'request', $console );

	expect( app( SiteContext::class )->currentSiteId() )->toBeNull();
} );

it( 'keeps a resolver written against the 1.4 shape resolving', function (): void {
	$site = sharedResolutionSite( 'Legacy', 'legacy.test' );

	config()->set( 'artisanpack.analytics.multi_tenant.enabled', true );
	config()->set( 'artisanpack.analytics.multi_tenant.resolvers', [ LegacyShapeResolver::class ] );
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [] );

	bridgeLegacyAnalyticsTenancy();

	config()->set( 'artisanpack.core.multi_tenant.enabled', true );
	app()->forgetInstance( SiteResolver::class );
	app()->forgetInstance( SiteContext::class );
	app()->forgetInstance( TenantManager::class );

	app()->instance( 'request', Request::create( 'https://legacy.test/', 'GET' ) );

	// Core builds the chain with `make()` and rejects anything that is not a
	// SiteResolver, so without the adapter this is a hard failure on the first
	// scoped query of an upgraded application.
	expect( app( SiteContext::class )->currentSiteId() )->toBe( $site->id )
		->and( app( TenantManager::class )->currentId() )->toBe( $site->id );
} );

it( 'answers no site rather than throwing when a legacy resolver blows up', function (): void {
	config()->set( 'artisanpack.analytics.multi_tenant.enabled', true );
	config()->set( 'artisanpack.analytics.multi_tenant.resolvers', [ ThrowingLegacyResolver::class ] );
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [] );

	bridgeLegacyAnalyticsTenancy();

	config()->set( 'artisanpack.core.multi_tenant.enabled', true );
	app()->forgetInstance( SiteResolver::class );
	app()->forgetInstance( SiteContext::class );

	app()->instance( 'request', Request::create( 'https://legacy.test/', 'GET' ) );

	expect( app( SiteContext::class )->currentSiteId() )->toBeNull();
} );

it( 'writes api key usage once per request however often it resolves', function (): void {
	$site   = sharedResolutionSite( 'Api key' );
	$apiKey = $site->generateApiKey();
	$site->save();

	useSharedResolvers( [ ApiKeyResolver::class ] );

	$request = Request::create( '/', 'GET', [], [], [], [
		'HTTP_AUTHORIZATION' => 'Bearer ' . $apiKey,
	] );
	app()->instance( 'request', $request );

	$writes = 0;
	DB::listen( function ( $query ) use ( &$writes ): void {
		if ( str_starts_with( strtolower( ltrim( $query->sql ) ), 'update "analytics_sites"' ) ) {
			$writes++;
		}
	} );

	// The shared contract re-resolves on every call by design, and the site
	// scope resolves once per scoped query — so an unthrottled write here is an
	// UPDATE per SELECT for the whole request.
	$context = app( SiteContext::class );

	expect( $context->currentSiteId() )->toBe( $site->id )
		->and( $context->currentSiteId() )->toBe( $site->id )
		->and( $context->currentSiteId() )->toBe( $site->id )
		->and( $writes )->toBe( 1 );
} );

it( 'leaves a freshly recorded api key usage alone', function (): void {
	$site = sharedResolutionSite( 'Recently used' );
	$site->generateApiKey();
	$site->recordApiKeyUsage();

	$firstRecordedAt = $site->fresh()->api_key_last_used_at;

	$site->fresh()->recordApiKeyUsage();

	expect( $site->fresh()->api_key_last_used_at->eq( $firstRecordedAt ) )->toBeTrue();
} );

it( 'records api key usage again once the recorded time is stale', function (): void {
	$site = sharedResolutionSite( 'Stale' );
	$site->generateApiKey();
	$site->api_key_last_used_at = now()->subSeconds( Site::API_KEY_USAGE_INTERVAL + 1 );
	$site->save();

	$site->fresh()->recordApiKeyUsage();

	expect( $site->fresh()->api_key_last_used_at->gt( now()->subSeconds( 5 ) ) )->toBeTrue();
} );

it( 'respects a sibling package pinning no site over the analytics default', function (): void {
	$default = sharedResolutionSite( 'Default' );

	useSharedResolvers( [] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $default->id );

	sharedResolutionGoal( 'Default site goal', $default->id );
	sharedResolutionGoal( 'Other site goal', $default->id + 100 );

	// A pin made straight on the shared context, by a package that knows
	// nothing about analytics. "No site" is an instruction, not an absence, so
	// the analytics default must not quietly take its place.
	app( SiteContext::class )->setSiteId( null );

	expect( app( TenantManager::class )->currentId() )->toBeNull()
		->and( Goal::query()->pluck( 'name' )->all() )
		->toBe( [ 'Default site goal', 'Other site goal' ] );
} );

it( 'still falls back to the analytics default when nothing is pinned', function (): void {
	$default = sharedResolutionSite( 'Default' );

	useSharedResolvers( [] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $default->id );

	expect( app( TenantManager::class )->currentId() )->toBe( $default->id );
} );

it( 'returns to the analytics default once a null pin is released', function (): void {
	$default = sharedResolutionSite( 'Default' );

	useSharedResolvers( [] );
	config()->set( 'artisanpack.analytics.multi_tenant.default_site_id', $default->id );

	$context = app( SiteContext::class );
	$context->setSiteId( null );

	expect( app( TenantManager::class )->currentId() )->toBeNull();

	$context->forget();

	expect( app( TenantManager::class )->currentId() )->toBe( $default->id );
} );
