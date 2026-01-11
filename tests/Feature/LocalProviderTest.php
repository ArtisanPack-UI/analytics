<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    // Disable queue processing for tests
    config()->set( 'artisanpack.analytics.local.queue_processing', false );
    config()->set( 'artisanpack.analytics.local.enabled', true );
} );

test( 'local provider returns correct name', function (): void {
    $provider = new LocalAnalyticsProvider;

    expect( $provider->getName() )->toBe( 'local' );
} );

test( 'local provider is enabled by default', function (): void {
    $provider = new LocalAnalyticsProvider;

    expect( $provider->isEnabled() )->toBeTrue();
} );

test( 'local provider can be disabled via config', function (): void {
    config()->set( 'artisanpack.analytics.local.enabled', false );

    $provider = new LocalAnalyticsProvider;

    expect( $provider->isEnabled() )->toBeFalse();
} );

test( 'local provider returns config array', function (): void {
    $provider = new LocalAnalyticsProvider;
    $config   = $provider->getConfig();

    expect( $config )->toBeArray();
    expect( $config )->toHaveKey( 'enabled' );
} );

test( 'local provider tracks page view', function (): void {
    $provider = new LocalAnalyticsProvider;

    $data = new PageViewData(
        path: '/test-page',
        title: 'Test Page',
        sessionId: 'session-123',
        visitorId: 'visitor-123',
    );

    $provider->trackPageView( $data );

    $this->assertDatabaseHas( 'analytics_page_views', [
        'path'       => '/test-page',
        'title'      => 'Test Page',
        'session_id' => 'session-123',
        'visitor_id' => 'visitor-123',
    ] );
} );

test( 'local provider tracks custom event', function (): void {
    $provider = new LocalAnalyticsProvider;

    $data = new EventData(
        name: 'button_click',
        category: 'engagement',
        sessionId: 'session-123',
        visitorId: 'visitor-123',
        value: 1.5,
        properties: ['button_id' => 'cta-main'],
    );

    $provider->trackEvent( $data );

    $this->assertDatabaseHas( 'analytics_events', [
        'name'       => 'button_click',
        'category'   => 'engagement',
        'session_id' => 'session-123',
        'visitor_id' => 'visitor-123',
    ] );
} );

test( 'local provider does not track when disabled', function (): void {
    config()->set( 'artisanpack.analytics.local.enabled', false );
    $provider = new LocalAnalyticsProvider;

    $data = new PageViewData(
        path: '/disabled-test',
        sessionId: 'session-123',
        visitorId: 'visitor-123',
    );

    $provider->trackPageView( $data );

    $this->assertDatabaseMissing( 'analytics_page_views', [
        'path' => '/disabled-test',
    ] );
} );

test( 'local provider supports queries', function (): void {
    $provider = new LocalAnalyticsProvider;

    expect( $provider->supportsQueries() )->toBeTrue();
} );

test( 'local provider is healthy when enabled and database works', function (): void {
    $provider = new LocalAnalyticsProvider;

    expect( $provider->isHealthy() )->toBeTrue();
} );

test( 'local provider returns page view count', function (): void {
    $provider = new LocalAnalyticsProvider;

    // Create some page views
    PageView::create( [
        'path'       => '/page-1',
        'session_id' => 'session-1',
        'visitor_id' => 'visitor-1',
        'created_at' => now(),
    ] );

    PageView::create( [
        'path'       => '/page-2',
        'session_id' => 'session-1',
        'visitor_id' => 'visitor-1',
        'created_at' => now(),
    ] );

    $range = DateRange::today();
    $count = $provider->getPageViews( $range );

    expect( $count )->toBe( 2 );
} );

test( 'local provider returns unique visitor count', function (): void {
    $provider = new LocalAnalyticsProvider;

    // Create page views from different visitors
    PageView::create( [
        'path'       => '/page-1',
        'session_id' => 'session-1',
        'visitor_id' => 'visitor-1',
        'created_at' => now(),
    ] );

    PageView::create( [
        'path'       => '/page-2',
        'session_id' => 'session-2',
        'visitor_id' => 'visitor-2',
        'created_at' => now(),
    ] );

    PageView::create( [
        'path'       => '/page-3',
        'session_id' => 'session-1',
        'visitor_id' => 'visitor-1',
        'created_at' => now(),
    ] );

    $range = DateRange::today();
    $count = $provider->getVisitors( $range );

    expect( $count )->toBe( 2 );
} );

test( 'local provider returns session count', function (): void {
    $provider = new LocalAnalyticsProvider;

    // Create sessions
    Session::create( [
        'session_id'       => 'session-1',
        'visitor_id'       => 'visitor-1',
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
        'is_bounce'        => false,
        'referrer_type'    => 'direct',
    ] );

    Session::create( [
        'session_id'       => 'session-2',
        'visitor_id'       => 'visitor-2',
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
        'is_bounce'        => true,
        'referrer_type'    => 'direct',
    ] );

    $range = DateRange::today();
    $count = $provider->getSessions( $range );

    expect( $count )->toBe( 2 );
} );

test( 'local provider returns bounce rate', function (): void {
    $provider = new LocalAnalyticsProvider;

    // Create 2 sessions, 1 bounced
    Session::create( [
        'session_id'       => 'session-1',
        'visitor_id'       => 'visitor-1',
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
        'is_bounce'        => true,
        'referrer_type'    => 'direct',
    ] );

    Session::create( [
        'session_id'       => 'session-2',
        'visitor_id'       => 'visitor-2',
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
        'is_bounce'        => false,
        'referrer_type'    => 'direct',
    ] );

    $range      = DateRange::today();
    $bounceRate = $provider->getBounceRate( $range );

    expect( $bounceRate )->toBe( 50.0 );
} );

test( 'local provider returns top pages', function (): void {
    $provider = new LocalAnalyticsProvider;

    // Create page views
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

    PageView::create( [
        'path'       => '/other',
        'title'      => 'Other Page',
        'session_id' => 'session-1',
        'visitor_id' => 'visitor-1',
        'created_at' => now(),
    ] );

    $range    = DateRange::today();
    $topPages = $provider->getTopPages( $range, 10 );

    expect( $topPages )->toHaveCount( 2 );
    expect( $topPages->first()['path'] )->toBe( '/popular' );
    expect( $topPages->first()['views'] )->toBe( 2 );
} );

test( 'local provider returns empty collection for top pages when no data', function (): void {
    $provider = new LocalAnalyticsProvider;

    $range    = DateRange::today();
    $topPages = $provider->getTopPages( $range );

    expect( $topPages )->toBeEmpty();
} );

test( 'local provider returns average session duration', function (): void {
    $provider = new LocalAnalyticsProvider;

    Session::create( [
        'session_id'       => 'session-1',
        'visitor_id'       => 'visitor-1',
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
        'is_bounce'        => false,
        'referrer_type'    => 'direct',
        'duration'         => 120,
    ] );

    Session::create( [
        'session_id'       => 'session-2',
        'visitor_id'       => 'visitor-2',
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
        'is_bounce'        => false,
        'referrer_type'    => 'direct',
        'duration'         => 180,
    ] );

    $range    = DateRange::today();
    $duration = $provider->getAverageSessionDuration( $range );

    expect( $duration )->toBe( 150 );
} );

test( 'local provider returns statistics summary', function (): void {
    $provider = new LocalAnalyticsProvider;

    $range = DateRange::today();
    $stats = $provider->getStats( $range );

    expect( $stats )->toHaveKeys( [
        'pageviews',
        'visitors',
        'sessions',
        'bounce_rate',
        'avg_session_duration',
    ] );
} );

test( 'local provider applies filters correctly', function (): void {
    $provider = new LocalAnalyticsProvider;

    PageView::create( [
        'path'       => '/filtered',
        'session_id' => 'session-1',
        'visitor_id' => 'visitor-1',
        'site_id'    => 1,
        'created_at' => now(),
    ] );

    PageView::create( [
        'path'       => '/other',
        'session_id' => 'session-2',
        'visitor_id' => 'visitor-2',
        'site_id'    => 2,
        'created_at' => now(),
    ] );

    $range = DateRange::today();
    $count = $provider->getPageViews( $range, ['site_id' => 1] );

    expect( $count )->toBe( 1 );
} );

test( 'local provider respects date range', function (): void {
    $provider = new LocalAnalyticsProvider;

    PageView::create( [
        'path'       => '/today',
        'session_id' => 'session-1',
        'visitor_id' => 'visitor-1',
        'created_at' => now(),
    ] );

    PageView::create( [
        'path'       => '/yesterday',
        'session_id' => 'session-2',
        'visitor_id' => 'visitor-2',
        'created_at' => now()->subDays( 2),
    ]);

    $range = DateRange::today();
    $count = $provider->getPageViews( $range);

    expect( $count)->toBe( 1);
});
