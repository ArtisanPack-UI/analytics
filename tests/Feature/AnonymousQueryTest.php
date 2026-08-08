<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Http\Controllers\AnalyticsQueryController;
use ArtisanPackUI\Analytics\Http\Controllers\InertiaDashboardController;
use ArtisanPackUI\Analytics\Models\AnonymousPageView;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'artisanpack.analytics.local.queue_processing', false );
    config()->set( 'artisanpack.analytics.local.enabled', true );
    config()->set( 'artisanpack.analytics.privacy.anonymous_mode', true );
} );

/**
 * Build a query service with caching off so each assertion sees fresh data.
 */
function anonymousQueryService(): AnalyticsQuery
{
    return ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );
}

/**
 * Create an identified page view, with its visitor and session.
 */
function identifiedPageView( string $path = '/', ?int $siteId = null, ?string $referrerDomain = null ): void
{
    $visitor = Visitor::create( [
        'fingerprint'   => bin2hex( random_bytes( 16 ) ),
        'site_id'       => $siteId,
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'device_type'   => 'desktop',
        'is_bot'        => false,
    ] );

    $sessionId = 'session-' . bin2hex( random_bytes( 8 ) );

    Session::create( [
        'site_id'          => $siteId,
        'visitor_id'       => $visitor->id,
        'session_id'       => $sessionId,
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => $path,
        'exit_page'        => $path,
        'referrer_domain'  => $referrerDomain,
        'is_bounce'        => false,
    ] );

    PageView::create( [
        'path'       => $path,
        'session_id' => $sessionId,
        'visitor_id' => $visitor->id,
        'site_id'    => $siteId,
        'created_at' => now(),
    ] );
}

/**
 * Create an anonymous (pre-consent) page view.
 */
function anonymousPageView(
    string $path = '/',
    ?int $siteId = null,
    ?string $referrerHost = null,
    string $deviceType = 'mobile',
): void {
    AnonymousPageView::create( [
        'site_id'       => $siteId,
        'path'          => $path,
        'title'         => 'Anonymous page',
        'referrer_host' => $referrerHost,
        'device_type'   => $deviceType,
        'created_at'    => now(),
    ] );
}

test( 'anonymous rows are excluded by default so figures match pre-anonymous behaviour', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    identifiedPageView( '/a' );
    anonymousPageView( '/a' );
    anonymousPageView( '/b' );

    $stats = $query->getStats( DateRange::today(), false );

    expect( $stats['pageviews'] )->toBe( 2 );
    expect( $stats['anonymous_pageviews'] )->toBe( 0 );
    expect( $stats['anonymous_mode'] )->toBe( 'exclude' );
    expect( $query->getPageViewCount( DateRange::today() ) )->toBe( 2 );
    expect( $query->getTopPages( DateRange::today() )->pluck( 'path' )->all() )->toBe( [ '/a' ] );
} );

test( 'combined page views add anonymous rows to the identified total', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    identifiedPageView( '/a' );
    anonymousPageView( '/a' );
    anonymousPageView( '/b' );

    $count = $query->getPageViewCount( DateRange::today(), [ 'anonymous' => 'include' ] );

    expect( $count )->toBe( 4 );
} );

test( 'the anonymous-only scope counts nothing but anonymous rows', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    anonymousPageView( '/b' );
    anonymousPageView( '/b' );

    expect( $query->getPageViewCount( DateRange::today(), [ 'anonymous' => 'only' ] ) )->toBe( 2 );
    expect( $query->getAnonymousPageViewCount( DateRange::today() ) )->toBe( 2 );
} );

test( 'chained modifiers set the anonymous scope for a single query', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    anonymousPageView( '/b' );

    expect( $query->includeAnonymous()->getPageViewCount( DateRange::today() ) )->toBe( 2 );

    // The modifier is consumed, so the next query is identified-only again.
    expect( $query->getPageViewCount( DateRange::today() ) )->toBe( 1 );
} );

test( 'unique visitors, sessions and bounce rate never move with anonymous rows', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );

    foreach ( range( 1, 5 ) as $ignored ) {
        anonymousPageView( '/b' );
    }

    $identifiedOnly = $query->getStats( DateRange::today(), false );
    $combined       = $query->getStats( DateRange::today(), false, [ 'anonymous' => 'include' ] );

    expect( $combined['visitors'] )->toBe( $identifiedOnly['visitors'] );
    expect( $combined['sessions'] )->toBe( $identifiedOnly['sessions'] );
    expect( $combined['bounce_rate'] )->toBe( $identifiedOnly['bounce_rate'] );
    expect( $combined['avg_session_duration'] )->toBe( $identifiedOnly['avg_session_duration'] );

    // Only the page-view figure moved.
    expect( $combined['pageviews'] )->toBe( 6 );
    expect( $combined['anonymous_pageviews'] )->toBe( 5 );
} );

test( 'pages per session is computed from identified page views only', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    identifiedPageView( '/b' );

    foreach ( range( 1, 10 ) as $ignored ) {
        anonymousPageView( '/c' );
    }

    $combined = $query->getStats( DateRange::today(), false, [ 'anonymous' => 'include' ] );

    // Two identified views across two sessions, regardless of the ten
    // anonymous views folded into the page-view total.
    expect( $combined['pages_per_session'] )->toBe( 1.0 );
} );

test( 'an anonymous-only view reports identified metrics as unavailable', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    anonymousPageView( '/b' );

    $stats = $query->getStats( DateRange::today(), true, [ 'anonymous' => 'only' ] );

    expect( $stats['identified_only_metrics_available'] )->toBeFalse();
    expect( $stats['visitors'] )->toBe( 0 );
    expect( $stats['sessions'] )->toBe( 0 );
    expect( $stats['realtime_visitors'] )->toBe( 0 );
    expect( $stats['pageviews'] )->toBe( 1 );
    expect( $stats['comparison'] )->toHaveKey( 'pageviews' );
    expect( $stats['comparison'] )->not->toHaveKey( 'visitors' );
} );

test( 'combined top pages merge both sources and keep unique views identified-only', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/shared' );
    anonymousPageView( '/shared' );
    anonymousPageView( '/shared' );
    anonymousPageView( '/anon-only' );

    $pages = $query->getTopPages( DateRange::today(), 10, [ 'anonymous' => 'include' ] );

    $shared = $pages->firstWhere( 'path', '/shared' );

    expect( $shared['views'] )->toBe( 3 );
    expect( $shared['identified_views'] )->toBe( 1 );
    expect( $shared['anonymous_views'] )->toBe( 2 );
    expect( $shared['unique_views'] )->toBe( 1 );

    $anonOnly = $pages->firstWhere( 'path', '/anon-only' );

    expect( $anonOnly['views'] )->toBe( 1 );
    expect( $anonOnly['unique_views' ] )->toBe( 0 );
} );

test( 'the anonymous device breakdown reports views and shares', function (): void {
    $query = anonymousQueryService();

    anonymousPageView( '/a', null, null, 'mobile' );
    anonymousPageView( '/b', null, null, 'mobile' );
    anonymousPageView( '/c', null, null, 'desktop' );

    $devices = $query->getAnonymousDeviceBreakdown( DateRange::today() );

    expect( $devices->firstWhere( 'device_type', 'mobile' ) )->toMatchArray( [
        'device_type' => 'mobile',
        'views'       => 2,
        'percentage'  => 66.67,
    ] );
    expect( $devices->firstWhere( 'device_type', 'desktop' )['views'] )->toBe( 1 );
} );

test( 'combined device breakdown keeps sessions and anonymous views in separate columns', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    anonymousPageView( '/b', null, null, 'mobile' );

    $devices = $query->getDeviceBreakdown( DateRange::today(), [ 'anonymous' => 'include' ] );

    $mobile = $devices->firstWhere( 'device_type', 'mobile' );

    expect( $mobile['anonymous_views'] )->toBe( 1 );
    expect( $mobile['sessions'] )->toBe( 0 );
} );

test( 'anonymous referring hosts group by host and label missing referrers as direct', function (): void {
    $query = anonymousQueryService();

    anonymousPageView( '/a', null, 'example.com' );
    anonymousPageView( '/b', null, 'example.com' );
    anonymousPageView( '/c', null, null );

    $hosts = $query->getAnonymousReferringHosts( DateRange::today() );

    expect( $hosts->firstWhere( 'host', 'example.com' )['views'] )->toBe( 2 );
    expect( $hosts->firstWhere( 'host', 'direct' )['views'] )->toBe( 1 );
} );

test( 'combined referring hosts add both sources in the same unit', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a', null, 'example.com' );
    anonymousPageView( '/b', null, 'example.com' );

    $hosts = $query->getReferringHosts( DateRange::today(), 10, [ 'anonymous' => 'include' ] );

    $example = $hosts->firstWhere( 'host', 'example.com' );

    expect( $example['views'] )->toBe( 2 );
    expect( $example['identified_views'] )->toBe( 1 );
    expect( $example['anonymous_views'] )->toBe( 1 );
} );

test( 'anonymous queries respect site scoping', function (): void {
    $query = anonymousQueryService();

    anonymousPageView( '/a', 1 );
    anonymousPageView( '/b', 2 );

    expect( $query->getAnonymousPageViewCount( DateRange::today(), [ 'site_id' => 1 ] ) )->toBe( 1 );
} );

test( 'the anonymous scope collapses to exclude when anonymous mode is off', function (): void {
    config()->set( 'artisanpack.analytics.privacy.anonymous_mode', false );

    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    anonymousPageView( '/b' );

    // A stale toggle cannot resurrect a feature that is switched off.
    expect( $query->getPageViewCount( DateRange::today(), [ 'anonymous' => 'include' ] ) )->toBe( 1 );
} );

test( 'getAnonymousStats summarizes the split without inventing visitor metrics', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    anonymousPageView( '/b', null, 'example.com' );
    anonymousPageView( '/b', null, 'example.com' );
    anonymousPageView( '/c' );

    $stats = $query->getAnonymousStats( DateRange::today() );

    expect( $stats['enabled'] )->toBeTrue();
    expect( $stats['anonymous_pageviews'] )->toBe( 3 );
    expect( $stats['identified_pageviews'] )->toBe( 1 );
    expect( $stats['total_pageviews'] )->toBe( 4 );
    expect( $stats['anonymous_percentage'] )->toBe( 75.0 );
    expect( $stats['top_pages'][0] )->toMatchArray( [ 'path' => '/b', 'views' => 2 ] );
    expect( $stats )->not->toHaveKey( 'visitors' );
    expect( $stats )->not->toHaveKey( 'sessions' );
} );

test( 'getAnonymousStats reports the feature as off without any rows', function (): void {
    config()->set( 'artisanpack.analytics.privacy.anonymous_mode', false );

    $stats = anonymousQueryService()->getAnonymousStats( DateRange::today() );

    expect( $stats['enabled'] )->toBeFalse();
    expect( $stats['anonymous_pageviews'] )->toBe( 0 );
    expect( $stats['anonymous_percentage'] )->toBe( 0.0 );
    expect( $stats['top_pages'] )->toBe( [] );
} );

test( 'hasAnonymousData reports whether anything was collected', function (): void {
    $query = anonymousQueryService();

    expect( $query->hasAnonymousData( DateRange::today() ) )->toBeFalse();

    anonymousPageView( '/a' );

    expect( $query->hasAnonymousData( DateRange::today() ) )->toBeTrue();
} );

test( 'getAnonymousStats ignores an incoming anonymous filter mode', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    anonymousPageView( '/b' );

    // Passing anonymous => only must not fold the anonymous rows into the
    // identified figure and skew the split.
    $stats = $query->getAnonymousStats( DateRange::today(), 10, 'day', [ 'anonymous' => 'only' ] );

    expect( $stats['identified_pageviews'] )->toBe( 1 );
    expect( $stats['anonymous_pageviews'] )->toBe( 1 );
} );

test( 'bot stats ignore anonymous traffic even when the dashboard toggle is on', function (): void {
    $query = anonymousQueryService();

    identifiedPageView( '/a' );
    anonymousPageView( '/b' );
    anonymousPageView( '/c' );

    // The bot widget passes the dashboard's filters straight through, so the
    // anonymous toggle reaches this call. Anonymous rows are not bots and must
    // not shape a bot-only trend.
    $withToggle = $query->getBotStats( DateRange::today(), 10, 'day', [ 'anonymous' => 'include' ] );
    $without    = $query->getBotStats( DateRange::today() );

    expect( $withToggle )->toBe( $without );
    expect( $withToggle['total_visits'] )->toBe( 1 );
} );

test( 'the anonymous endpoint returns the anonymous stats envelope', function (): void {
    identifiedPageView( '/a' );
    anonymousPageView( '/b' );

    $controller = new AnalyticsQueryController( app( AnalyticsQuery::class ) );
    $response   = $controller->anonymous( Request::create( '/api/analytics/anonymous', 'GET', [ 'period' => 'today' ] ) );

    expect( $response->getStatusCode() )->toBe( 200 );

    $payload = $response->getData( true );

    expect( $payload['success'] )->toBeTrue();
    expect( $payload['data'] )->toHaveKeys( [
        'enabled',
        'anonymous_pageviews',
        'identified_pageviews',
        'total_pageviews',
        'anonymous_percentage',
        'top_pages',
        'referring_hosts',
        'device_breakdown',
        'trend',
    ] );
    expect( $payload['data']['anonymous_pageviews'] )->toBe( 1 );
    expect( $payload )->toHaveKey( 'range' );
} );

test( 'the referrers endpoint honours the anonymous query parameter', function (): void {
    identifiedPageView( '/a', null, 'example.com' );
    anonymousPageView( '/b', null, 'example.com' );

    $controller = new AnalyticsQueryController( app( AnalyticsQuery::class ) );

    $identifiedOnly = $controller->referrers(
        Request::create( '/api/analytics/referrers', 'GET', [ 'period' => 'today' ] ),
    )->getData( true );

    expect( $identifiedOnly['data'][0]['views'] )->toBe( 1 );

    $combined = $controller->referrers(
        Request::create( '/api/analytics/referrers', 'GET', [ 'period' => 'today', 'anonymous' => 'include' ] ),
    )->getData( true );

    expect( $combined['data'][0]['views'] )->toBe( 2 );
} );

test( 'the inertia dashboard does not accept the anonymous-only mode', function (): void {
    $controller = new ReflectionClass( InertiaDashboardController::class );
    $getFilters = $controller->getMethod( 'getFilters' );
    $instance   = app( InertiaDashboardController::class );

    // The dashboard's control is a boolean and its page props reduce the mode
    // to one, so an anonymous-only response would be rendered as though it
    // were combined, with zeroed visitor figures shown unlabelled.
    $only = $getFilters->invoke( $instance, Request::create( '/analytics', 'GET', [ 'anonymous' => 'only' ] ) );

    expect( $only )->not->toHaveKey( 'anonymous' );

    $include = $getFilters->invoke( $instance, Request::create( '/analytics', 'GET', [ 'anonymous' => 'include' ] ) );

    expect( $include['anonymous'] )->toBe( 'include' );
} );

test( 'the stats endpoint rejects an unknown anonymous mode', function (): void {
    identifiedPageView( '/a' );
    anonymousPageView( '/b' );

    $controller = new AnalyticsQueryController( app( AnalyticsQuery::class ) );

    $payload = $controller->stats(
        Request::create( '/api/analytics/stats', 'GET', [
            'period'    => 'today',
            'compare'   => false,
            'anonymous' => 'everything',
        ] ),
    )->getData( true );

    expect( $payload['data']['pageviews'] )->toBe( 1 );
    expect( $payload['data']['anonymous_mode'] )->toBe( 'exclude' );
} );
