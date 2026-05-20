<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Jobs\AnalyzeBotTraffic;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.bot_detection', [
		'enabled'           => true,
		'threshold'         => 70,
		'analysis_interval' => 15,
		'analysis_window'   => 60,
		'whitelist'         => [
			'user_agents' => [],
			'ips'         => [],
		],
		'signals' => [
			'user_agent'       => true,
			'engagement'       => true,
			'request_patterns' => true,
			'js_fingerprint'   => true,
		],
	] );
	config()->set( 'artisanpack.analytics.local.queue_name', 'analytics' );
} );

/**
 * Create a visitor with the given attributes.
 *
 * @param array<string, mixed> $attributes Visitor attributes.
 *
 * @return Visitor
 */
function makeBotVisitor( array $attributes = [] ): Visitor
{
	return Visitor::create( array_merge( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => 'visitor-' . Str::random( 10 ),
		'first_seen_at' => now()->subMinutes( 10 ),
		'last_seen_at'  => now()->subMinutes( 5 ),
		'user_agent'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
		'ip_address'    => '203.0.113.10',
	], $attributes ) );
}

/**
 * Attach page views to a visitor.
 *
 * @param Visitor                          $visitor   The visitor.
 * @param array<int, array<string, mixed>> $pageViews Page view attribute sets.
 *
 * @return void
 */
function attachPageViews( Visitor $visitor, array $pageViews ): void
{
	$session = Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
	] );

	foreach ( $pageViews as $data ) {
		PageView::create( array_merge( [
			'session_id' => $session->id,
			'visitor_id' => $visitor->id,
			'path'       => '/',
		], $data ) );
	}
}

test( 'job flags a visitor exhibiting known bot behavior', function (): void {
	$visitor = makeBotVisitor( [ 'user_agent' => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)' ] );

	( new AnalyzeBotTraffic() )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	$visitor->refresh();

	expect( $visitor->is_bot )->toBeTrue();
	expect( $visitor->bot_score )->toBe( 100 );
	expect( $visitor->bot_detected_at )->not->toBeNull();
} );

test( 'job does not flag a visitor exhibiting human behavior', function (): void {
	$base    = now()->subMinutes( 30 );
	$visitor = makeBotVisitor();
	attachPageViews( $visitor, [
		[ 'engaged_time' => 30, 'scroll_depth' => 80, 'time_on_page' => 45, 'referrer_path' => '/home', 'created_at' => $base ],
		[ 'engaged_time' => 25, 'scroll_depth' => 60, 'time_on_page' => 30, 'referrer_path' => '/about', 'created_at' => $base->copy()->addMinutes( 5 ) ],
		[ 'engaged_time' => 40, 'scroll_depth' => 90, 'time_on_page' => 60, 'referrer_path' => '/contact', 'created_at' => $base->copy()->addMinutes( 12 ) ],
	] );

	( new AnalyzeBotTraffic() )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	$visitor->refresh();

	expect( $visitor->is_bot )->toBeFalse();
	expect( $visitor->bot_score )->toBeLessThan( 70 );
	expect( $visitor->bot_detected_at )->not->toBeNull();
} );

test( 'job flags a zero-engagement visitor', function (): void {
	$base    = now()->subMinutes( 40 );
	$visitor = makeBotVisitor();
	attachPageViews( $visitor, [
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'time_on_page' => 0, 'referrer_path' => '/a', 'created_at' => $base ],
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'time_on_page' => 0, 'referrer_path' => '/a', 'created_at' => $base->copy()->addSeconds( 2 ) ],
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'time_on_page' => 0, 'referrer_path' => '/a', 'created_at' => $base->copy()->addSeconds( 4 ) ],
	] );

	( new AnalyzeBotTraffic() )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	$visitor->refresh();

	expect( $visitor->is_bot )->toBeTrue();
	expect( $visitor->bot_score )->toBeGreaterThanOrEqual( 70 );
} );

test( 'job never flags a whitelisted visitor', function (): void {
	config()->set( 'artisanpack.analytics.bot_detection.whitelist.user_agents', [ 'GPTBot' ] );

	$visitor = makeBotVisitor( [ 'user_agent' => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)' ] );

	( new AnalyzeBotTraffic() )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	$visitor->refresh();

	expect( $visitor->is_bot )->toBeFalse();
	expect( $visitor->bot_score )->toBe( 0 );
} );

test( 'job only processes visitors within the analysis window', function (): void {
	$recent = makeBotVisitor( [
		'user_agent'   => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)',
		'last_seen_at' => now()->subMinutes( 10 ),
	] );

	$stale = makeBotVisitor( [
		'user_agent'   => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)',
		'last_seen_at' => now()->subMinutes( 120 ),
	] );

	( new AnalyzeBotTraffic() )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	expect( $recent->fresh()->bot_detected_at )->not->toBeNull();
	expect( $stale->fresh()->bot_detected_at )->toBeNull();
} );

test( 'job skips visitors that have already been scored', function (): void {
	$visitor = makeBotVisitor( [
		'user_agent'      => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)',
		'is_bot'          => true,
		'bot_score'       => 100,
		'bot_detected_at' => Carbon::parse( '2026-01-01 00:00:00' ),
	] );

	( new AnalyzeBotTraffic() )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	expect( $visitor->fresh()->bot_detected_at->toDateTimeString() )->toBe( '2026-01-01 00:00:00' );
} );

test( 'job does nothing when bot detection is disabled', function (): void {
	config()->set( 'artisanpack.analytics.bot_detection.enabled', false );

	$visitor = makeBotVisitor( [ 'user_agent' => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)' ] );

	( new AnalyzeBotTraffic() )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	expect( $visitor->fresh()->bot_detected_at )->toBeNull();
} );

test( 'job aborts when the analysis window is not positive', function (): void {
	config()->set( 'artisanpack.analytics.bot_detection.analysis_window', 0 );

	$visitor = makeBotVisitor( [ 'user_agent' => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)' ] );

	( new AnalyzeBotTraffic() )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	expect( $visitor->fresh()->bot_detected_at )->toBeNull();
} );

test( 'job respects the site scope', function (): void {
	$siteOne = makeBotVisitor( [
		'user_agent' => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)',
		'site_id'    => 1,
	] );

	$siteTwo = makeBotVisitor( [
		'user_agent' => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)',
		'site_id'    => 2,
	] );

	( new AnalyzeBotTraffic( siteId: 1 ) )->handle( app( ArtisanPackUI\Analytics\Services\BotDetector::class ) );

	expect( $siteOne->fresh()->bot_detected_at )->not->toBeNull();
	expect( $siteTwo->fresh()->bot_detected_at )->toBeNull();
} );
