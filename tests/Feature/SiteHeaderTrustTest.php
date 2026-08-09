<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\AnalyticsServiceProvider;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Resolvers\HeaderResolver;
use ArtisanPackUI\Analytics\Services\TenantManager;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use ArtisanPackUI\Core\MultiTenancy\SiteContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

uses( RefreshDatabase::class );

/**
 * Create an active site.
 */
function headerTrustSite( string $name ): Site
{
	return Site::create( [
		'name'      => $name,
		'is_active' => true,
	] );
}

/**
 * Build a request carrying a site header, optionally from a given address.
 */
function headerTrustRequest( int|string $siteIdentifier, ?string $from = null ): Request
{
	$server = [ 'HTTP_X_SITE_ID' => (string) $siteIdentifier ];

	if ( null !== $from ) {
		$server['REMOTE_ADDR'] = $from;
	}

	return Request::create( '/dashboard', 'GET', [], [], [], $server );
}

/**
 * Switch shared site resolution on with the header resolver in the chain.
 */
function useHeaderResolverChain(): void
{
	config()->set( 'artisanpack.core.multi_tenant.enabled', true );
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [ HeaderResolver::class ] );

	app()->forgetInstance( SiteResolver::class );
	app()->forgetInstance( SiteContext::class );
	app()->forgetInstance( TenantManager::class );
}

it( 'ignores a site header nobody was permitted to send', function (): void {
	$site = headerTrustSite( 'Spoof target' );

	// The shipped default: the gate is closed, so the header names nothing.
	expect( config( 'artisanpack.analytics.multi_tenant.trust_site_header' ) )->toBeFalse();

	$resolved = ( new HeaderResolver )->resolve( headerTrustRequest( $site->id ) );

	expect( $resolved )->toBeNull();
} );

it( 'keeps a spoofed header out of the context every package reads', function (): void {
	$site = headerTrustSite( 'Sibling package data' );

	useHeaderResolverChain();
	app()->instance( 'request', headerTrustRequest( $site->id ) );

	expect( app( SiteContext::class )->currentSiteId() )->toBeNull()
		->and( app( TenantManager::class )->currentId() )->toBeNull();
} );

it( 'resolves the header once an operator has trusted it', function (): void {
	$site = headerTrustSite( 'Trusted' );

	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );
	useHeaderResolverChain();
	app()->instance( 'request', headerTrustRequest( $site->id ) );

	expect( ( new HeaderResolver )->resolve( headerTrustRequest( $site->id ) )?->id )->toBe( $site->id )
		->and( app( SiteContext::class )->currentSiteId() )->toBe( $site->id );
} );

it( 'resolves a trusted header naming a site by UUID', function (): void {
	$site = headerTrustSite( 'By UUID' );

	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );

	expect( ( new HeaderResolver )->resolve( headerTrustRequest( $site->uuid ) )?->id )->toBe( $site->id );
} );

it( 'answers only for addresses on the allowlist', function (): void {
	$site = headerTrustSite( 'Behind the gateway' );

	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );
	config()->set( 'artisanpack.analytics.multi_tenant.trusted_site_header_ips', [ '10.0.0.0/8', '192.168.1.5' ] );

	$resolver = new HeaderResolver;

	expect( $resolver->resolve( headerTrustRequest( $site->id, '10.4.2.1' ) )?->id )->toBe( $site->id )
		->and( $resolver->resolve( headerTrustRequest( $site->id, '192.168.1.5' ) )?->id )->toBe( $site->id )
		->and( $resolver->resolve( headerTrustRequest( $site->id, '192.168.1.6' ) ) )->toBeNull()
		->and( $resolver->resolve( headerTrustRequest( $site->id, '203.0.113.9' ) ) )->toBeNull();
} );

it( 'matches the allowlist on the peer address, not a forwarded-for header', function (): void {
	$site = headerTrustSite( 'Forwarded' );

	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );
	config()->set( 'artisanpack.analytics.multi_tenant.trusted_site_header_ips', [ '10.0.0.1' ] );

	// Trusting every proxy is a real (if unwise) configuration, and it makes
	// Request::ip() caller-controlled. The allowlist must not be.
	Request::setTrustedProxies( [ '0.0.0.0/0' ], Request::HEADER_X_FORWARDED_FOR );

	$spoofed = Request::create( '/dashboard', 'GET', [], [], [], [
		'HTTP_X_SITE_ID'       => (string) $site->id,
		'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
		'REMOTE_ADDR'          => '203.0.113.9',
	] );

	$legitimate = headerTrustRequest( $site->id, '10.0.0.1' );

	try {
		expect( $spoofed->ip() )->toBe( '10.0.0.1' )
			->and( ( new HeaderResolver )->resolve( $spoofed ) )->toBeNull()
			->and( ( new HeaderResolver )->resolve( $legitimate )?->id )->toBe( $site->id );
	} finally {
		Request::setTrustedProxies( [], Request::HEADER_X_FORWARDED_FOR );
	}
} );

it( 'reads a comma-separated allowlist from the environment', function (): void {
	$site = headerTrustSite( 'From env' );

	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );
	config()->set( 'artisanpack.analytics.multi_tenant.trusted_site_header_ips', ' 10.0.0.1 , 10.0.0.2 ' );

	$resolver = new HeaderResolver;

	expect( $resolver->resolve( headerTrustRequest( $site->id, '10.0.0.2' ) )?->id )->toBe( $site->id )
		->and( $resolver->resolve( headerTrustRequest( $site->id, '10.0.0.3' ) ) )->toBeNull();
} );

it( 'records an ignored header once per request rather than once per query', function (): void {
	$site    = headerTrustSite( 'Logged' );
	$request = headerTrustRequest( $site->id );

	Log::shouldReceive( 'notice' )->once();

	$resolver = new HeaderResolver;
	$resolver->resolve( $request );
	$resolver->resolve( $request );
	( new HeaderResolver )->resolve( $request );
} );

it( 'truncates the rejected header value it logs', function (): void {
	$logged = null;

	Log::shouldReceive( 'notice' )->once()->andReturnUsing( function ( string $message, array $context ) use ( &$logged ): void {
		$logged = $context;
	} );

	( new HeaderResolver )->resolve( headerTrustRequest( str_repeat( 'a', 4096 ) ) );

	expect( $logged['value'] )->toBe( str_repeat( 'a', 100 ) . '...' )
		->and( $logged['peer'] )->toBe( '127.0.0.1' );
} );

it( 'says nothing when no header was sent at all', function (): void {
	Log::shouldReceive( 'notice' )->never();

	expect( ( new HeaderResolver )->resolve( Request::create( '/dashboard' ) ) )->toBeNull();
} );

it( 'does not widen the trust boundary when the legacy bridge runs on upgrade', function (): void {
	$site = headerTrustSite( 'Upgraded install' );

	// A 1.4 installation as it arrives: tenancy on under the analytics key,
	// with the shipped resolver list still in place.
	config()->set( 'artisanpack.analytics.multi_tenant.enabled', true );
	config()->set( 'artisanpack.analytics.multi_tenant.resolvers', [ HeaderResolver::class ] );
	config()->set( 'artisanpack.core.multi_tenant.enabled', false );
	// An empty shared list is the upgrade the bridge is for; a populated one is
	// an application that already migrated, and the bridge leaves that alone.
	config()->set( 'artisanpack.core.multi_tenant.resolvers', [] );

	$provider = new class( app() ) extends AnalyticsServiceProvider {
		public function bridge(): void
		{
			$this->bridgeLegacyMultiTenantConfig();
		}
	};

	$provider->bridge();

	app()->forgetInstance( SiteResolver::class );
	app()->forgetInstance( SiteContext::class );
	app()->forgetInstance( TenantManager::class );

	app()->instance( 'request', headerTrustRequest( $site->id ) );

	// The header resolver is bridged onto the shared list — and answers
	// nothing, because the bridge carries a resolver list, not a decision to
	// trust the caller.
	expect( config( 'artisanpack.core.multi_tenant.resolvers' ) )
		->toBe( [ HeaderResolver::class ] )
		->and( app( SiteContext::class )->currentSiteId() )->toBeNull();

	// Opting in is what turns it back on.
	config()->set( 'artisanpack.analytics.multi_tenant.trust_site_header', true );

	expect( app( SiteContext::class )->currentSiteId() )->toBe( $site->id );
} );
