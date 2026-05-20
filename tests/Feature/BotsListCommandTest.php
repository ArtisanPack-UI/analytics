<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses( RefreshDatabase::class );

/**
 * Create a visitor with the given bot attributes.
 */
function botsCommandVisitor( array $attributes = [] ): Visitor
{
    return Visitor::create( array_merge( [
        'fingerprint'     => bin2hex( random_bytes( 16 ) ),
        'first_seen_at'   => now()->subDay(),
        'last_seen_at'    => now(),
        'device_type'     => 'desktop',
        'user_agent'      => 'GPTBot/1.0',
        'is_bot'          => true,
        'bot_score'       => 90,
        'total_pageviews' => 5,
    ], $attributes ) );
}

test( 'bots command reports when no bots are found', function (): void {
    $this->artisan( 'analytics:bots' )
        ->expectsOutputToContain( 'No bot visitors found' )
        ->assertSuccessful();
} );

test( 'bots command lists flagged bot visitors', function (): void {
    botsCommandVisitor( [ 'user_agent' => 'GPTBot/1.0' ] );
    botsCommandVisitor( [ 'user_agent' => 'Mozilla/5.0 Human', 'is_bot' => false, 'bot_score' => null ] );

    $this->artisan( 'analytics:bots' )
        ->expectsOutputToContain( 'GPTBot/1.0' )
        ->assertSuccessful();
} );

test( 'bots command filters by minimum score', function (): void {
    botsCommandVisitor( [ 'user_agent' => 'LowScoreBot', 'bot_score' => 50 ] );

    $this->artisan( 'analytics:bots', [ '--score' => 80 ] )
        ->expectsOutputToContain( 'No bot visitors found' )
        ->assertSuccessful();
} );

test( 'bots command filters by site', function (): void {
    botsCommandVisitor( [ 'user_agent' => 'SiteOneBot', 'site_id' => 1 ] );
    botsCommandVisitor( [ 'user_agent' => 'SiteTwoBot', 'site_id' => 2 ] );

    $this->artisan( 'analytics:bots', [ '--site' => 1 ] )
        ->expectsOutputToContain( 'SiteOneBot' )
        ->doesntExpectOutputToContain( 'SiteTwoBot' )
        ->assertSuccessful();
} );

test( 'bots command rejects an invalid since date', function (): void {
    botsCommandVisitor();

    $this->artisan( 'analytics:bots', [ '--since' => 'not-a-date' ] )
        ->assertFailed();
} );

test( 'bots command exports results to csv', function (): void {
    botsCommandVisitor( [ 'user_agent' => 'GPTBot/1.0' ] );

    $directory = storage_path( 'app' );
    $before    = File::exists( $directory ) ? File::glob( $directory . '/analytics-bots-*.csv' ) : [];

    $this->artisan( 'analytics:bots', [ '--export' => 'csv' ] )
        ->expectsOutputToContain( 'Exported' )
        ->assertSuccessful();

    $after = File::glob( $directory . '/analytics-bots-*.csv' );
    $new   = array_values( array_diff( $after, $before ) );

    expect( $new )->toHaveCount( 1 );
    expect( File::get( $new[0] ) )->toContain( 'visitor_id,user_agent,bot_score' )->toContain( 'GPTBot/1.0' );

    File::delete( $new[0] );
} );

test( 'bots command rejects an unsupported export format', function (): void {
    botsCommandVisitor();

    $this->artisan( 'analytics:bots', [ '--export' => 'xml' ] )
        ->assertFailed();
} );
