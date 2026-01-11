<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Widgets\RealtimeVisitors;
use ArtisanPackUI\Analytics\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );
} );

test( 'realtime visitors component can be rendered', function (): void {
	Livewire::test( RealtimeVisitors::class )
		->assertStatus( 200 );
} );

test( 'realtime visitors displays visitor count', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$component = Livewire::test( RealtimeVisitors::class )
		->assertStatus( 200 );

	expect( $component->instance()->visitorCount )->toBeInt();
} );

test( 'realtime visitors can toggle polling', function (): void {
	Livewire::test( RealtimeVisitors::class )
		->assertSet( 'pollingEnabled', true )
		->call( 'togglePolling' )
		->assertSet( 'pollingEnabled', false )
		->call( 'togglePolling' )
		->assertSet( 'pollingEnabled', true );
} );

test( 'realtime visitors can poll for updates', function (): void {
	Livewire::test( RealtimeVisitors::class )
		->call( 'poll' )
		->assertStatus( 200 );
} );

test( 'realtime visitors can refresh data', function (): void {
	Livewire::test( RealtimeVisitors::class )
		->call( 'refreshData' )
		->assertStatus( 200 );
} );

test( 'realtime visitors respects active minutes configuration', function (): void {
	Livewire::test( RealtimeVisitors::class, ['activeMinutes' => 10] )
		->assertSet( 'activeMinutes', 10 );
} );

test( 'realtime visitors respects polling interval configuration', function (): void {
	Livewire::test( RealtimeVisitors::class, ['pollingInterval' => 5000] )
		->assertSet( 'pollingInterval', 5000 );
} );

test( 'realtime visitors can be initialized with polling disabled', function (): void {
	Livewire::test( RealtimeVisitors::class, ['pollingEnabled' => false] )
		->assertSet( 'pollingEnabled', false );
} );

test( 'realtime visitors returns trend indicator', function (): void {
	$component = Livewire::test( RealtimeVisitors::class );
	$trend     = $component->instance()->getTrend();

	expect( $trend )->toBeIn( ['up', 'down', 'stable'] );
} );

test( 'realtime visitors returns trend difference', function (): void {
	$component  = Livewire::test( RealtimeVisitors::class );
	$difference = $component->instance()->getTrendDifference();

	expect( $difference )->toBeInt();
	expect( $difference )->toBeGreaterThanOrEqual( 0 );
} );

test( 'realtime visitors returns status class', function (): void {
	$component   = Livewire::test( RealtimeVisitors::class );
	$statusClass = $component->instance()->getStatusClass();

	expect( $statusClass )->toBeString();
} );
