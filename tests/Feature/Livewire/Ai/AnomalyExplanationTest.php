<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Ai\AnomalyExplanation;
use Livewire\Livewire;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'populates hypotheses when explain is called', function (): void {
	$this->prompter->queue( [
		'hypotheses'             => [
			[ 'cause' => 'Reddit thread', 'confidence' => 'high', 'evidence' => [ 'x' ] ],
		],
		'recommended_next_steps' => [ 'Verify UTMs' ],
	] );

	Livewire::test( AnomalyExplanation::class, [
		'anomaly' => [
			'metric'    => 'pageviews',
			'direction' => 'up',
			'magnitude' => 245.0,
			'date'      => '2026-05-11',
		],
		'context' => [ 'referrer_deltas' => [ 'reddit.com' => 340 ] ],
	] )
		->call( 'explain' )
		->assertSet( 'error', null );
} );

it( 'renders the disabled state when the feature toggle is off', function (): void {
	app( ArtisanPackUI\Ai\Contracts\FeatureRegistry::class )->disable( 'analytics.explain_anomaly' );

	Livewire::test( AnomalyExplanation::class )
		->assertSee( 'AI anomaly explanations are currently disabled.' );
} );
