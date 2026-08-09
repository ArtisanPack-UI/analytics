<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\AnonymousPageView;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses( RefreshDatabase::class );

const ANON_TEST_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.privacy.anonymous_mode', true );
	config()->set( 'artisanpack.analytics.privacy.excluded_paths', [ '/admin/*', '/api/*' ] );
} );

/**
 * Post an anonymous beacon as a real browser would.
 *
 * @param array<string, mixed> $payload The beacon payload.
 * @param array<string, string> $headers Extra request headers.
 */
function postAnonymous( array $payload, array $headers = [] ): Illuminate\Testing\TestResponse
{
	return test()
		->withHeaders( array_merge( [ 'User-Agent' => ANON_TEST_AGENT ], $headers ) )
		->postJson( '/api/analytics/anonymous/pageview', $payload );
}

test( 'the anonymous table carries no column capable of identifying a visitor', function (): void {
	// The privacy guarantee is structural: you cannot correlate two rows to
	// one person using columns that do not exist. If a future change adds one
	// of these, that guarantee is gone and this test should stop it.
	foreach ( [ 'visitor_id', 'session_id', 'fingerprint', 'ip_address', 'user_agent' ] as $column ) {
		expect( Schema::hasColumn( 'analytics_anonymous_page_views', $column ) )
			->toBeFalse( "analytics_anonymous_page_views must not have a `{$column}` column" );
	}

	expect( Schema::hasColumn( 'analytics_anonymous_page_views', 'path' ) )->toBeTrue();
} );

test( 'a pre-consent page view is recorded without any identifier', function (): void {
	postAnonymous( [
		'path'          => '/docs/getting-started',
		'title'         => 'Getting Started',
		'referrer_host' => 'duckduckgo.com',
	] )->assertNoContent();

	$row = AnonymousPageView::query()->sole();

	expect( $row->path )->toBe( '/docs/getting-started' )
		->and( $row->title )->toBe( 'Getting Started' )
		->and( $row->referrer_host )->toBe( 'duckduckgo.com' )
		->and( $row->device_type )->toBe( 'desktop' );

	// Nothing may leak into the identified tables.
	expect( PageView::query()->count() )->toBe( 0 )
		->and( Visitor::query()->count() )->toBe( 0 );
} );

test( 'nothing is recorded when anonymous mode is disabled', function (): void {
	config()->set( 'artisanpack.analytics.privacy.anonymous_mode', false );

	postAnonymous( [ 'path' => '/docs/getting-started' ] )->assertNoContent();

	expect( AnonymousPageView::query()->count() )->toBe( 0 );
} );

test( 'an explicit opt-out suppresses anonymous tracking too', function (): void {
	// Anonymous mode is an argument about identifiability, not a way around
	// someone saying no. Both header spellings must be honoured.
	postAnonymous( [ 'path' => '/docs/one' ], [ 'DNT' => '1' ] )->assertNoContent();
	postAnonymous( [ 'path' => '/docs/two' ], [ 'Sec-GPC' => '1' ] )->assertNoContent();

	expect( AnonymousPageView::query()->count() )->toBe( 0 );
} );

test( 'excluded paths are honoured for anonymous page views', function (): void {
	postAnonymous( [ 'path' => '/admin/settings' ] )->assertNoContent();
	postAnonymous( [ 'path' => '/docs/getting-started' ] )->assertNoContent();

	expect( AnonymousPageView::query()->pluck( 'path' )->all() )->toBe( [ '/docs/getting-started' ] );
} );

test( 'bots are filtered out of anonymous tracking', function (): void {
	postAnonymous( [ 'path' => '/docs/one' ], [ 'User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)' ] )
		->assertNoContent();

	expect( AnonymousPageView::query()->count() )->toBe( 0 );
} );

test( 'identifiers sent by a mis-wired client are dropped rather than stored', function (): void {
	// The endpoint validates a narrow payload, so a client that wrongly
	// included identity fields cannot turn an anonymous hit into an
	// identifiable one by accident.
	postAnonymous( [
		'path'        => '/docs/getting-started',
		'visitor_id'  => 'should-not-persist',
		'session_id'  => '11111111-2222-4333-8444-555555555555',
		'fingerprint' => [ 'webdriver' => false ],
	] )->assertNoContent();

	$row = AnonymousPageView::query()->sole();

	expect( $row->getAttributes() )
		->not->toHaveKey( 'visitor_id' )
		->not->toHaveKey( 'session_id' )
		->not->toHaveKey( 'fingerprint' );
} );

test( 'a full referrer URL is reduced to its host', function (): void {
	// A referrer query string can carry search terms or share identifiers.
	// Reducing server-side means the guarantee does not rely on the client.
	postAnonymous( [
		'path'          => '/docs/one',
		'referrer_host' => 'https://www.google.com/search?q=secret+search+terms',
	] )->assertNoContent();

	expect( AnonymousPageView::query()->sole()->referrer_host )->toBe( 'www.google.com' );
} );

test( 'a referrer that is neither a URL nor a bare host is discarded', function (): void {
	postAnonymous( [ 'path' => '/docs/one', 'referrer_host' => 'not a host /with?stuff' ] )->assertNoContent();

	expect( AnonymousPageView::query()->sole()->referrer_host )->toBeNull();
} );

test( 'a path is required', function (): void {
	// Validation rejects rather than silently 204-ing. A beacon ignores the
	// response either way, but a 422 is visible in the network tab, which is
	// the difference between a mis-wired client being debuggable and it
	// looking like it works.
	postAnonymous( [ 'title' => 'No path' ] )->assertStatus( 422 );

	expect( AnonymousPageView::query()->count() )->toBe( 0 );
} );

test( 'anonymous rows are swept by the retention cleanup', function (): void {
	config()->set( 'artisanpack.analytics.retention.period', 30 );

	AnonymousPageView::create( [ 'path' => '/old', 'created_at' => now()->subDays( 60 ) ] );
	AnonymousPageView::create( [ 'path' => '/recent', 'created_at' => now()->subDays( 2 ) ] );

	( new ArtisanPackUI\Analytics\Jobs\CleanupOldData() )->handle();

	expect( AnonymousPageView::query()->pluck( 'path' )->all() )->toBe( [ '/recent' ] );
} );

test( 'anonymous rows do not appear in visitor or session counts', function (): void {
	// The whole reason these live in their own table. Every existing
	// visitor-scoped query stays correct without knowing this feature exists.
	AnonymousPageView::create( [ 'path' => '/docs/one', 'created_at' => now() ] );
	AnonymousPageView::create( [ 'path' => '/docs/two', 'created_at' => now() ] );

	expect( Visitor::query()->count() )->toBe( 0 )
		->and( PageView::query()->count() )->toBe( 0 )
		->and( AnonymousPageView::query()->count() )->toBe( 2 );
} );
