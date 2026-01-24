<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\DataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses( RefreshDatabase::class );

test( 'data export service exports visitor data', function (): void {
	$service = new DataExportService;

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$visitor = Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => $fingerprint,
		'first_seen_at' => now()->subDays( 7 ),
		'last_seen_at'  => now(),
		'country'       => 'US',
		'region'        => 'California',
		'city'          => 'San Francisco',
		'device_type'   => 'desktop',
		'browser'       => 'Chrome',
		'os'            => 'Windows',
		'language'      => 'en-US',
		'timezone'      => 'America/Los_Angeles',
	] );

	$session = Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now()->subHours( 2 ),
		'ended_at'         => now()->subHours( 1 ),
		'last_activity_at' => now()->subHours( 1 ),
		'entry_page'       => '/',
		'exit_page'        => '/about',
		'page_count'       => 3,
		'duration'         => 3600,
		'referrer'         => 'https://google.com',
	] );

	PageView::create( [
		'session_id'   => $session->id,
		'visitor_id'   => $visitor->id,
		'path'         => '/',
		'title'        => 'Home',
		'time_on_page' => 120,
	] );

	Event::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'name'       => 'button_click',
		'category'   => 'engagement',
		'properties' => ['button_id' => 'cta'],
	] );

	Consent::create( [
		'visitor_id' => $visitor->id,
		'category'   => 'analytics',
		'granted'    => true,
		'granted_at' => now()->subDays( 7 ),
	] );

	$data = $service->exportVisitorData( $fingerprint );

	expect( $data )->toHaveKey( 'visitor' );
	expect( $data )->toHaveKey( 'sessions' );
	expect( $data )->toHaveKey( 'consents' );
	expect( $data )->toHaveKey( 'exported_at' );

	expect( $data['visitor']['id'] )->toBe( $fingerprint );
	expect( $data['visitor']['country'] )->toBe( 'US' );
	expect( $data['visitor']['browser'] )->toBe( 'Chrome' );

	expect( $data['sessions'] )->toHaveCount( 1 );
	expect( $data['sessions'][0]['page_views'] )->toHaveCount( 1 );
	expect( $data['sessions'][0]['events'] )->toHaveCount( 1 );

	expect( $data['consents'] )->toHaveCount( 1 );
	expect( $data['consents'][0]['category'] )->toBe( 'analytics' );
} );

test( 'data export service returns error for non-existent visitor', function (): void {
	$service = new DataExportService;

	$data = $service->exportVisitorData( 'nonexistent-fingerprint' );

	expect( $data )->toHaveKey( 'error' );
	expect( $data )->toHaveKey( 'exported_at' );
} );

test( 'data export service exports as CSV', function (): void {
	$service = new DataExportService;

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

	$csv = $service->exportAsCsv( $fingerprint );

	expect( $csv )->toContain( 'Analytics Data Export' );
	expect( $csv )->toContain( 'Visitor Information' );
	expect( $csv )->toContain( 'Sessions' );
} );

test( 'data export service exports as JSON', function (): void {
	$service = new DataExportService;

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$visitor = Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => $fingerprint,
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
	] );

	Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
	] );

	$json = $service->exportAsJson( $fingerprint );

	$data = json_decode( $json, true );

	expect( $data )->toBeArray();
	expect( $data )->toHaveKey( 'visitor' );
	expect( $data )->toHaveKey( 'sessions' );
} );

test( 'data export service handles CSV special characters', function (): void {
	$service = new DataExportService;

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
		'entry_page'       => '/test',
		'referrer'         => 'https://example.com?param=value,with,commas',
	] );

	PageView::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'path'       => '/test',
		'title'      => 'Test, "Page" with special chars',
	] );

	$csv = $service->exportAsCsv( $fingerprint );

	// CSV should properly escape special characters
	expect( $csv )->toContain( '"Test, ""Page"" with special chars"' );
} );

test( 'data export service CSV returns error message for non-existent visitor', function (): void {
	$service = new DataExportService;

	$csv = $service->exportAsCsv( 'nonexistent-fingerprint' );

	expect( $csv )->toContain( 'Error' );
	expect( $csv )->toContain( 'Visitor not found' );
} );

test( 'data export service includes UTM data', function (): void {
	$service = new DataExportService;

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$visitor = Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => $fingerprint,
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
	] );

	Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
		'utm_source'       => 'google',
		'utm_medium'       => 'cpc',
		'utm_campaign'     => 'spring_sale',
	] );

	$data = $service->exportVisitorData( $fingerprint );

	expect( $data['sessions'][0]['utm']['source'] )->toBe( 'google' );
	expect( $data['sessions'][0]['utm']['medium'] )->toBe( 'cpc' );
	expect( $data['sessions'][0]['utm']['campaign'] )->toBe( 'spring_sale' );
} );
