<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Http\Livewire\Ai\SegmentInsight;
use Livewire\Livewire;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'populates patterns when analyze is called', function (): void {
	$this->prompter->queue( [
		'patterns' => [
			[
				'observation'      => 'Mobile bounce is 62% vs 41% baseline',
				'significance'     => 'high',
				'suggested_action' => 'Audit mobile viewport',
			],
		],
	] );

	Livewire::test( SegmentInsight::class, [
		'segmentType'  => 'device',
		'segmentValue' => 'mobile',
		'metrics'      => [ 'bounce_rate' => 0.62 ],
		'baseline'     => [ 'bounce_rate' => 0.41 ],
	] )
		->call( 'analyze' )
		->assertSet( 'error', null );
} );

it( 'renders the disabled state when the feature toggle is off', function (): void {
	app( ArtisanPackUI\Ai\Contracts\FeatureRegistry::class )->disable( 'analytics.segment_insight' );

	Livewire::test( SegmentInsight::class )
		->assertSee( 'AI segment insights are currently disabled.' );
} );
