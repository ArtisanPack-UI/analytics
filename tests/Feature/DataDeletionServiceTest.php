<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\DataDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses( RefreshDatabase::class );

test( 'data deletion service deletes all visitor data', function (): void {
	$service = new DataDeletionService;

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$visitor = Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => $fingerprint,
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
	] );

	$session = Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
	] );

	PageView::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'path'       => '/',
	] );

	Event::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'name'       => 'test_event',
	] );

	$goal = Goal::create( [
		'name'       => 'Test Goal',
		'type'       => Goal::TYPE_EVENT,
		'conditions' => ['event_name' => 'test_event'],
		'is_active'  => true,
	] );

	Conversion::create( [
		'session_id' => $session->id,
		'goal_id'    => $goal->id,
	] );

	Consent::create( [
		'visitor_id' => $visitor->id,
		'category'   => 'analytics',
		'granted'    => true,
		'granted_at' => now(),
	] );

	$result = $service->deleteVisitorData( $fingerprint );

	expect( $result['success'] )->toBeTrue();
	expect( $result['deleted'] )->toBeArray();
	expect( $result['deleted']['page_views'] )->toBeGreaterThanOrEqual( 1 );
	expect( $result['deleted']['events'] )->toBeGreaterThanOrEqual( 1 );
	expect( $result['deleted']['conversions'] )->toBeGreaterThanOrEqual( 1 );
	expect( $result['deleted']['sessions'] )->toBeGreaterThanOrEqual( 1 );
	expect( $result['deleted']['consents'] )->toBeGreaterThanOrEqual( 1 );

	$this->assertDatabaseMissing( 'analytics_visitors', [
		'fingerprint' => $fingerprint,
	] );

	$this->assertDatabaseMissing( 'analytics_sessions', [
		'visitor_id' => $visitor->id,
	] );
} );

test( 'data deletion service returns error for non-existent visitor', function (): void {
	$service = new DataDeletionService;

	$result = $service->deleteVisitorData( 'nonexistent-fingerprint' );

	expect( $result['success'] )->toBeFalse();
	expect( $result )->toHaveKey( 'error' );
} );

test( 'data deletion service anonymizes visitor data', function (): void {
	$service = new DataDeletionService;

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$visitor = Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => $fingerprint,
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
		'country'       => 'US',
		'region'        => 'California',
		'city'          => 'San Francisco',
		'browser'       => 'Chrome',
		'os'            => 'Windows',
	] );

	$session = Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
		'referrer'         => 'https://google.com',
		'referrer_domain'  => 'google.com',
		'utm_source'       => 'google',
	] );

	Consent::create( [
		'visitor_id' => $visitor->id,
		'category'   => 'analytics',
		'granted'    => true,
		'granted_at' => now(),
	] );

	$result = $service->anonymizeVisitorData( $fingerprint );

	expect( $result['success'] )->toBeTrue();
	expect( $result['anonymized'] )->toBeArray();

	// Refresh from database
	$visitor->refresh();
	$session->refresh();

	// Visitor should be anonymized
	expect( $visitor->fingerprint )->toStartWith( 'anonymous_' );
	expect( $visitor->country )->toBeNull();
	expect( $visitor->region )->toBeNull();
	expect( $visitor->city )->toBeNull();
	expect( $visitor->browser )->toBeNull();
	expect( $visitor->os )->toBeNull();
	expect( $visitor->device_type )->toBe( 'other' );

	// Session should be anonymized
	expect( $session->referrer )->toBeNull();
	expect( $session->referrer_domain )->toBeNull();
	expect( $session->utm_source )->toBeNull();

	// Consents should be deleted
	$this->assertDatabaseMissing( 'analytics_consents', [
		'visitor_id' => $visitor->id,
	] );
} );

test( 'data deletion service returns error for non-existent visitor anonymization', function (): void {
	$service = new DataDeletionService;

	$result = $service->anonymizeVisitorData( 'nonexistent-fingerprint' );

	expect( $result['success'] )->toBeFalse();
	expect( $result )->toHaveKey( 'error' );
} );

test( 'data deletion service checks if visitor has data', function (): void {
	$service = new DataDeletionService;

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	expect( $service->hasVisitorData( $fingerprint ) )->toBeFalse();

	Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => $fingerprint,
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
	] );

	expect( $service->hasVisitorData( $fingerprint ) )->toBeTrue();
} );

test( 'data deletion service returns data summary', function (): void {
	$service = new DataDeletionService;

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	// Non-existent visitor
	$summary = $service->getDataSummary( $fingerprint );
	expect( $summary['exists'] )->toBeFalse();

	// Create visitor with data
	$visitor = Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => $fingerprint,
		'first_seen_at' => now()->subDays( 7 ),
		'last_seen_at'  => now(),
	] );

	$session = Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
	] );

	PageView::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'path'       => '/',
	] );

	PageView::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'path'       => '/about',
	] );

	Event::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'name'       => 'test_event',
	] );

	$summary = $service->getDataSummary( $fingerprint );

	expect( $summary['exists'] )->toBeTrue();
	expect( $summary['session_count'] )->toBe( 1 );
	expect( $summary['page_view_count'] )->toBe( 2 );
	expect( $summary['event_count'] )->toBe( 1 );
} );

test( 'data deletion service deletes multiple visitors', function (): void {
	$service = new DataDeletionService;

	$fingerprints = [];

	for ( $i = 0; $i < 3; $i++ ) {
		$fingerprint    = 'test-fingerprint-' . Str::random( 10 );
		$fingerprints[] = $fingerprint;

		Visitor::create( [
			'visitor_id'    => Str::uuid()->toString(),
			'fingerprint'   => $fingerprint,
			'first_seen_at' => now(),
			'last_seen_at'  => now(),
		] );
	}

	$results = $service->deleteMultipleVisitors( $fingerprints );

	expect( $results['total'] )->toBe( 3 );
	expect( $results['successful'] )->toBe( 3 );
	expect( $results['failed'] )->toBe( 0 );
} );

test( 'data deletion service handles mixed success and failure in bulk delete', function (): void {
	$service = new DataDeletionService;

	$existingFingerprint = 'existing-' . Str::random( 10 );

	Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => $existingFingerprint,
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
	] );

	$fingerprints = [
		$existingFingerprint,
		'nonexistent-1',
		'nonexistent-2',
	];

	$results = $service->deleteMultipleVisitors( $fingerprints );

	expect( $results['total'] )->toBe( 3 );
	expect( $results['successful'] )->toBe( 1 );
	expect( $results['failed'] )->toBe( 2 );
} );
