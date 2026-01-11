<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );
	Cache::flush();
} );

test( 'analytics query can be resolved from container', function (): void {
	$query = app( AnalyticsQuery::class );

	expect( $query )->toBeInstanceOf( AnalyticsQuery::class );
} );

test( 'analytics query returns stats with comparison', function (): void {
	// Create page views for current period
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$query = app( AnalyticsQuery::class );
	$range = DateRange::today();
	$stats = $query->getStats( $range, true );

	expect( $stats )->toHaveKeys( [
		'pageviews',
		'visitors',
		'sessions',
		'bounce_rate',
		'avg_session_duration',
		'comparison',
	] );
	expect( $stats['pageviews'] )->toBe( 1 );
} );

test( 'analytics query returns stats without comparison', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$query = app( AnalyticsQuery::class );
	$range = DateRange::today();
	$stats = $query->getStats( $range, false );

	expect( $stats )->toHaveKeys( [
		'pageviews',
		'visitors',
		'sessions',
		'bounce_rate',
		'avg_session_duration',
	] );
	expect( $stats )->not->toHaveKey( 'comparison' );
} );

test( 'analytics query caches results', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$query = app( AnalyticsQuery::class );
	$range = DateRange::today();

	// First call
	$stats1 = $query->getStats( $range );

	// Add another page view
	PageView::create( [
		'path'       => '/page-2',
		'session_id' => 'session-2',
		'visitor_id' => 'visitor-2',
		'created_at' => now(),
	] );

	// Second call should return cached result
	$stats2 = $query->getStats( $range );

	expect( $stats1['pageviews'] )->toBe( $stats2['pageviews'] );
} );

test( 'analytics query can clear cache', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$query = app( AnalyticsQuery::class );
	$range = DateRange::today();

	// First call
	$stats1 = $query->getStats( $range );

	// Add another page view
	PageView::create( [
		'path'       => '/page-2',
		'session_id' => 'session-2',
		'visitor_id' => 'visitor-2',
		'created_at' => now(),
	] );

	// Clear cache
	$query->clearCache();

	// Second call should return fresh result
	$stats2 = $query->getStats( $range );

	expect( $stats2['pageviews'] )->toBe( 2 );
} );

test( 'analytics query returns top pages', function (): void {
	PageView::create( [
		'path'       => '/popular',
		'title'      => 'Popular Page',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	PageView::create( [
		'path'       => '/popular',
		'title'      => 'Popular Page',
		'session_id' => 'session-2',
		'visitor_id' => 'visitor-2',
		'created_at' => now(),
	] );

	$query    = app( AnalyticsQuery::class );
	$range    = DateRange::today();
	$topPages = $query->getTopPages( $range );

	expect( $topPages )->toHaveCount( 1 );
	expect( $topPages->first()['path'] )->toBe( '/popular' );
	expect( $topPages->first()['views'] )->toBe( 2 );
} );

test( 'analytics query returns traffic sources', function (): void {
	Session::create( [
		'session_id'       => 'session-1',
		'visitor_id'       => 'visitor-1',
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
		'is_bounce'        => false,
		'referrer_type'    => 'search',
		'referrer_source'  => 'google',
	] );

	$query   = app( AnalyticsQuery::class );
	$range   = DateRange::today();
	$sources = $query->getTrafficSources( $range );

	expect( $sources )->toBeInstanceOf( Illuminate\Support\Collection::class );
} );

test( 'analytics query returns page views over time', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$query     = app( AnalyticsQuery::class );
	$range     = DateRange::lastDays( 7 );
	$pageViews = $query->getPageViews( $range, 'day' );

	expect( $pageViews )->toBeInstanceOf( Illuminate\Support\Collection::class );
} );

test( 'analytics query returns realtime data', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$query    = app( AnalyticsQuery::class );
	$realtime = $query->getRealtime( 5 );

	expect( $realtime )->toHaveKey( 'active_visitors' );
	expect( $realtime )->toHaveKey( 'timestamp' );
} );

test( 'analytics query returns page analytics', function (): void {
	PageView::create( [
		'path'       => '/test-page',
		'title'      => 'Test Page',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$query     = app( AnalyticsQuery::class );
	$range     = DateRange::today();
	$analytics = $query->getPageAnalytics( '/test-page', $range );

	expect( $analytics )->toHaveKey( 'pageviews' );
	expect( $analytics )->toHaveKey( 'visitors' );
} );
