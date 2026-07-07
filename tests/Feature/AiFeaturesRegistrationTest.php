<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Ai\Agents\AnomalyExplanationAgent;
use ArtisanPackUI\Analytics\Ai\Agents\DigestEmailAgent;
use ArtisanPackUI\Analytics\Ai\Agents\InsightSummaryAgent;
use ArtisanPackUI\Analytics\Ai\Agents\SegmentInsightAgent;
use ArtisanPackUI\Analytics\AnalyticsServiceProvider;

it( 'exposes the four analytics AI features via aiFeatures()', function (): void {
	$provider = new AnalyticsServiceProvider( $this->app );
	$features = $provider->aiFeatures();

	expect( $features )->toHaveKeys( [
		'analytics.insight_summary',
		'analytics.explain_anomaly',
		'analytics.segment_insight',
		'analytics.digest_email',
	] );

	expect( $features['analytics.insight_summary']['agent'] )->toBe( InsightSummaryAgent::class );
	expect( $features['analytics.explain_anomaly']['agent'] )->toBe( AnomalyExplanationAgent::class );
	expect( $features['analytics.segment_insight']['agent'] )->toBe( SegmentInsightAgent::class );
	expect( $features['analytics.digest_email']['agent'] )->toBe( DigestEmailAgent::class );
} );

it( 'associates every feature with the analytics package', function (): void {
	$provider = new AnalyticsServiceProvider( $this->app );

	foreach ( $provider->aiFeatures() as $key => $config ) {
		expect( $config['package'] )->toBe( 'artisanpack-ui/analytics' );
		expect( $config )->toHaveKey( 'label' );
		expect( $config )->toHaveKey( 'description' );
	}
} );
