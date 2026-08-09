<?php

declare( strict_types=1 );

/**
 * Behavioural coverage for the tracker's History API support.
 *
 * The tracker is a browser IIFE with no JS test runner in this package, and
 * asserting on its source text cannot tell you whether the de-duplication or
 * the engagement reset actually work. `tests/js/tracker-spa-harness.mjs` runs
 * the real file inside a Node VM context with a stub DOM and reports the
 * beacons it emits; these tests assert against that report.
 *
 * Skipped when Node is unavailable so the suite still runs on a PHP-only box.
 */

/**
 * Run the SPA harness and return its decoded report.
 *
 * @param array<string, mixed> $configOverrides Tracker config overrides.
 *
 * @return array{initialPageViews: list<string>, steps: list<array{label: string, pageViewPaths: list<string>, engagementUpdates: list<array{path: string|null, scroll_depth: int|null}>}>}
 */
function runTrackerHarness( array $configOverrides = [] ): array
{
	$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );

	if ( '' === $node ) {
		test()->markTestSkipped( 'Node is not available; skipping tracker SPA harness.' );
	}

	$harness = realpath( __DIR__ . '/../js/tracker-spa-harness.mjs' );
	$tracker = realpath( __DIR__ . '/../../resources/js/tracker.js' );

	expect( $harness )->not->toBeFalse( 'tracker-spa-harness.mjs is missing' );
	expect( $tracker )->not->toBeFalse( 'resources/js/tracker.js is missing' );

	$command = sprintf(
		'%s %s %s %s 2>&1',
		escapeshellarg( $node ),
		escapeshellarg( (string) $harness ),
		escapeshellarg( (string) $tracker ),
		[] === $configOverrides ? '' : escapeshellarg( (string) json_encode( $configOverrides ) ),
	);

	$output = (string) shell_exec( $command );
	$report = json_decode( $output, true );

	expect( $report )->toBeArray( "Harness did not return JSON. Output:\n" . $output );

	return $report;
}

/**
 * Collect the page view paths recorded for a labelled harness step.
 *
 * @param array<string, mixed> $report The harness report.
 *
 * @return list<string>
 */
function stepPageViews( array $report, string $label ): array
{
	foreach ( $report['steps'] as $step ) {
		if ( $step['label'] === $label ) {
			return $step['pageViewPaths'];
		}
	}

	throw new RuntimeException( "No harness step labelled '{$label}'" );
}

/**
 * Collect the engagement updates recorded for a labelled harness step.
 *
 * @param array<string, mixed> $report The harness report.
 *
 * @return list<array{path: string|null, scroll_depth: int|null}>
 */
function stepEngagement( array $report, string $label ): array
{
	foreach ( $report['steps'] as $step ) {
		if ( $step['label'] === $label ) {
			return $step['engagementUpdates'];
		}
	}

	throw new RuntimeException( "No harness step labelled '{$label}'" );
}

test( 'a pushState navigation records a page view for the new path', function (): void {
	$report = runTrackerHarness();

	expect( $report['initialPageViews'] )->toBe( [ '/' ] )
		->and( stepPageViews( $report, 'pushState -> /docs/one' ) )->toBe( [ '/docs/one' ] )
		->and( stepPageViews( $report, 'pushState -> /docs/two' ) )->toBe( [ '/docs/two' ] );
} );

test( 'a popstate navigation records a page view', function (): void {
	$report = runTrackerHarness();

	expect( stepPageViews( $report, 'popstate back -> /docs/one' ) )->toBe( [ '/docs/one' ] );
} );

test( 'a replaceState that does not change the url records nothing', function (): void {
	// Routers call replaceState routinely to sync state without navigating.
	// Treating those as page views would inflate every SPA's numbers.
	$report = runTrackerHarness();

	expect( stepPageViews( $report, 'replaceState same url (router state sync)' ) )->toBe( [] );
} );

test( 'a hash-only change is left to trackHashChanges', function (): void {
	// Otherwise a router that pushes a hash would double-count when both
	// options are enabled.
	$report = runTrackerHarness();

	expect( stepPageViews( $report, 'hash-only change on same path' ) )->toBe( [] );
} );

test( 'a query string change counts as a new page view', function (): void {
	$report = runTrackerHarness();

	expect( stepPageViews( $report, 'query string change' ) )->toBe( [ '/docs/one' ] );
} );

test( 'engagement is flushed against the page it was measured on, not the incoming one', function (): void {
	// location has already changed by the time the navigation handler runs, so
	// the flush has to use the path Engagement recorded at reset. Getting this
	// wrong would attribute the outgoing page's scroll depth and time on page
	// to the incoming path — and the server matches the row to update by path,
	// so the update would land on the wrong page view or none at all.
	$report = runTrackerHarness();

	expect( stepEngagement( $report, 'pushState -> /docs/one' ) )
		->toBe( [ [ 'path' => '/', 'scroll_depth' => 0 ] ] );

	expect( stepEngagement( $report, 'pushState -> /docs/two' ) )
		->toBe( [ [ 'path' => '/docs/one', 'scroll_depth' => 0 ] ] );

	expect( stepEngagement( $report, 'popstate back -> /docs/one' ) )
		->toBe( [ [ 'path' => '/docs/two', 'scroll_depth' => 0 ] ] );
} );

test( 'setting trackHistoryChanges to false restores the previous behaviour', function (): void {
	// The escape hatch for apps that already bridge their router's navigation
	// events by hand; without it they would double count.
	$report = runTrackerHarness( [ 'trackHistoryChanges' => false ] );

	expect( $report['initialPageViews'] )->toBe( [ '/' ] );

	foreach ( $report['steps'] as $step ) {
		expect( $step['pageViewPaths'] )
			->toBe( [], "Step '{$step['label']}' should record nothing when trackHistoryChanges is off" );
	}
} );

test( 'a page view is on the wire before the engagement update that refers to it', function (): void {
	// Page views sit in the batch queue; engagement updates go out immediately.
	// Run at the production batch interval rather than the harness's short one,
	// which is fast enough to hide the race entirely.
	$report = runTrackerHarness( [ 'batchInterval' => 5000 ] );

	$seenPages = [];

	foreach ( $report['timeline'] as $beacon ) {
		if ( 'pageview' === $beacon['kind'] ) {
			foreach ( $beacon['paths'] as $path ) {
				$seenPages[ $path ] = true;
			}

			continue;
		}

		if ( 'update' !== $beacon['kind'] ) {
			continue;
		}

		foreach ( $beacon['paths'] as $path ) {
			// updatePageView() matches the row by path, so an update that
			// arrives first lands on nothing at all.
			expect( $seenPages )->toHaveKey(
				$path,
				"An engagement update for '{$path}' was sent before that page's own page view",
			);
		}
	}
} );
