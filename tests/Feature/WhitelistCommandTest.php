<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\BotWhitelistEntry;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\BotDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses( RefreshDatabase::class );

test( 'whitelist list reports an empty whitelist', function (): void {
    config()->set( 'artisanpack.analytics.bot_detection.whitelist.user_agents', [] );
    config()->set( 'artisanpack.analytics.bot_detection.whitelist.ips', [] );

    $this->artisan( 'analytics:whitelist', [ 'action' => 'list' ] )
        ->expectsOutputToContain( 'The bot whitelist is empty.' )
        ->assertSuccessful();
} );

test( 'whitelist list shows config and database entries', function (): void {
    config()->set( 'artisanpack.analytics.bot_detection.whitelist.user_agents', [ 'Googlebot' ] );
    config()->set( 'artisanpack.analytics.bot_detection.whitelist.ips', [] );
    BotWhitelistEntry::create( [ 'type' => BotWhitelistEntry::TYPE_IP, 'value' => '1.2.3.4' ] );

    $this->artisan( 'analytics:whitelist', [ 'action' => 'list' ] )
        ->expectsOutputToContain( 'Googlebot' )
        ->expectsOutputToContain( '1.2.3.4' )
        ->assertSuccessful();
} );

test( 'whitelist add stores a user agent entry', function (): void {
    $this->artisan( 'analytics:whitelist', [ 'action' => 'add', '--user-agent' => 'Googlebot' ] )
        ->assertSuccessful();

    expect( BotWhitelistEntry::query()->userAgents()->where( 'value', 'Googlebot' )->exists() )->toBeTrue();
} );

test( 'whitelist add stores an ip entry', function (): void {
    $this->artisan( 'analytics:whitelist', [ 'action' => 'add', '--ip' => '203.0.113.5' ] )
        ->assertSuccessful();

    expect( BotWhitelistEntry::query()->ips()->where( 'value', '203.0.113.5' )->exists() )->toBeTrue();
} );

test( 'whitelist add is idempotent', function (): void {
    $this->artisan( 'analytics:whitelist', [ 'action' => 'add', '--user-agent' => 'Googlebot' ] )->assertSuccessful();
    $this->artisan( 'analytics:whitelist', [ 'action' => 'add', '--user-agent' => 'Googlebot' ] )
        ->expectsOutputToContain( 'already in the whitelist' )
        ->assertSuccessful();

    expect( BotWhitelistEntry::query()->where( 'value', 'Googlebot' )->count() )->toBe( 1 );
} );

test( 'whitelist remove deletes a database entry', function (): void {
    BotWhitelistEntry::create( [ 'type' => BotWhitelistEntry::TYPE_USER_AGENT, 'value' => 'Googlebot' ] );

    $this->artisan( 'analytics:whitelist', [ 'action' => 'remove', '--user-agent' => 'Googlebot' ] )
        ->expectsOutputToContain( 'Removed' )
        ->assertSuccessful();

    expect( BotWhitelistEntry::query()->where( 'value', 'Googlebot' )->exists() )->toBeFalse();
} );

test( 'whitelist remove warns when the entry is missing', function (): void {
    $this->artisan( 'analytics:whitelist', [ 'action' => 'remove', '--ip' => '9.9.9.9' ] )
        ->expectsOutputToContain( 'was not found' )
        ->assertSuccessful();
} );

test( 'whitelist add requires a target option', function (): void {
    $this->artisan( 'analytics:whitelist', [ 'action' => 'add' ] )
        ->assertFailed();
} );

test( 'whitelist add rejects providing both options', function (): void {
    $this->artisan( 'analytics:whitelist', [ 'action' => 'add', '--user-agent' => 'Googlebot', '--ip' => '1.2.3.4' ] )
        ->assertFailed();
} );

test( 'whitelist add rejects an invalid ip', function (): void {
    $this->artisan( 'analytics:whitelist', [ 'action' => 'add', '--ip' => 'not-an-ip' ] )
        ->expectsOutputToContain( 'Invalid --ip value' )
        ->assertFailed();

    expect( BotWhitelistEntry::query()->where( 'value', 'not-an-ip' )->exists() )->toBeFalse();
} );

test( 'whitelist rejects an unknown action', function (): void {
    $this->artisan( 'analytics:whitelist', [ 'action' => 'frobnicate' ] )
        ->assertFailed();
} );

test( 'database whitelist entries exempt visitors from bot scoring', function (): void {
    config()->set( 'artisanpack.analytics.bot_detection.whitelist.user_agents', [] );
    config()->set( 'artisanpack.analytics.bot_detection.whitelist.ips', [] );

    BotWhitelistEntry::create( [ 'type' => BotWhitelistEntry::TYPE_USER_AGENT, 'value' => 'Googlebot' ] );

    $visitor = Visitor::create( [
        'fingerprint'   => bin2hex( random_bytes( 16 ) ),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'device_type'   => 'desktop',
        'user_agent'    => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
        'is_bot'        => false,
    ] );

    expect( app( BotDetector::class )->isWhitelisted( $visitor ) )->toBeTrue();
} );

test( 'database ip whitelist entries exempt visitors from bot scoring', function (): void {
    config()->set( 'artisanpack.analytics.bot_detection.whitelist.user_agents', [] );
    config()->set( 'artisanpack.analytics.bot_detection.whitelist.ips', [] );

    BotWhitelistEntry::create( [ 'type' => BotWhitelistEntry::TYPE_IP, 'value' => '203.0.113.5' ] );

    $visitor = Visitor::create( [
        'fingerprint'   => bin2hex( random_bytes( 16 ) ),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
        'device_type'   => 'desktop',
        'user_agent'    => 'Mozilla/5.0 (compatible; Googlebot/2.1)',
        'ip_address'    => '203.0.113.5',
        'is_bot'        => false,
    ] );

    expect( app( BotDetector::class )->isWhitelisted( $visitor ) )->toBeTrue();
} );
