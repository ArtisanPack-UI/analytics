<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Ai\InsightSummary;
use Livewire\Livewire;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'renders and populates the summary when analyze runs', function (): void {
	$this->prompter->queue( [
		'summary'    => 'Traffic grew nicely.',
		'highlights' => [ 'Organic +18%' ],
		'concerns'   => [],
	] );

	Livewire::test( InsightSummary::class, [
		'dateFrom' => '2026-05-01',
		'dateTo'   => '2026-05-07',
		'metrics'  => [ 'pageviews' => 100 ],
	] )
		->call( 'summarize' )
		->assertSet( 'summary', 'Traffic grew nicely.' )
		->assertSet( 'error', null );
} );

it( 'renders the disabled state when the feature toggle is off', function (): void {
	app( ArtisanPackUI\Ai\Contracts\FeatureRegistry::class )->disable( 'analytics.insight_summary' );

	Livewire::test( InsightSummary::class )
		->assertSee( 'AI insight summaries are currently disabled.' );
} );
