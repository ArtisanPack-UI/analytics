<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\PageAnalytics;
use ArtisanPackUI\Analytics\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );
} );

test( 'page analytics component can be rendered', function (): void {
	Livewire::test( PageAnalytics::class )
		->assertStatus( 200 );
} );

test( 'page analytics displays page data', function (): void {
	PageView::create( [
		'path'       => '/test-page',
		'title'      => 'Test Page',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	Livewire::test( PageAnalytics::class, ['path' => '/test-page'] )
		->assertStatus( 200 );
} );

test( 'page analytics can change path', function (): void {
	Livewire::test( PageAnalytics::class, ['path' => '/original'] )
		->assertSet( 'path', '/original' )
		->call( 'setPath', '/new-path' )
		->assertSet( 'path', '/new-path' );
} );

test( 'page analytics can refresh data', function (): void {
	Livewire::test( PageAnalytics::class )
		->call( 'refreshData' )
		->assertStatus( 200 );
} );

test( 'page analytics can change date range', function (): void {
	Livewire::test( PageAnalytics::class )
		->call( 'setDateRange', 'last_30_days' )
		->assertSet( 'dateRangePreset', 'last_30_days' );
} );

test( 'page analytics respects compact mode', function (): void {
	Livewire::test( PageAnalytics::class, ['compact' => true] )
		->assertSet( 'compact', true );

	Livewire::test( PageAnalytics::class, ['compact' => false] )
		->assertSet( 'compact', false );
} );

test( 'page analytics respects show chart configuration', function (): void {
	Livewire::test( PageAnalytics::class, ['showChart' => true] )
		->assertSet( 'showChart', true );

	Livewire::test( PageAnalytics::class, ['showChart' => false] )
		->assertSet( 'showChart', false );
} );

test( 'page analytics returns page views count', function (): void {
	PageView::create( [
		'path'       => '/test-page',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	PageView::create( [
		'path'       => '/test-page',
		'session_id' => 'session-2',
		'visitor_id' => 'visitor-2',
		'created_at' => now(),
	] );

	$component  = Livewire::test( PageAnalytics::class, ['path' => '/test-page'] );
	$pageViews  = $component->instance()->getPageViews();

	expect( $pageViews )->toBeInt();
} );

test( 'page analytics returns visitors count', function (): void {
	$component = Livewire::test( PageAnalytics::class, ['path' => '/test-page'] );
	$visitors  = $component->instance()->getVisitors();

	expect( $visitors )->toBeInt();
} );

test( 'page analytics returns bounce rate', function (): void {
	$component  = Livewire::test( PageAnalytics::class, ['path' => '/test-page'] );
	$bounceRate = $component->instance()->getBounceRate();

	expect( $bounceRate )->toBeFloat();
} );

test( 'page analytics returns formatted values', function (): void {
	$component = Livewire::test( PageAnalytics::class, ['path' => '/test-page'] );

	$formattedPageViews  = $component->instance()->getFormattedPageViews();
	$formattedVisitors   = $component->instance()->getFormattedVisitors();
	$formattedBounceRate = $component->instance()->getFormattedBounceRate();

	expect( $formattedPageViews )->toBeString();
	expect( $formattedVisitors )->toBeString();
	expect( $formattedBounceRate )->toBeString();
} );

test( 'page analytics returns sparkline config', function (): void {
	$component = Livewire::test( PageAnalytics::class, ['path' => '/test-page'] );
	$config    = $component->instance()->getSparklineConfig();

	expect( $config )->toHaveKey( 'type' );
	expect( $config )->toHaveKey( 'data' );
	expect( $config )->toHaveKey( 'options' );
	expect( $config['type'] )->toBe( 'line' );
} );
