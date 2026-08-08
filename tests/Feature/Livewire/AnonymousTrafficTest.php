<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\AnalyticsDashboard;
use ArtisanPackUI\Analytics\Http\Livewire\Widgets\AnonymousTraffic;
use ArtisanPackUI\Analytics\Http\Livewire\Widgets\StatsCards;
use ArtisanPackUI\Analytics\Models\AnonymousPageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'artisanpack.analytics.local.queue_processing', false );
    config()->set( 'artisanpack.analytics.local.enabled', true );
    config()->set( 'artisanpack.analytics.privacy.anonymous_mode', true );
} );

/**
 * Create an anonymous page view for the widget tests.
 */
function anonymousWidgetPageView( string $path = '/', string $deviceType = 'mobile' ): void
{
    AnonymousPageView::create( [
        'path'          => $path,
        'title'         => 'Anonymous page',
        'referrer_host' => 'example.com',
        'device_type'   => $deviceType,
        'created_at'    => now(),
    ] );
}

test( 'anonymous traffic widget can be rendered', function (): void {
    Livewire::test( AnonymousTraffic::class )
        ->assertStatus( 200 );
} );

test( 'anonymous traffic widget loads the split on mount', function (): void {
    anonymousWidgetPageView( '/a' );
    anonymousWidgetPageView( '/a' );
    anonymousWidgetPageView( '/b' );

    Livewire::test( AnonymousTraffic::class )
        ->assertSet( 'enabled', true )
        ->assertSet( 'anonymousPageviews', 3 )
        ->assertSet( 'anonymousPercentage', 100.0 );
} );

test( 'anonymous traffic widget explains that the feature is off', function (): void {
    config()->set( 'artisanpack.analytics.privacy.anonymous_mode', false );

    Livewire::test( AnonymousTraffic::class )
        ->assertSet( 'enabled', false )
        ->assertSee( 'Anonymous mode is turned off' );
} );

test( 'anonymous traffic widget explains an empty period without implying a fault', function (): void {
    Livewire::test( AnonymousTraffic::class )
        ->assertSet( 'anonymousPageviews', 0 )
        ->assertSee( 'no pre-consent page views have been recorded' )
        ->assertDontSee( 'Anonymous mode is turned off' );
} );

test( 'the dashboard hides the anonymous toggle when nothing has been collected', function (): void {
    Livewire::test( AnalyticsDashboard::class )
        ->assertSet( 'hasAnonymousData', false )
        ->assertDontSee( 'Include anonymous traffic' );
} );

test( 'the dashboard hides the anonymous toggle when the feature is off', function (): void {
    config()->set( 'artisanpack.analytics.privacy.anonymous_mode', false );

    anonymousWidgetPageView( '/a' );

    Livewire::test( AnalyticsDashboard::class )
        ->assertSet( 'hasAnonymousData', false );
} );

test( 'the dashboard offers the anonymous toggle and tab once rows exist', function (): void {
    anonymousWidgetPageView( '/a' );

    $component = Livewire::test( AnalyticsDashboard::class )
        ->assertSet( 'hasAnonymousData', true )
        ->assertSee( 'Include anonymous traffic' );

    expect( $component->instance()->getTabs() )->toHaveKey( 'anonymous' );
} );

test( 'the dashboard toggle dispatches the anonymous inclusion event', function (): void {
    anonymousWidgetPageView( '/a' );

    Livewire::test( AnalyticsDashboard::class )
        ->call( 'toggleAnonymous' )
        ->assertDispatched( 'analytics-anonymous-toggled', includeAnonymous: true );
} );

test( 'widgets sync with the anonymous toggle and refresh their figures', function (): void {
    anonymousWidgetPageView( '/a' );
    anonymousWidgetPageView( '/b' );

    Livewire::test( StatsCards::class )
        ->assertSet( 'stats.pageviews', 0 )
        ->call( 'syncAnonymousInclusion', true )
        ->assertSet( 'includeAnonymous', true )
        ->assertSet( 'stats.pageviews', 2 )
        ->assertSet( 'stats.anonymous_pageviews', 2 );
} );

test( 'stats cards label which metrics can include anonymous traffic', function (): void {
    anonymousWidgetPageView( '/a' );

    Livewire::test( StatsCards::class )
        ->assertDontSee( 'Includes anonymous traffic' )
        ->call( 'syncAnonymousInclusion', true )
        ->assertSee( 'Includes anonymous traffic' )
        ->assertSee( 'Consented visitors only' );
} );

test( 'the dashboard announces the traffic scope when anonymous rows are included', function (): void {
    anonymousWidgetPageView( '/a' );

    $component = Livewire::test( AnalyticsDashboard::class );

    expect( $component->instance()->getAnonymousAnnouncement() )
        ->toBe( 'Showing consented visitors only.' );

    $component->call( 'syncAnonymousInclusion', true );

    expect( $component->instance()->getAnonymousAnnouncement() )
        ->toContain( 'consented and anonymous visitors' );
} );

test( 'chart series are renamed for their scope when anonymous rows are included', function (): void {
    anonymousWidgetPageView( '/a' );

    $component = Livewire::test( ArtisanPackUI\Analytics\Http\Livewire\Widgets\VisitorsChart::class );

    $labels = fn (): array => array_column( $component->get( 'chartData' )['datasets'] ?? [], 'label' );

    expect( $labels() )->toContain( 'Page Views' )->toContain( 'Visitors' );

    $component->call( 'syncAnonymousInclusion', true );

    // Two series in one chart no longer describe the same population, so
    // neither is left carrying its old unqualified name.
    expect( $labels() )
        ->toContain( 'Page Views (incl. anonymous)' )
        ->toContain( 'Visitors (consented only)' );
} );

test( 'the top pages widget only shows the anonymous column when combined', function (): void {
    anonymousWidgetPageView( '/a' );

    $component = Livewire::test( ArtisanPackUI\Analytics\Http\Livewire\Widgets\TopPages::class );

    expect( $component->instance()->getColumns() )->not->toHaveKey( 'anonymous_views' );

    $component->call( 'syncAnonymousInclusion', true );

    expect( $component->instance()->getColumns() )->toHaveKey( 'anonymous_views' );
} );
