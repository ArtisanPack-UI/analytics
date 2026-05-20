<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\BotDetector;
use ArtisanPackUI\Analytics\Services\DeviceDetector;
use Illuminate\Support\Carbon;

/**
 * Build a visitor with a set of in-memory page views attached.
 *
 * @param array<string, mixed>             $attributes Visitor attributes.
 * @param array<int, array<string, mixed>> $pageViews  Page view attribute sets.
 *
 * @return Visitor
 */
function makeVisitor( array $attributes = [], array $pageViews = [] ): Visitor
{
	$visitor = new Visitor( array_merge( [
		'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
		'ip_address' => '203.0.113.10',
	], $attributes ) );

	$views = collect( $pageViews )->map( fn ( array $data ): PageView => new PageView( $data ) );

	$visitor->setRelation( 'pageViews', $views );

	return $visitor;
}

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.bot_detection', [
		'enabled'   => true,
		'threshold' => 70,
		'whitelist' => [
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

	$this->detector = new BotDetector( new DeviceDetector() );
} );

test( 'score always returns a value between 0 and 100', function (): void {
	$visitor = makeVisitor( [ 'user_agent' => null ], [
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'custom_data' => [ 'webdriver' => true, 'headless' => true, 'missing_apis' => true ] ],
		[ 'engaged_time' => 0, 'scroll_depth' => 0 ],
		[ 'engaged_time' => 0, 'scroll_depth' => 0 ],
	] );

	$score = $this->detector->score( $visitor );

	expect( $score )->toBeGreaterThanOrEqual( 0 )->toBeLessThanOrEqual( 100 );
} );

test( 'known bot user agents score 100', function ( string $userAgent ): void {
	$visitor = makeVisitor( [ 'user_agent' => $userAgent ] );

	expect( $this->detector->score( $visitor ) )->toBe( 100 );
} )->with( [
	'GPTBot'    => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)',
	'ClaudeBot' => 'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
	'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
] );

test( 'isBot uses the configured threshold', function (): void {
	$visitor = makeVisitor( [], [
		[ 'custom_data' => [ 'webdriver' => true ] ],
	] );

	// WebDriver alone scores 50, below the default threshold of 70.
	expect( $this->detector->isBot( $visitor ) )->toBeFalse();

	config()->set( 'artisanpack.analytics.bot_detection.threshold', 40 );

	expect( $this->detector->isBot( $visitor ) )->toBeTrue();
} );

test( 'empty user agent scores 80', function (): void {
	$visitor = makeVisitor( [ 'user_agent' => '' ] );

	expect( $this->detector->score( $visitor ) )->toBe( 80 );
} );

test( 'whitelisted user agents bypass scoring', function (): void {
	config()->set( 'artisanpack.analytics.bot_detection.whitelist.user_agents', [ 'Googlebot' ] );

	$visitor = makeVisitor( [ 'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ] );

	expect( $this->detector->score( $visitor ) )->toBe( 0 );
	expect( $this->detector->isBot( $visitor ) )->toBeFalse();
} );

test( 'whitelisted ips bypass scoring', function (): void {
	config()->set( 'artisanpack.analytics.bot_detection.whitelist.ips', [ '198.51.100.5' ] );

	$visitor = makeVisitor( [ 'user_agent' => null, 'ip_address' => '198.51.100.5' ] );

	expect( $this->detector->score( $visitor ) )->toBe( 0 );
} );

test( 'webdriver fingerprint contributes 50 points', function (): void {
	$visitor = makeVisitor( [], [
		[ 'custom_data' => [ 'fingerprint' => [ 'webdriver' => true ] ] ],
	] );

	expect( $this->detector->score( $visitor ) )->toBe( 50 );
} );

test( 'zero engagement across three page views is flagged', function (): void {
	$base    = Carbon::parse( '2026-01-01 12:00:00' );
	$visitor = makeVisitor( [], [
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'referrer_path' => '/a', 'created_at' => $base ],
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'referrer_path' => '/b', 'created_at' => $base->copy()->addMinutes( 6 ) ],
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'referrer_path' => '/c', 'created_at' => $base->copy()->addMinutes( 13 ) ],
	] );

	expect( $this->detector->score( $visitor ) )->toBe( 35 );
} );

test( 'engagement on any page view avoids the zero engagement signal', function (): void {
	$base    = Carbon::parse( '2026-01-01 12:00:00' );
	$visitor = makeVisitor( [], [
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'referrer_path' => '/a', 'created_at' => $base ],
		[ 'engaged_time' => 12, 'scroll_depth' => 40, 'referrer_path' => '/b', 'created_at' => $base->copy()->addMinutes( 6 ) ],
		[ 'engaged_time' => 0, 'scroll_depth' => 0, 'referrer_path' => '/c', 'created_at' => $base->copy()->addMinutes( 13 ) ],
	] );

	expect( $this->detector->score( $visitor ) )->toBe( 0 );
} );

test( 'rapid sequential requests are flagged', function (): void {
	$base    = Carbon::parse( '2026-01-01 12:00:00' );
	$visitor = makeVisitor( [], [
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/a', 'created_at' => $base ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/b', 'created_at' => $base->copy()->addSeconds( 2 ) ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/c', 'created_at' => $base->copy()->addSeconds( 5 ) ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/d', 'created_at' => $base->copy()->addSeconds( 7 ) ],
	] );

	// 4 views across 7 seconds is well above 10 pages/minute.
	expect( $this->detector->score( $visitor ) )->toBe( 30 );
} );

test( 'no referrer variation is flagged', function (): void {
	$base    = Carbon::parse( '2026-01-01 12:00:00' );
	$visitor = makeVisitor( [], [
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/same', 'created_at' => $base ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/same', 'created_at' => $base->copy()->addMinutes( 5 ) ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/same', 'created_at' => $base->copy()->addMinutes( 11 ) ],
	] );

	expect( $this->detector->score( $visitor ) )->toBe( 15 );
} );

test( 'perfectly timed intervals are flagged', function (): void {
	$base    = Carbon::parse( '2026-01-01 12:00:00' );
	$visitor = makeVisitor( [], [
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/a', 'created_at' => $base ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/b', 'created_at' => $base->copy()->addMinutes( 10 ) ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'referrer_path' => '/c', 'created_at' => $base->copy()->addMinutes( 20 ) ],
	] );

	// Even 10-minute intervals, varied referrers, slow pace: only the interval signal fires.
	expect( $this->detector->score( $visitor ) )->toBe( 20 );
} );

test( 'short page view times are flagged', function (): void {
	$base    = Carbon::parse( '2026-01-01 12:00:00' );
	$visitor = makeVisitor( [], [
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'time_on_page' => 0, 'referrer_path' => '/a', 'created_at' => $base ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'time_on_page' => 0, 'referrer_path' => '/b', 'created_at' => $base->copy()->addMinutes( 7 ) ],
		[ 'engaged_time' => 5, 'scroll_depth' => 30, 'time_on_page' => 0, 'referrer_path' => '/c', 'created_at' => $base->copy()->addMinutes( 15 ) ],
	] );

	expect( $this->detector->score( $visitor ) )->toBe( 25 );
} );

test( 'each signal category can be disabled independently', function (): void {
	$visitor = makeVisitor( [], [
		[ 'custom_data' => [ 'webdriver' => true ] ],
	] );

	expect( $this->detector->score( $visitor ) )->toBe( 50 );

	config()->set( 'artisanpack.analytics.bot_detection.signals.js_fingerprint', false );

	expect( $this->detector->score( $visitor ) )->toBe( 0 );
} );

test( 'disabling user agent signal stops empty user agent scoring', function (): void {
	config()->set( 'artisanpack.analytics.bot_detection.signals.user_agent', false );

	$visitor = makeVisitor( [ 'user_agent' => null ] );

	expect( $this->detector->score( $visitor ) )->toBe( 0 );
} );

test( 'a legitimate visitor with one bad signal does not exceed the threshold', function (): void {
	$base    = Carbon::parse( '2026-01-01 12:00:00' );
	$visitor = makeVisitor( [], [
		// Only the perfect-interval signal fires (20 points): real engagement, varied referrers, slow pace.
		[ 'engaged_time' => 30, 'scroll_depth' => 80, 'time_on_page' => 45, 'referrer_path' => '/home', 'created_at' => $base ],
		[ 'engaged_time' => 25, 'scroll_depth' => 60, 'time_on_page' => 30, 'referrer_path' => '/about', 'created_at' => $base->copy()->addMinutes( 5 ) ],
		[ 'engaged_time' => 40, 'scroll_depth' => 90, 'time_on_page' => 60, 'referrer_path' => '/contact', 'created_at' => $base->copy()->addMinutes( 10 ) ],
	] );

	expect( $this->detector->score( $visitor ) )->toBeLessThan( 70 );
	expect( $this->detector->isBot( $visitor ) )->toBeFalse();
} );

test( 'isBot returns false when bot detection is disabled', function (): void {
	config()->set( 'artisanpack.analytics.bot_detection.enabled', false );

	$visitor = makeVisitor( [ 'user_agent' => 'Mozilla/5.0 (compatible; GPTBot/1.1; +https://openai.com/gptbot)' ] );

	expect( $this->detector->isBot( $visitor ) )->toBeFalse();
} );

test( 'the bot detector is resolvable from the container', function (): void {
	expect( app( BotDetector::class ) )->toBeInstanceOf( BotDetector::class );
} );
