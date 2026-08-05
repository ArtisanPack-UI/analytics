<?php

declare( strict_types=1 );

/**
 * Behavioural coverage for the tracker's anonymous (pre-consent) mode.
 *
 * The claim this feature makes is a negative one — no identifiers, no cookies,
 * no storage — and a negative is precisely what source-text assertions cannot
 * verify. `tests/js/tracker-anonymous-harness.mjs` runs the real tracker in a
 * Node VM context whose stub DOM records every cookie assignment and every
 * localStorage write, so the tests below can assert that the lists are empty.
 *
 * Skipped when Node is unavailable so the suite still runs on a PHP-only box.
 */

/**
 * Run the anonymous harness and return its decoded report.
 *
 * @param array<string, mixed> $configOverrides Tracker config overrides. The
 *                                              `__doNotTrack` and
 *                                              `__globalPrivacyControl` keys
 *                                              set navigator signals rather
 *                                              than tracker config.
 *
 * @return array<string, mixed>
 */
function runAnonymousHarness( array $configOverrides = [] ): array
{
	$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

	if ( '' === $node ) {
		test()->markTestSkipped( 'Node is not available; skipping anonymous tracker harness.' );
	}

	$harness = realpath( __DIR__ . '/../js/tracker-anonymous-harness.mjs' );
	$tracker = realpath( __DIR__ . '/../../resources/js/tracker.js' );

	$output = (string) shell_exec( sprintf(
		'%s %s %s %s 2>&1',
		escapeshellarg( $node ),
		escapeshellarg( (string) $harness ),
		escapeshellarg( (string) $tracker ),
		escapeshellarg( (string) json_encode( $configOverrides ) ),
	) );

	$report = json_decode( $output, true );

	expect( $report )->toBeArray( "Harness did not return JSON. Output:\n" . $output );

	return $report;
}

/**
 * Find a labelled step in the harness report.
 *
 * @param array<string, mixed> $report The harness report.
 *
 * @return array<string, mixed>
 */
function anonymousStep( array $report, string $label ): array
{
	foreach ( $report['steps'] as $step ) {
		if ( $step['label'] === $label ) {
			return $step;
		}
	}

	throw new RuntimeException( "No harness step labelled '{$label}'" );
}

test( 'a pre-consent visit sends an identifier-free page view', function (): void {
	$report = runAnonymousHarness( [ 'anonymousMode' => true ] );

	$beacons = $report['afterLoad']['beacons'];

	expect( $beacons )->toHaveCount( 1 )
		->and( $beacons[0]['url'] )->toBe( '/api/analytics/anonymous/pageview' )
		->and( $beacons[0]['keys'] )->toBe( [ 'path', 'referrer_host', 'title' ] );
} );

test( 'anonymous mode writes no cookie and no storage key', function (): void {
	// This is the whole promise. The tracker gets there by never calling
	// Visitor.init(), Session.init() or Fingerprint.init() — the absence of
	// those calls is the guarantee, and this asserts the absence holds.
	$report = runAnonymousHarness( [ 'anonymousMode' => true ] );

	expect( $report['afterLoad']['cookieWrites'] )->toBe( [] )
		->and( $report['afterLoad']['storageWrites'] )->toBe( [] );

	$navigation = anonymousStep( $report, 'spa navigation while anonymous' );

	expect( $navigation['cookieWrites'] )->toBe( [] )
		->and( $navigation['storageWrites'] )->toBe( [] );
} );

test( 'SPA navigations are recorded anonymously too', function (): void {
	$report = runAnonymousHarness( [ 'anonymousMode' => true ] );

	$navigation = anonymousStep( $report, 'spa navigation while anonymous' );

	expect( $navigation['beacons'] )->toHaveCount( 1 )
		->and( $navigation['beacons'][0]['url'] )->toBe( '/api/analytics/anonymous/pageview' )
		->and( $navigation['beacons'][0]['data']['path'] )->toBe( '/docs/one' );
} );

test( 'granting consent mid-visit upgrades to identified tracking', function (): void {
	$report = runAnonymousHarness( [ 'anonymousMode' => true ] );

	$granted = anonymousStep( $report, 'consent granted mid-visit' );

	// The visitor and session are established only now, on consent.
	expect( implode( ' ', $granted['cookieWrites'] ) )->toContain( '_ap_vid' )
		->and( implode( ' ', $granted['cookieWrites'] ) )->toContain( '_ap_sid' );

	$afterConsent = anonymousStep( $report, 'spa navigation after consent' );
	$pageViews    = array_values( array_filter(
		$afterConsent['beacons'],
		fn ( array $b ): bool => str_ends_with( $b['url'], '/pageview' ),
	) );

	expect( $pageViews )->toHaveCount( 1, 'Post-consent navigation must not double count' )
		->and( $pageViews[0]['keys'] )->toContain( 'visitor_id' )
		->and( $pageViews[0]['keys'] )->toContain( 'session_id' );
} );

test( 'no anonymous page view is sent once the visit is identified', function (): void {
	$report = runAnonymousHarness( [ 'anonymousMode' => true ] );

	$afterConsent = anonymousStep( $report, 'spa navigation after consent' );

	foreach ( $afterConsent['beacons'] as $beacon ) {
		expect( $beacon['url'] )->not->toContain( 'anonymous' );
	}
} );

test( 'nothing is sent when anonymous mode is off and consent is absent', function (): void {
	$report = runAnonymousHarness( [ 'anonymousMode' => false ] );

	expect( $report['afterLoad']['beacons'] )->toBe( [] )
		->and( $report['afterLoad']['cookieWrites'] )->toBe( [] )
		->and( $report['afterLoad']['storageWrites'] )->toBe( [] );
} );

test( 'an explicit opt-out signal suppresses anonymous mode entirely', function (): void {
	// Anonymous mode argues about identifiability. Do Not Track and Global
	// Privacy Control are an answer, not an absence of one.
	foreach ( [ '__globalPrivacyControl' => true, '__doNotTrack' => '1' ] as $signal => $value ) {
		$report = runAnonymousHarness( [ 'anonymousMode' => true, $signal => $value ] );

		expect( $report['afterLoad']['beacons'] )
			->toBe( [], "Anonymous mode must send nothing when {$signal} is set" )
			->and( $report['afterLoad']['cookieWrites'] )->toBe( [] );
	}
} );
