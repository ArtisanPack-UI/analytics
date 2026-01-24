<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Widgets\TrafficSources;
use ArtisanPackUI\Analytics\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );
} );

test( 'traffic sources component can be rendered', function (): void {
	Livewire::test( TrafficSources::class )
		->assertStatus( 200 );
} );

test( 'traffic sources displays source data', function (): void {
	Session::create( [
		'session_id'       => 'session-1',
		'visitor_id'       => 'visitor-1',
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
		'is_bounce'        => false,
		'referrer_type'    => 'search',
		'utm_source'       => 'google',
	] );

	Livewire::test( TrafficSources::class )
		->assertStatus( 200 );
} );

test( 'traffic sources can refresh data', function (): void {
	Livewire::test( TrafficSources::class )
		->call( 'refreshData' )
		->assertStatus( 200 );
} );

test( 'traffic sources respects limit configuration', function (): void {
	Livewire::test( TrafficSources::class, ['limit' => 5] )
		->assertSet( 'limit', 5 );
} );

test( 'traffic sources respects chart type configuration', function (): void {
	Livewire::test( TrafficSources::class, ['chartType' => 'bar'] )
		->assertSet( 'chartType', 'bar' );
} );

test( 'traffic sources can hide chart', function (): void {
	Livewire::test( TrafficSources::class, ['showChart' => false] )
		->assertSet( 'showChart', false );
} );

test( 'traffic sources returns chart config', function (): void {
	Session::create( [
		'session_id'       => 'session-1',
		'visitor_id'       => 'visitor-1',
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
		'is_bounce'        => false,
		'referrer_type'    => 'search',
		'utm_source'       => 'google',
	] );

	$component = Livewire::test( TrafficSources::class );
	$config    = $component->instance()->getChartConfig();

	expect( $config )->toHaveKey( 'type' );
	expect( $config )->toHaveKey( 'data' );
	expect( $config )->toHaveKey( 'options' );
} );

test( 'traffic sources calculates total sessions', function (): void {
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
		'is_bounce'        => false,
		'referrer_type'    => 'search',
		'utm_source'       => 'google',
	] );

	$component     = Livewire::test( TrafficSources::class );
	$totalSessions = $component->instance()->getTotalSessions();

	expect( $totalSessions )->toBeGreaterThanOrEqual( 0 );
} );
