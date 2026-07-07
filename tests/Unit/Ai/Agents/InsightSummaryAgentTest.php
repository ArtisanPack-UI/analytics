<?php

declare( strict_types=1 );

use ArtisanPackUI\Ai\Exceptions\FeatureError;
use ArtisanPackUI\Analytics\Ai\Agents\InsightSummaryAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'returns the shaped summary when the prompter responds', function (): void {
	$this->prompter->queue( [
		'summary'    => 'Traffic grew 22% week-over-week driven by /pricing.',
		'highlights' => [ 'Top page: /pricing at 3,412 views', 'Organic up 18%' ],
		'concerns'   => [ 'Mobile bounce rate spiked to 68%' ],
	] );

	$result = InsightSummaryAgent::for( [
		'date_range' => [ 'from' => '2026-05-01', 'to' => '2026-05-07' ],
		'metrics'    => [ 'pageviews' => 12000, 'visitors' => 4200 ],
	] )->run();

	expect( $result['summary'] )->toContain( '22%' );
	expect( $result['highlights'] )->toHaveCount( 2 );
	expect( $result['concerns'] )->toHaveCount( 1 );
} );

it( 'raises FeatureError when date_range is missing', function (): void {
	expect( fn () => InsightSummaryAgent::for( [ 'metrics' => [ 'x' => 1 ] ] )->run() )
		->toThrow( FeatureError::class );
} );

it( 'raises FeatureError when metrics is empty', function (): void {
	expect( fn () => InsightSummaryAgent::for( [
		'date_range' => [ 'from' => '2026-05-01', 'to' => '2026-05-07' ],
		'metrics'    => [],
	] )->run() )->toThrow( FeatureError::class );
} );

it( 'includes compare_to in the prompter message when provided', function (): void {
	$this->prompter->queue( [
		'summary'    => 'ok',
		'highlights' => [],
		'concerns'   => [],
	] );

	InsightSummaryAgent::for( [
		'date_range' => [ 'from' => '2026-05-01', 'to' => '2026-05-07' ],
		'metrics'    => [ 'pageviews' => 100 ],
		'compare_to' => [ 'from' => '2026-04-24', 'to' => '2026-04-30' ],
	] )->run();

	$texts = collect( $this->prompter->calls[0]['message'] )->pluck( 'text' );
	expect( $texts->contains( fn ( string $t ): bool => str_contains( $t, 'Compare to' ) ) )->toBeTrue();
} );

it( 'uses the claude-sonnet-4-6 default model', function (): void {
	$agent = new InsightSummaryAgent();
	expect( $agent->defaultModel )->toBe( 'claude-sonnet-4-6' );
	expect( $agent->stream )->toBeTrue();
} );
