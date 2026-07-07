<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Analytics\Ai\Agents\AnomalyExplanationAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns hypotheses and next steps when the prompter responds', function (): void {
	$this->prompter->queue( [
		'hypotheses'             => [
			[
				'cause'      => 'Reddit thread on /pricing went viral',
				'confidence' => 'high',
				'evidence'   => [ 'Referrer reddit.com rose 340%', 'Same-day traffic to /pricing' ],
			],
		],
		'recommended_next_steps' => [ 'Verify UTMs on /pricing', 'Check server capacity' ],
	] );

	$result = AnomalyExplanationAgent::for( [
		'anomaly' => [
			'metric'    => 'pageviews',
			'direction' => 'up',
			'magnitude' => 245.0,
			'date'      => '2026-05-11',
		],
		'context' => [
			'recent_content_changes' => [],
			'referrer_deltas'        => [ 'reddit.com' => 340 ],
			'campaign_launches'      => [],
		],
	] )->run();

	expect( $result['hypotheses'] )->toHaveCount( 1 );
	expect( $result['hypotheses'][0]['confidence'] )->toBe( 'high' );
	expect( $result['recommended_next_steps'] )->toHaveCount( 2 );
} );

it( 'clamps invalid confidence levels to low', function (): void {
	$this->prompter->queue( [
		'hypotheses'             => [
			[ 'cause' => 'x', 'confidence' => 'not-a-level', 'evidence' => [ 'a' ] ],
		],
		'recommended_next_steps' => [],
	] );

	$result = AnomalyExplanationAgent::for( [
		'anomaly' => [
			'metric'    => 'pageviews',
			'direction' => 'down',
			'magnitude' => -50,
			'date'      => '2026-05-11',
		],
		'context' => [],
	] )->run();

	expect( $result['hypotheses'][0]['confidence'] )->toBe( 'low' );
} );

it( 'raises FeatureError when anomaly is missing required fields', function (): void {
	expect( fn () => AnomalyExplanationAgent::for( [ 'anomaly' => [] ] )->run() )
		->toThrow( FeatureError::class );
} );

it( 'drops hypotheses with an empty cause', function (): void {
	$this->prompter->queue( [
		'hypotheses'             => [
			[ 'cause' => '', 'confidence' => 'high', 'evidence' => [] ],
			[ 'cause' => 'A real cause', 'confidence' => 'medium', 'evidence' => [ 'e' ] ],
		],
		'recommended_next_steps' => [],
	] );

	$result = AnomalyExplanationAgent::for( [
		'anomaly' => [
			'metric'    => 'conversions',
			'direction' => 'down',
			'magnitude' => -22,
			'date'      => '2026-05-11',
		],
		'context' => [],
	] )->run();

	expect( $result['hypotheses'] )->toHaveCount( 1 );
	expect( $result['hypotheses'][0]['cause'] )->toBe( 'A real cause' );
} );
