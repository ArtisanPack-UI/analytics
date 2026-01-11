<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Widgets\TopPages;
use ArtisanPackUI\Analytics\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.local.queue_processing', false );
	config()->set( 'artisanpack.analytics.local.enabled', true );
} );

test( 'top pages component can be rendered', function (): void {
	Livewire::test( TopPages::class )
		->assertStatus( 200 );
} );

test( 'top pages displays page data', function (): void {
	PageView::create( [
		'path'       => '/popular-page',
		'title'      => 'Popular Page',
		'session_id' => 'session-1',
		'visitor_id' => 'visitor-1',
		'created_at' => now(),
	] );

	Livewire::test( TopPages::class )
		->assertSee( '/popular-page' );
} );

test( 'top pages can be sorted by column', function (): void {
	Livewire::test( TopPages::class )
		->call( 'sortByColumn', 'views' )
		->assertSet( 'sortBy', 'views' );
} );

test( 'top pages toggles sort direction on same column', function (): void {
	Livewire::test( TopPages::class, ['sortBy' => 'views', 'sortDirection' => 'desc'] )
		->call( 'sortByColumn', 'views' )
		->assertSet( 'sortDirection', 'asc' );
} );

test( 'top pages resets to desc when sorting new column', function (): void {
	Livewire::test( TopPages::class, ['sortBy' => 'path', 'sortDirection' => 'asc'] )
		->call( 'sortByColumn', 'views' )
		->assertSet( 'sortBy', 'views' )
		->assertSet( 'sortDirection', 'desc' );
} );

test( 'top pages can refresh data', function (): void {
	Livewire::test( TopPages::class )
		->call( 'refreshData' )
		->assertStatus( 200 );
} );

test( 'top pages respects limit configuration', function (): void {
	Livewire::test( TopPages::class, ['limit' => 5] )
		->assertSet( 'limit', 5 );
} );

test( 'top pages returns column definitions', function (): void {
	$component = Livewire::test( TopPages::class );
	$columns   = $component->instance()->getColumns();

	expect( $columns )->toHaveKey( 'path' );
	expect( $columns )->toHaveKey( 'views' );
	expect( $columns )->toHaveKey( 'unique_views' );
	expect( $columns['path']['sortable'] )->toBeTrue();
} );
