<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'artisanpack.analytics.local.queue_processing', false );
    config()->set( 'artisanpack.analytics.local.enabled', true );
} );

/**
 * Create a visitor with the given bot state and return its UUID.
 */
function scopeVisitor( bool $isBot, string $deviceType = 'desktop' ): string
{
    $visitor = Visitor::create( [
        'fingerprint'   => bin2hex( random_bytes( 16 ) ),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'device_type'   => $deviceType,
        'is_bot'        => $isBot,
        'bot_score'     => $isBot ? 90 : null,
    ] );

    return (string) $visitor->id;
}

/**
 * Create a page view tied to a visitor id.
 */
function scopePageView( string $visitorId, string $path = '/' ): void
{
    PageView::create( [
        'path'       => $path,
        'session_id' => 'session-' . $visitorId,
        'visitor_id' => $visitorId,
        'created_at' => now(),
    ] );
}

/**
 * Create a session tied to a visitor id.
 */
function scopeSession( string $visitorId ): void
{
    Session::create( [
        'session_id'       => 'session-' . $visitorId,
        'visitor_id'       => $visitorId,
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
        'is_bounce'        => false,
        'referrer_type'    => 'direct',
    ] );
}

test( 'page views exclude confirmed bots by default', function (): void {
    $provider = new LocalAnalyticsProvider;

    $human = scopeVisitor( false );
    $bot   = scopeVisitor( true );
    scopePageView( $human, '/human' );
    scopePageView( $bot, '/bot' );

    expect( $provider->getPageViews( DateRange::today() ) )->toBe( 1 );
} );

test( 'page views include bots when requested', function (): void {
    $provider = new LocalAnalyticsProvider;

    scopePageView( scopeVisitor( false ), '/human' );
    scopePageView( scopeVisitor( true ), '/bot' );

    expect( $provider->getPageViews( DateRange::today(), [ 'bots' => 'include' ] ) )->toBe( 2 );
} );

test( 'only bots mode returns just bot traffic', function (): void {
    $provider = new LocalAnalyticsProvider;

    scopePageView( scopeVisitor( false ), '/human' );
    scopePageView( scopeVisitor( true ), '/bot' );

    expect( $provider->getPageViews( DateRange::today(), [ 'bots' => 'only' ] ) )->toBe( 1 );
} );

test( 'unique visitors exclude bot visitors by default', function (): void {
    $provider = new LocalAnalyticsProvider;

    scopePageView( scopeVisitor( false ), '/a' );
    scopePageView( scopeVisitor( false ), '/b' );
    scopePageView( scopeVisitor( true ), '/c' );

    expect( $provider->getVisitors( DateRange::today() ) )->toBe( 2 );
} );

test( 'sessions exclude bot sessions by default', function (): void {
    $provider = new LocalAnalyticsProvider;

    scopeSession( scopeVisitor( false ) );
    scopeSession( scopeVisitor( true ) );

    expect( $provider->getSessions( DateRange::today() ) )->toBe( 1 );
    expect( $provider->getSessions( DateRange::today(), [ 'bots' => 'include' ] ) )->toBe( 2 );
} );

test( 'device breakdown excludes bot visitors by default', function (): void {
    $provider = new LocalAnalyticsProvider;

    $human = scopeVisitor( false, 'desktop' );
    $bot   = scopeVisitor( true, 'mobile' );
    scopeSession( $human );
    scopeSession( $bot );

    $breakdown = $provider->getDeviceBreakdown( DateRange::today() );

    expect( $breakdown->pluck( 'device_type' )->all() )->toContain( 'desktop' );
    expect( $breakdown->pluck( 'device_type' )->all() )->not->toContain( 'mobile' );
} );

test( 'page views with an unresolved visitor are still counted when excluding bots', function (): void {
    $provider = new LocalAnalyticsProvider;

    // No matching Visitor row exists for this id.
    scopePageView( 'orphan-visitor', '/orphan' );

    expect( $provider->getPageViews( DateRange::today() ) )->toBe( 1 );
} );

test( 'realtime visitors exclude bots by default', function (): void {
    $provider = new LocalAnalyticsProvider;

    scopeSession( scopeVisitor( false ) );
    scopeSession( scopeVisitor( true ) );

    expect( $provider->getRealTimeVisitors() )->toBe( 1 );
} );

test( 'realtime visitors include bots when requested', function (): void {
    $provider = new LocalAnalyticsProvider;

    scopeSession( scopeVisitor( false ) );
    scopeSession( scopeVisitor( true ) );

    expect( $provider->getRealTimeVisitors( 5, [ 'bots' => 'include' ] ) )->toBe( 2 );
} );

test( 'analytics query realtime respects bot modifier', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    scopeSession( scopeVisitor( false ) );
    scopeSession( scopeVisitor( true ) );

    expect( $query->getRealtime()['active_visitors'] )->toBe( 1 );
    expect( $query->includeBots()->getRealtime()['active_visitors'] )->toBe( 2 );
} );

test( 'analytics query defaults to excluding bots', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    scopePageView( scopeVisitor( false ), '/human' );
    scopePageView( scopeVisitor( true ), '/bot' );

    expect( $query->getPageViewCount( DateRange::today() ) )->toBe( 1 );
} );

test( 'analytics query includeBots modifier opts bots back in', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    scopePageView( scopeVisitor( false ), '/human' );
    scopePageView( scopeVisitor( true ), '/bot' );

    expect( $query->includeBots()->getPageViewCount( DateRange::today() ) )->toBe( 2 );
} );

test( 'analytics query withBots is an alias of includeBots', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    scopePageView( scopeVisitor( false ), '/human' );
    scopePageView( scopeVisitor( true ), '/bot' );

    expect( $query->withBots()->getPageViewCount( DateRange::today() ) )->toBe( 2 );
} );

test( 'analytics query onlyBots restricts to bot traffic', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    scopePageView( scopeVisitor( false ), '/human' );
    scopePageView( scopeVisitor( true ), '/bot' );

    expect( $query->onlyBots()->getPageViewCount( DateRange::today() ) )->toBe( 1 );
} );

test( 'bot modifier only affects a single query', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    scopePageView( scopeVisitor( false ), '/human' );
    scopePageView( scopeVisitor( true ), '/bot' );

    $query->includeBots()->getPageViewCount( DateRange::today() );

    // The next call should fall back to the default (exclude).
    expect( $query->getPageViewCount( DateRange::today() ) )->toBe( 1 );
} );
