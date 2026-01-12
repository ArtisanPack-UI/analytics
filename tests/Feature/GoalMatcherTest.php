<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Events\GoalConverted;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\GoalMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Str;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'artisanpack.analytics.goals.allow_multiple_per_session', false );
} );

test( 'goal matcher matches event by name', function (): void {
    $goal = Goal::create( [
        'name'       => 'Button Click Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'button_click',
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'button_click',
        'category'   => 'engagement',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchEvent( $event, null, null );

    expect( $conversions )->toHaveCount( 1 );
    expect( $conversions->first()->goal_id )->toBe( $goal->id );
} );

test( 'goal matcher matches event by category', function (): void {
    $goal = Goal::create( [
        'name'       => 'Ecommerce Event Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_category' => 'ecommerce',
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'purchase',
        'category'   => 'ecommerce',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchEvent( $event, null, null );

    expect( $conversions )->toHaveCount( 1 );
} );

test( 'goal matcher matches event with property conditions', function (): void {
    $goal = Goal::create( [
        'name'       => 'High Value Purchase',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name'       => 'purchase',
            'property_matches' => [
                'amount' => ['gt' => 100],
            ],
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'purchase',
        'properties' => ['amount' => 150],
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchEvent( $event, null, null );

    expect( $conversions )->toHaveCount( 1 );
} );

test( 'goal matcher does not match event with property below threshold', function (): void {
    $goal = Goal::create( [
        'name'       => 'High Value Purchase',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name'       => 'purchase',
            'property_matches' => [
                'amount' => ['gt' => 100],
            ],
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'purchase',
        'properties' => ['amount' => 50],
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchEvent( $event, null, null );

    expect( $conversions )->toHaveCount( 0 );
} );

test( 'goal matcher matches pageview by exact path', function (): void {
    $goal = Goal::create( [
        'name'       => 'Thank You Page',
        'type'       => Goal::TYPE_PAGEVIEW,
        'conditions' => [
            'path_exact' => '/thank-you',
        ],
        'is_active' => true,
    ] );

    $pageView = PageView::create( [
        'path'       => '/thank-you',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchPageView( $pageView, null, null );

    expect( $conversions )->toHaveCount( 1 );
} );

test( 'goal matcher matches pageview by pattern', function (): void {
    $goal = Goal::create( [
        'name'       => 'Product Page',
        'type'       => Goal::TYPE_PAGEVIEW,
        'conditions' => [
            'path_pattern' => '/products/*',
        ],
        'is_active' => true,
    ] );

    $pageView = PageView::create( [
        'path'       => '/products/awesome-widget',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchPageView( $pageView, null, null );

    expect( $conversions )->toHaveCount( 1 );
} );

test( 'goal matcher matches pageview by regex', function (): void {
    $goal = Goal::create( [
        'name'       => 'Order Confirmation',
        'type'       => Goal::TYPE_PAGEVIEW,
        'conditions' => [
            'path_regex' => '/^\/order\/[0-9]+\/confirmation$/',
        ],
        'is_active' => true,
    ] );

    $pageView = PageView::create( [
        'path'       => '/order/12345/confirmation',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchPageView( $pageView, null, null );

    expect( $conversions )->toHaveCount( 1 );
} );

test( 'goal matcher matches session duration goal', function (): void {
    $goal = Goal::create( [
        'name'       => 'Long Session',
        'type'       => Goal::TYPE_DURATION,
        'conditions' => [
            'min_seconds' => 300,
        ],
        'is_active' => true,
    ] );

    $visitorId = Str::uuid()->toString();
    $sessionId = Str::uuid()->toString();

    $visitor = Visitor::create( [
        'id'            => $visitorId,
        'visitor_id'    => $visitorId,
        'fingerprint'   => 'test-fingerprint-' . Str::random( 10 ),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ] );

    $session = Session::create( [
        'id'               => $sessionId,
        'session_id'       => $sessionId,
        'visitor_id'       => $visitor->id,
        'started_at'       => now()->subMinutes( 10 ),
        'last_activity_at' => now(),
        'duration'         => 600,
        'entry_page'       => '/',
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchSession( $session );

    expect( $conversions )->toHaveCount( 1 );
} );

test( 'goal matcher matches pages per session goal', function (): void {
    $goal = Goal::create( [
        'name'       => 'Engaged Visitor',
        'type'       => Goal::TYPE_PAGES_PER_SESSION,
        'conditions' => [
            'min_pages' => 5,
        ],
        'is_active' => true,
    ] );

    $visitorId = Str::uuid()->toString();
    $sessionId = Str::uuid()->toString();

    $visitor = Visitor::create( [
        'id'            => $visitorId,
        'visitor_id'    => $visitorId,
        'fingerprint'   => 'test-fingerprint-' . Str::random( 10 ),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ] );

    $session = Session::create( [
        'id'               => $sessionId,
        'session_id'       => $sessionId,
        'visitor_id'       => $visitor->id,
        'started_at'       => now(),
        'last_activity_at' => now(),
        'duration'         => 300,
        'page_count'       => 7,
        'entry_page'       => '/',
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchSession( $session );

    expect( $conversions )->toHaveCount( 1 );
} );

test( 'goal matcher prevents duplicate conversions per session', function (): void {
    $goal = Goal::create( [
        'name'       => 'Button Click Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'button_click',
        ],
        'is_active' => true,
    ] );

    $visitorId = Str::uuid()->toString();
    $sessionId = Str::uuid()->toString();

    $visitor = Visitor::create( [
        'id'            => $visitorId,
        'visitor_id'    => $visitorId,
        'fingerprint'   => 'test-fingerprint-' . Str::random( 10 ),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ] );

    $session = Session::create( [
        'id'               => $sessionId,
        'session_id'       => $sessionId,
        'visitor_id'       => $visitor->id,
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
    ] );

    $event1 = Event::create( [
        'name'       => 'button_click',
        'session_id' => $session->id,
        'visitor_id' => $visitor->id,
    ] );

    $event2 = Event::create( [
        'name'       => 'button_click',
        'session_id' => $session->id,
        'visitor_id' => $visitor->id,
    ] );

    $matcher      = new GoalMatcher;
    $conversions1 = $matcher->matchEvent( $event1, $session, $visitor );
    $conversions2 = $matcher->matchEvent( $event2, $session, $visitor );

    expect( $conversions1 )->toHaveCount( 1 );
    expect( $conversions2 )->toHaveCount( 0 );
} );

test( 'goal matcher allows multiple conversions when configured', function (): void {
    config()->set( 'artisanpack.analytics.goals.allow_multiple_per_session', true );

    $goal = Goal::create( [
        'name'       => 'Button Click Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'button_click',
        ],
        'is_active' => true,
    ] );

    $visitorId = Str::uuid()->toString();
    $sessionId = Str::uuid()->toString();

    $visitor = Visitor::create( [
        'id'            => $visitorId,
        'visitor_id'    => $visitorId,
        'fingerprint'   => 'test-fingerprint-' . Str::random( 10 ),
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ] );

    $session = Session::create( [
        'id'               => $sessionId,
        'session_id'       => $sessionId,
        'visitor_id'       => $visitor->id,
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
    ] );

    $event1 = Event::create( [
        'name'       => 'button_click',
        'session_id' => $session->id,
        'visitor_id' => $visitor->id,
    ] );

    $event2 = Event::create( [
        'name'       => 'button_click',
        'session_id' => $session->id,
        'visitor_id' => $visitor->id,
    ] );

    $matcher      = new GoalMatcher;
    $conversions1 = $matcher->matchEvent( $event1, $session, $visitor );
    $conversions2 = $matcher->matchEvent( $event2, $session, $visitor );

    expect( $conversions1 )->toHaveCount( 1 );
    expect( $conversions2 )->toHaveCount( 1 );
} );

test( 'goal matcher fires goal converted event', function (): void {
    EventFacade::fake( [GoalConverted::class] );

    $goal = Goal::create( [
        'name'       => 'Button Click Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'button_click',
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'button_click',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher = new GoalMatcher;
    $matcher->matchEvent( $event, null, null );

    EventFacade::assertDispatched( GoalConverted::class );
} );

test( 'goal matcher skips inactive goals', function (): void {
    $goal = Goal::create( [
        'name'       => 'Inactive Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'button_click',
        ],
        'is_active' => false,
    ] );

    $event = Event::create( [
        'name'       => 'button_click',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchEvent( $event, null, null );

    expect( $conversions )->toHaveCount( 0 );
} );

test( 'goal matcher calculates fixed value', function (): void {
    $goal = Goal::create( [
        'name'       => 'Fixed Value Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'purchase',
        ],
        'value_type'  => Goal::VALUE_TYPE_FIXED,
        'fixed_value' => 25.00,
        'is_active'   => true,
    ] );

    $event = Event::create( [
        'name'       => 'purchase',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchEvent( $event, null, null );

    expect( $conversions )->toHaveCount( 1 );
    expect( (float) $conversions->first()->value )->toBe( 25.00 );
} );

test( 'goal matcher extracts dynamic value from event properties', function (): void {
    $goal = Goal::create( [
        'name'       => 'Dynamic Value Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'purchase',
        ],
        'value_type'         => Goal::VALUE_TYPE_DYNAMIC,
        'dynamic_value_path' => 'order_total',
        'is_active'          => true,
    ] );

    $event = Event::create( [
        'name'       => 'purchase',
        'properties' => ['order_total' => 149.99],
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcher     = new GoalMatcher;
    $conversions = $matcher->matchEvent( $event, null, null );

    expect( $conversions )->toHaveCount( 1 );
    expect( (float) $conversions->first()->value )->toBe( 149.99 );
} );

test( 'goal matcher can filter by site', function (): void {
    $goal = Goal::create( [
        'name'       => 'Site Specific Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'button_click',
        ],
        'site_id'   => 1,
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'button_click',
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    $matcherWithSite    = (new GoalMatcher)->forSite( 1 );
    $matcherWithoutSite = (new GoalMatcher)->forSite( 2 );

    $conversions1 = $matcherWithSite->matchEvent( $event, null, null );
    $conversions2 = $matcherWithoutSite->matchEvent( $event, null, null );

    expect( $conversions1 )->toHaveCount( 1 );
    expect( $conversions2 )->toHaveCount( 0 );
} );

test( 'goal model matches operator equals', function (): void {
    $goal = Goal::create( [
        'name'       => 'Equals Test',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name'       => 'test_event',
            'property_matches' => [
                'status' => ['eq' => 'active'],
            ],
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'test_event',
        'properties' => ['status' => 'active'],
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    expect( $goal->matches( $event ) )->toBeTrue();
} );

test( 'goal model matches operator contains', function (): void {
    $goal = Goal::create( [
        'name'       => 'Contains Test',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name'       => 'test_event',
            'property_matches' => [
                'message' => ['contains' => 'hello'],
            ],
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'test_event',
        'properties' => ['message' => 'hello world'],
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    expect( $goal->matches( $event ) )->toBeTrue();
} );

test( 'goal model matches operator regex', function (): void {
    $goal = Goal::create( [
        'name'       => 'Regex Test',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name'       => 'test_event',
            'property_matches' => [
                'email' => ['regex' => '/^[a-z]+@example\.com$/'],
            ],
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'test_event',
        'properties' => ['email' => 'test@example.com'],
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    expect( $goal->matches( $event ) )->toBeTrue();
} );

test( 'goal model matches operator in array', function (): void {
    $goal = Goal::create( [
        'name'       => 'In Array Test',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name'       => 'test_event',
            'property_matches' => [
                'plan' => ['in' => ['pro', 'enterprise']],
            ],
        ],
        'is_active' => true,
    ] );

    $event = Event::create( [
        'name'       => 'test_event',
        'properties' => ['plan' => 'pro'],
        'session_id' => Str::uuid()->toString(),
        'visitor_id' => Str::uuid()->toString(),
    ] );

    expect( $goal->matches( $event ) )->toBeTrue();
} );
