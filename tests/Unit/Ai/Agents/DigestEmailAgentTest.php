<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Ai\Agents\DigestEmailAgent;
use Tests\Feature\Ai\AiAgentTestSetup;

beforeEach( function (): void {
	$this->prompter = AiAgentTestSetup::bootstrap( $this->app );
} );

it( 'produces a narrative digest when given items', function (): void {
	$this->prompter->queue( [
		'summary'    => 'Your site had a great week — pageviews up 18%.',
		'key_points' => [ 'Top page: /pricing (3,412 views)', 'Organic search up 22%' ],
		'caveats'    => [ 'Mobile bounce ticked up 4pp' ],
	] );

	$result = DigestEmailAgent::for( [
		'items'  => [ [ 'metric' => 'pageviews', 'value' => 12000 ] ],
		'focus'  => 'week-over-week change',
		'length' => 'detailed',
	] )->run();

	expect( $result['summary'] )->toContain( '18%' );
	expect( $result['key_points'] )->toHaveCount( 2 );
	expect( $result['caveats'] )->toHaveCount( 1 );
} );

it( 'short-circuits when items is empty', function (): void {
	$result = DigestEmailAgent::for( [ 'items' => [] ] )->run();

	expect( $result['summary'] )->toBe( 'No items to summarize.' );
	expect( $result['key_points'] )->toBe( [] );
} );

it( 'uses claude-sonnet-4-6 as the default model', function (): void {
	$agent = new DigestEmailAgent();
	expect( $agent->defaultModel )->toBe( 'claude-sonnet-4-6' );
	expect( $agent->featureKey )->toBe( 'analytics.digest_email' );
} );

it( 'inherits the SummarizationAgent output schema shape', function (): void {
	$schema = ( new DigestEmailAgent() )->outputSchema();

	expect( $schema['properties'] )->toHaveKeys( [ 'summary', 'key_points', 'caveats' ] );
} );
