<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Analytics\Ai\Agents\SegmentInsightAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns patterns when the prompter responds', function (): void {
	$this->prompter->queue( [
		'patterns' => [
			[
				'observation'      => 'Mobile bounce rate is 62% vs. 41% baseline',
				'significance'     => 'high',
				'suggested_action' => 'Audit mobile viewport on /pricing',
			],
		],
	] );

	$result = SegmentInsightAgent::for( [
		'segment'  => [ 'type' => 'device', 'value' => 'mobile' ],
		'metrics'  => [ 'bounce_rate' => 0.62 ],
		'baseline' => [ 'bounce_rate' => 0.41 ],
	] )->run();

	expect( $result['patterns'] )->toHaveCount( 1 );
	expect( $result['patterns'][0]['significance'] )->toBe( 'high' );
} );

it( 'raises FeatureError when segment is missing', function (): void {
	expect( fn () => SegmentInsightAgent::for( [
		'metrics'  => [ 'x' => 1 ],
		'baseline' => [ 'x' => 2 ],
	] )->run() )->toThrow( FeatureError::class );
} );

it( 'drops patterns with an empty observation', function (): void {
	$this->prompter->queue( [
		'patterns' => [
			[ 'observation' => '', 'significance' => 'high', 'suggested_action' => 'x' ],
			[ 'observation' => 'valid', 'significance' => 'medium', 'suggested_action' => 'do a thing' ],
		],
	] );

	$result = SegmentInsightAgent::for( [
		'segment'  => [ 'type' => 'device', 'value' => 'mobile' ],
		'metrics'  => [ 'bounce_rate' => 0.62 ],
		'baseline' => [ 'bounce_rate' => 0.41 ],
	] )->run();

	expect( $result['patterns'] )->toHaveCount( 1 );
} );

it( 'clamps invalid significance to low', function (): void {
	$this->prompter->queue( [
		'patterns' => [
			[
				'observation'      => 'x',
				'significance'     => 'critical',
				'suggested_action' => 'y',
			],
		],
	] );

	$result = SegmentInsightAgent::for( [
		'segment'  => [ 'type' => 'device', 'value' => 'mobile' ],
		'metrics'  => [ 'bounce_rate' => 0.62 ],
		'baseline' => [ 'bounce_rate' => 0.41 ],
	] )->run();

	expect( $result['patterns'][0]['significance'] )->toBe( 'low' );
} );
