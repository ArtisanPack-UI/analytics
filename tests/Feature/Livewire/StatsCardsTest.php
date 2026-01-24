<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Widgets\StatsCards;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );
} );

test( 'stats cards component can be rendered', function (): void {
	Livewire::test( StatsCards::class )
		->assertStatus( 200 );
} );

test( 'stats cards displays statistics', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	Session::create( [
		'session_id'       => 'session-1',
		'visitor_id'       => 'visitor-1',
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
		'is_bounce'        => false,
		'referrer_type'    => 'direct',
	] );

	Livewire::test( StatsCards::class )
		->assertSee( '1' )
		->assertStatus( 200 );
} );

test( 'stats cards can refresh data', function (): void {
	Livewire::test( StatsCards::class )
		->call( 'refreshData' )
		->assertStatus( 200 );
} );

test( 'stats cards can change date range', function (): void {
	Livewire::test( StatsCards::class )
		->call( 'setDateRange', 'last_7_days' )
		->assertSet( 'dateRangePreset', 'last_7_days' );
} );

test( 'stats cards respects visible stats configuration', function (): void {
	Livewire::test( StatsCards::class, ['visibleStats' => ['pageviews', 'visitors']] )
		->assertSet( 'visibleStats', ['pageviews', 'visitors'] );
} );

test( 'stats cards shows comparison when enabled', function (): void {
	Livewire::test( StatsCards::class, ['showComparison' => true] )
		->assertSet( 'showComparison', true );
} );

test( 'stats cards hides comparison when disabled', function (): void {
	Livewire::test( StatsCards::class, ['showComparison' => false] )
		->assertSet( 'showComparison', false );
} );
