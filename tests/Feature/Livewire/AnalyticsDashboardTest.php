<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\AnalyticsDashboard;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );
} );

test( 'analytics dashboard component can be rendered', function (): void {
	Livewire::test( AnalyticsDashboard::class )
		->assertStatus( 200 );
} );

test( 'analytics dashboard displays stats', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	Livewire::test( AnalyticsDashboard::class )
		->assertStatus( 200 );
} );

test( 'analytics dashboard can switch tabs', function (): void {
	Livewire::test( AnalyticsDashboard::class )
		->assertSet( 'activeTab', 'overview' )
		->call( 'switchTab', 'pages' )
		->assertSet( 'activeTab', 'pages' )
		->call( 'switchTab', 'traffic' )
		->assertSet( 'activeTab', 'traffic' )
		->call( 'switchTab', 'audience' )
		->assertSet( 'activeTab', 'audience' );
} );

test( 'analytics dashboard can change date range', function (): void {
	Livewire::test( AnalyticsDashboard::class )
		->call( 'setDateRange', 'last_30_days' )
		->assertSet( 'dateRangePreset', 'last_30_days' );
} );

test( 'analytics dashboard can refresh data', function (): void {
	Livewire::test( AnalyticsDashboard::class )
		->call( 'refreshData' )
		->assertStatus( 200 );
} );

test( 'analytics dashboard returns available tabs', function (): void {
	$component = Livewire::test( AnalyticsDashboard::class );
	$tabs      = $component->instance()->getTabs();

	expect( $tabs )->toHaveKey( 'overview' );
	expect( $tabs )->toHaveKey( 'pages' );
	expect( $tabs )->toHaveKey( 'traffic' );
	expect( $tabs )->toHaveKey( 'audience' );
	expect( $tabs['overview'] )->toHaveKey( 'label' );
	expect( $tabs['overview'] )->toHaveKey( 'icon' );
} );

test( 'analytics dashboard can export csv', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$component = Livewire::test( AnalyticsDashboard::class );
	$response  = $component->instance()->exportCsv();

	expect( $response )->toBeInstanceOf( Symfony\Component\HttpFoundation\StreamedResponse::class );
} );

test( 'analytics dashboard can export json', function (): void {
	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$component = Livewire::test( AnalyticsDashboard::class );
	$response  = $component->instance()->exportJson();

	expect( $response )->toBeInstanceOf( Symfony\Component\HttpFoundation\StreamedResponse::class );
} );

test( 'analytics dashboard respects initial active tab', function (): void {
	Livewire::test( AnalyticsDashboard::class, ['activeTab' => 'pages'] )
		->assertSet( 'activeTab', 'pages' );
} );

test( 'analytics dashboard loads all data on mount', function (): void {
	Session::create( [
		'session_id'       => 'session-1',
		'visitor_id'       => 'visitor-1',
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
		'is_bounce'        => false,
		'referrer_type'    => 'direct',
	] );

	PageView::create( [
		'path'       => '/page-1',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	$component = Livewire::test( AnalyticsDashboard::class );

	expect( $component->instance()->stats )->toBeArray();
	expect( $component->instance()->chartData )->toBeArray();
	expect( $component->instance()->topPages )->toBeInstanceOf( Illuminate\Support\Collection::class );
	expect( $component->instance()->trafficSources )->toBeInstanceOf( Illuminate\Support\Collection::class );
} );
