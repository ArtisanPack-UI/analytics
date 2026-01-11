<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Widgets\VisitorsChart;
use ArtisanPackUI\Analytics\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );
} );

test( 'visitors chart component can be rendered', function (): void {
	Livewire::test( VisitorsChart::class )
		->assertStatus( 200 );
} );

test( 'visitors chart can change granularity', function (): void {
	Livewire::test( VisitorsChart::class )
		->call( 'setGranularity', 'week' )
		->assertSet( 'granularity', 'week' );
} );

test( 'visitors chart can refresh data', function (): void {
	Livewire::test( VisitorsChart::class )
		->call( 'refreshData' )
		->assertStatus( 200 );
} );

test( 'visitors chart respects initial granularity', function (): void {
	Livewire::test( VisitorsChart::class, ['granularity' => 'month'] )
		->assertSet( 'granularity', 'month' );
} );

test( 'visitors chart respects height configuration', function (): void {
	Livewire::test( VisitorsChart::class, ['height' => 400] )
		->assertSet( 'height', 400 );
} );

test( 'visitors chart returns chart config', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$component = Livewire::test( VisitorsChart::class );
	$config    = $component->instance()->getChartConfig();

	expect( $config )->toHaveKey( 'type' );
	expect( $config )->toHaveKey( 'data' );
	expect( $config )->toHaveKey( 'options' );
	expect( $config['type'] )->toBe( 'line' );
} );

test( 'visitors chart can change date range', function (): void {
	Livewire::test( VisitorsChart::class )
		->call( 'setDateRange', 'last_30_days' )
		->assertSet( 'dateRangePreset', 'last_30_days' );
} );
