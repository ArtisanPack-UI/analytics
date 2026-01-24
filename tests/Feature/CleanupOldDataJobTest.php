<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Jobs\CleanupOldData;
use ArtisanPackUI\Analytics\Models\Aggregate;
use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.retention.period', 30 );
	config()->set( 'artisanpack.analytics.retention.aggregation_retention', 365 );
	config()->set( 'artisanpack.analytics.local.queue_name', 'analytics' );
} );

test( 'cleanup job deletes old page views', function (): void {
	$visitor = createVisitor();
	$session = createSession( $visitor );

	// Old page view (should be deleted)
	PageView::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'path'       => '/old-page',
		'created_at' => now()->subDays( 60 ),
	] );

	// Recent page view (should be kept)
	PageView::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'path'       => '/recent-page',
		'created_at' => now()->subDays( 5 ),
	] );

	( new CleanupOldData )->handle();

	$this->assertDatabaseMissing( 'analytics_page_views', [
		'path' => '/old-page',
	] );

	$this->assertDatabaseHas( 'analytics_page_views', [
		'path' => '/recent-page',
	] );
} );

test( 'cleanup job deletes old events', function (): void {
	$visitor = createVisitor();
	$session = createSession( $visitor );

	// Old event (should be deleted)
	Event::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'name'       => 'old_event',
		'created_at' => now()->subDays( 60 ),
	] );

	// Recent event (should be kept)
	Event::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'name'       => 'recent_event',
		'created_at' => now()->subDays( 5 ),
	] );

	( new CleanupOldData )->handle();

	$this->assertDatabaseMissing( 'analytics_events', [
		'name' => 'old_event',
	] );

	$this->assertDatabaseHas( 'analytics_events', [
		'name' => 'recent_event',
	] );
} );

test( 'cleanup job deletes old sessions', function (): void {
	$visitor = createVisitor();

	// Old session (should be deleted)
	Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now()->subDays( 60 ),
		'last_activity_at' => now()->subDays( 60 ),
		'entry_page'       => '/old',
	] );

	// Recent session (should be kept)
	Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now()->subDays( 5 ),
		'last_activity_at' => now()->subDays( 5 ),
		'entry_page'       => '/recent',
	] );

	( new CleanupOldData )->handle();

	$this->assertDatabaseMissing( 'analytics_sessions', [
		'entry_page' => '/old',
	] );

	$this->assertDatabaseHas( 'analytics_sessions', [
		'entry_page' => '/recent',
	] );
} );

test( 'cleanup job deletes orphaned visitors', function (): void {
	// Old visitor with no recent sessions (should be deleted)
	$oldVisitor = Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => 'old-visitor-' . Str::random( 10 ),
		'first_seen_at' => now()->subDays( 60 ),
		'last_seen_at'  => now()->subDays( 60 ),
	] );

	// Recent visitor (should be kept)
	$recentVisitor = Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => 'recent-visitor-' . Str::random( 10 ),
		'first_seen_at' => now()->subDays( 5 ),
		'last_seen_at'  => now()->subDays( 5 ),
	] );

	( new CleanupOldData )->handle();

	$this->assertDatabaseMissing( 'analytics_visitors', [
		'id' => $oldVisitor->id,
	] );

	$this->assertDatabaseHas( 'analytics_visitors', [
		'id' => $recentVisitor->id,
	] );
} );

test( 'cleanup job deletes expired consents', function (): void {
	$visitor = createVisitor();

	// Expired consent (should be deleted)
	Consent::create( [
		'visitor_id' => $visitor->id,
		'category'   => 'analytics',
		'granted'    => true,
		'granted_at' => now()->subYear(),
		'expires_at' => now()->subDays( 1 ),
	] );

	// Valid consent (should be kept)
	Consent::create( [
		'visitor_id' => $visitor->id,
		'category'   => 'marketing',
		'granted'    => true,
		'granted_at' => now(),
		'expires_at' => now()->addYear(),
	] );

	( new CleanupOldData )->handle();

	$this->assertDatabaseMissing( 'analytics_consents', [
		'category' => 'analytics',
	] );

	$this->assertDatabaseHas( 'analytics_consents', [
		'category' => 'marketing',
	] );
} );

test( 'cleanup job skips when retention is disabled', function (): void {
	config()->set( 'artisanpack.analytics.retention.period', null );

	$visitor = createVisitor();
	$session = createSession( $visitor );

	PageView::create( [
		'session_id' => $session->id,
		'visitor_id' => $visitor->id,
		'path'       => '/old-page',
		'created_at' => now()->subDays( 60 ),
	] );

	( new CleanupOldData )->handle();

	// Should not be deleted when retention is disabled
	$this->assertDatabaseHas( 'analytics_page_views', [
		'path' => '/old-page',
	] );
} );

test( 'cleanup job deletes old conversions', function (): void {
	$visitor = createVisitor();
	$session = createSession( $visitor );

	$goal = Goal::create( [
		'name'       => 'Test Goal',
		'type'       => Goal::TYPE_EVENT,
		'conditions' => ['event_name' => 'test'],
		'is_active'  => true,
	] );

	// Old conversion (should be deleted)
	Conversion::create( [
		'session_id' => $session->id,
		'goal_id'    => $goal->id,
		'created_at' => now()->subDays( 60 ),
	] );

	// Recent conversion (should be kept)
	Conversion::create( [
		'session_id' => $session->id,
		'goal_id'    => $goal->id,
		'created_at' => now()->subDays( 5 ),
	] );

	( new CleanupOldData )->handle();

	expect( Conversion::count() )->toBe( 1 );
} );

test( 'cleanup job deletes old aggregates', function (): void {
	// Old aggregate (should be deleted)
	Aggregate::create( [
		'metric'    => 'visitors',
		'dimension' => 'total',
		'date'      => now()->subDays( 400 ),
		'period'    => 'daily',
		'value'     => 100,
	] );

	// Recent aggregate (should be kept)
	Aggregate::create( [
		'metric'    => 'visitors',
		'dimension' => 'total',
		'date'      => now()->subDays( 30 ),
		'period'    => 'daily',
		'value'     => 150,
	] );

	( new CleanupOldData )->handle();

	expect( Aggregate::count() )->toBe( 1 );
} );

test( 'cleanup job respects site scope', function (): void {
	$visitor1 = createVisitor( 1 );
	$session1 = createSession( $visitor1, 1 );

	$visitor2 = createVisitor( 2 );
	$session2 = createSession( $visitor2, 2 );

	PageView::create( [
		'session_id' => $session1->id,
		'visitor_id' => $visitor1->id,
		'site_id'    => 1,
		'path'       => '/site1-page',
		'created_at' => now()->subDays( 60 ),
	] );

	PageView::create( [
		'session_id' => $session2->id,
		'visitor_id' => $visitor2->id,
		'site_id'    => 2,
		'path'       => '/site2-page',
		'created_at' => now()->subDays( 60 ),
	] );

	// Only clean up site 1
	( new CleanupOldData( siteId: 1 ) )->handle();

	$this->assertDatabaseMissing( 'analytics_page_views', [
		'path' => '/site1-page',
	] );

	$this->assertDatabaseHas( 'analytics_page_views', [
		'path' => '/site2-page',
	] );
} );

// Helper functions
function createVisitor( ?int $siteId = null ): Visitor
{
	return Visitor::create( [
		'visitor_id'    => Str::uuid()->toString(),
		'fingerprint'   => 'test-visitor-' . Str::random( 10 ),
		'first_seen_at' => now(),
		'last_seen_at'  => now(),
		'site_id'       => $siteId,
	] );
}

function createSession( Visitor $visitor, ?int $siteId = null ): Session
{
	return Session::create( [
		'session_id'       => Str::uuid()->toString(),
		'visitor_id'       => $visitor->id,
		'started_at'       => now(),
		'last_activity_at' => now(),
		'entry_page'       => '/',
		'site_id'          => $siteId,
	] );
}
