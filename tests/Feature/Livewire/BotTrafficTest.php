<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Widgets\BotTraffic;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'artisanpack.analytics.local.queue_processing', false );
    config()->set( 'artisanpack.analytics.local.enabled', true );
} );

/**
 * Create a visitor with the given bot state and user agent, returning its id.
 */
function botWidgetVisitor( bool $isBot, ?string $userAgent = null ): string
{
    $visitor = Visitor::create( [
        'fingerprint'   => bin2hex( random_bytes( 16 ) ),
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
function botWidgetPageView( string $visitorId, string $path = '/' ): void
{
    PageView::create( [
        'path'       => $path,
        'session_id' => 'session-' . $visitorId,
        'visitor_id' => $visitorId,
        'created_at' => now(),
    ] );
}

test( 'bot traffic widget can be rendered', function (): void {
    Livewire::test( BotTraffic::class )
        ->assertStatus( 200 );
} );

test( 'bot traffic widget loads bot statistics on mount', function (): void {
    botWidgetPageView( botWidgetVisitor( false, 'Human' ), '/a' );
    botWidgetPageView( botWidgetVisitor( true, 'GPTBot' ), '/bot' );

    Livewire::test( BotTraffic::class )
        ->assertSet( 'botVisits', 1 )
        ->assertSet( 'totalVisits', 2 )
        ->assertSet( 'botPercentage', 50.0 )
        ->assertSet( 'isLoading', false );
} );

test( 'bot traffic widget lists the top bot user agents', function (): void {
    botWidgetVisitor( true, 'GPTBot' );
    botWidgetVisitor( true, 'GPTBot' );
    botWidgetVisitor( true, 'ClaudeBot' );

    Livewire::test( BotTraffic::class )
        ->assertSee( 'GPTBot' )
        ->assertSee( 'ClaudeBot' );
} );

test( 'bot traffic widget shows an empty state when there is no bot traffic', function (): void {
    botWidgetPageView( botWidgetVisitor( false, 'Human' ), '/a' );

    Livewire::test( BotTraffic::class )
        ->assertSet( 'botVisits', 0 )
        ->assertSee( 'No bot traffic detected for this period' );
} );

test( 'bot traffic widget reloads when widgets are refreshed', function (): void {
    Livewire::test( BotTraffic::class )
        ->assertSet( 'botVisits', 0 )
        ->call( 'refreshData' )
        ->assertStatus( 200 );
} );
