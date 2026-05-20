<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Http\Controllers\AnalyticsQueryController;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'artisanpack.analytics.local.queue_processing', false );
    config()->set( 'artisanpack.analytics.local.enabled', true );
} );

/**
 * Create a visitor with the given bot state and user agent, returning its id.
 */
function botStatsVisitor( bool $isBot, ?string $userAgent = null, ?int $siteId = null ): string
{
    $visitor = Visitor::create( [
        'fingerprint'   => bin2hex( random_bytes( 16 ) ),
        'site_id'       => $siteId,
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'device_type'   => 'desktop',
        'user_agent'    => $userAgent,
        'is_bot'        => $isBot,
        'bot_score'     => $isBot ? 90 : null,
    ] );

    return (string) $visitor->id;
}

/**
 * Create a page view tied to a visitor id.
 */
function botStatsPageView( string $visitorId, string $path = '/' ): void
{
    PageView::create( [
        'path'       => $path,
        'session_id' => 'session-' . $visitorId,
        'visitor_id' => $visitorId,
        'created_at' => now(),
    ] );
}

test( 'getTopBotAgents returns only bot agents ordered by visits', function (): void {
    $provider = new LocalAnalyticsProvider;

    botStatsVisitor( true, 'GPTBot/1.0' );
    botStatsVisitor( true, 'GPTBot/1.0' );
    botStatsVisitor( true, 'ClaudeBot/1.0' );
    botStatsVisitor( false, 'Mozilla/5.0 (real human)' );

    $agents = $provider->getTopBotAgents( DateRange::today() );

    expect( $agents->pluck( 'user_agent' )->all() )->toBe( [ 'GPTBot/1.0', 'ClaudeBot/1.0' ] );
    expect( $agents->first() )->toMatchArray( [ 'user_agent' => 'GPTBot/1.0', 'visits' => 2 ] );
    expect( $agents->pluck( 'user_agent' )->all() )->not->toContain( 'Mozilla/5.0 (real human)' );
} );

test( 'getTopBotAgents ignores bot visitors without a user agent', function (): void {
    $provider = new LocalAnalyticsProvider;

    botStatsVisitor( true, null );
    botStatsVisitor( true, 'ByteSpider' );

    $agents = $provider->getTopBotAgents( DateRange::today() );

    expect( $agents )->toHaveCount( 1 );
    expect( $agents->first()['user_agent'] )->toBe( 'ByteSpider' );
} );

test( 'getTopBotAgents respects site scoping', function (): void {
    $provider = new LocalAnalyticsProvider;

    botStatsVisitor( true, 'GPTBot', 1 );
    botStatsVisitor( true, 'ClaudeBot', 2 );

    $agents = $provider->getTopBotAgents( DateRange::today(), 10, [ 'site_id' => 1 ] );

    expect( $agents->pluck( 'user_agent' )->all() )->toBe( [ 'GPTBot' ] );
} );

test( 'getBotStats summarizes bot visits, percentage, agents and trend', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    // Three human visits, one bot visit => 25% bot share.
    botStatsPageView( botStatsVisitor( false, 'Human A' ), '/a' );
    botStatsPageView( botStatsVisitor( false, 'Human B' ), '/b' );
    botStatsPageView( botStatsVisitor( false, 'Human C' ), '/c' );
    botStatsPageView( botStatsVisitor( true, 'GPTBot' ), '/bot' );

    $stats = $query->getBotStats( DateRange::today() );

    expect( $stats['bot_visits'] )->toBe( 1 );
    expect( $stats['total_visits'] )->toBe( 4 );
    expect( $stats['bot_percentage'] )->toBe( 25.0 );
    expect( $stats['top_agents'] )->toBe( [ [ 'user_agent' => 'GPTBot', 'visits' => 1 ] ] );
    expect( $stats['trend'] )->toBeArray();
} );

test( 'getBotStats returns a zero percentage when there is no traffic', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    $stats = $query->getBotStats( DateRange::today() );

    expect( $stats['bot_visits'] )->toBe( 0 );
    expect( $stats['total_visits'] )->toBe( 0 );
    expect( $stats['bot_percentage'] )->toBe( 0.0 );
    expect( $stats['top_agents'] )->toBe( [] );
} );

test( 'getBotStats ignores an incoming bot filter mode', function (): void {
    $query = ( new AnalyticsQuery( new LocalAnalyticsProvider ) )->setCacheEnabled( false );

    botStatsPageView( botStatsVisitor( false, 'Human' ), '/a' );
    botStatsPageView( botStatsVisitor( true, 'GPTBot' ), '/bot' );

    // Passing bots => only must not change the computed totals.
    $stats = $query->getBotStats( DateRange::today(), 10, 'day', [ 'bots' => 'only' ] );

    expect( $stats['bot_visits'] )->toBe( 1 );
    expect( $stats['total_visits'] )->toBe( 2 );
} );

test( 'bots controller endpoint returns the bot stats envelope', function (): void {
    botStatsPageView( botStatsVisitor( false, 'Human' ), '/a' );
    botStatsPageView( botStatsVisitor( true, 'GPTBot' ), '/bot' );

    $controller = new AnalyticsQueryController( app( AnalyticsQuery::class ) );
    $response   = $controller->bots( Request::create( '/api/analytics/bots', 'GET', [ 'period' => 'today' ] ) );

    expect( $response->getStatusCode() )->toBe( 200 );

    $payload = $response->getData( true );

    expect( $payload['success'] )->toBeTrue();
    expect( $payload['data'] )->toHaveKeys( [ 'bot_visits', 'total_visits', 'bot_percentage', 'top_agents', 'trend' ] );
    expect( $payload['data']['bot_visits'] )->toBe( 1 );
    expect( $payload )->toHaveKey( 'range' );
} );
