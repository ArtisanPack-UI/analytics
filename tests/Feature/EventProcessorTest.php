<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Events\EventTracked;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\EventProcessor;
use ArtisanPackUI\Analytics\Services\GoalMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Str;

uses( RefreshDatabase::class );

beforeEach( function (): void {
    config()->set( 'artisanpack.analytics.goals.allow_multiple_per_session', false );
    // Clear schema validation for most tests
    config()->set( 'artisanpack.analytics.events.schema', [] );
} );

test( 'event processor creates event from data', function (): void {
    $processor = new EventProcessor( new GoalMatcher );

    $data = new EventData(
        name: 'button_click',
        category: 'engagement',
        properties: ['button_id' => 'cta-main'],
        value: 1.5,
    );

    $event = $processor->process( $data );

    expect( $event )->toBeInstanceOf( Event::class );
    expect( $event->name )->toBe( 'button_click' );
    expect( $event->category )->toBe( 'engagement' );
    expect( $event->properties )->toBe( ['button_id' => 'cta-main'] );
    expect( (float) $event->value )->toBe( 1.5 );
} );

test( 'event processor fires event tracked event', function (): void {
    EventFacade::fake( [EventTracked::class] );

    $processor = new EventProcessor( new GoalMatcher );

    $data = new EventData(
        name: 'button_click',
    );

    $processor->process( $data );

    EventFacade::assertDispatched( EventTracked::class );
} );

test( 'event processor infers category from event type', function (): void {
    $processor = new EventProcessor( new GoalMatcher );

    $data = new EventData(
        name: 'purchase',
    );

    $event = $processor->process( $data );

    expect( $event->category )->toBe( 'ecommerce' );
} );

test( 'event processor preserves explicitly set category', function (): void {
    $processor = new EventProcessor( new GoalMatcher );

    $data = new EventData(
        name: 'purchase',
        category: 'custom_category',
    );

    $event = $processor->process( $data );

    expect( $event->category )->toBe( 'custom_category' );
} );

test( 'event processor triggers goal matching', function (): void {
    $goal = Goal::create( [
        'name'       => 'Purchase Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'purchase',
        ],
        'is_active' => true,
    ] );

    $processor = new EventProcessor( new GoalMatcher );

    $data = new EventData(
        name: 'purchase',
        properties: ['order_id' => '12345', 'total' => 99.99],
    );

    $processor->process( $data );

    $this->assertDatabaseHas( 'analytics_conversions', [
        'goal_id' => $goal->id,
    ] );
} );

test( 'event processor track method creates event', function (): void {
    $processor = new EventProcessor( new GoalMatcher );

    $event = $processor->track(
        name: 'signup',
        properties: ['plan' => 'premium'],
        category: 'conversion',
        value: 50.00,
        sourcePackage: 'auth-package',
    );

    expect( $event )->toBeInstanceOf( Event::class );
    expect( $event->name )->toBe( 'signup' );
    expect( $event->category )->toBe( 'conversion' );
    expect( $event->properties )->toBe( ['plan' => 'premium'] );
    expect( (float) $event->value )->toBe( 50.00 );
    expect( $event->source_package )->toBe( 'auth-package' );
} );

test( 'event processor validates event schema when configured', function (): void {
    // Enable schema validation for this test
    config()->set( 'artisanpack.analytics.events.schema', [
        'purchase' => [
            'required' => ['order_id', 'total'],
        ],
    ] );

    $processor = new EventProcessor( new GoalMatcher );

    // Valid event with required properties
    $data = new EventData(
        name: 'purchase',
        properties: ['order_id' => '12345', 'total' => 99.99],
    );

    $event = $processor->process( $data );
    expect( $event )->toBeInstanceOf( Event::class );
} );

test( 'event processor handles source package', function (): void {
    $processor = new EventProcessor( new GoalMatcher );

    $data = new EventData(
        name: 'form_submit',
        sourcePackage: 'artisanpack-forms',
    );

    $event = $processor->process( $data );

    expect( $event->source_package )->toBe( 'artisanpack-forms' );
} );

test( 'event processor handles action and label', function (): void {
    $processor = new EventProcessor( new GoalMatcher );

    $data = new EventData(
        name: 'video_play',
        action: 'play',
        label: 'intro-video',
    );

    $event = $processor->process( $data );

    expect( $event->action )->toBe( 'play' );
    expect( $event->label )->toBe( 'intro-video' );
} );

test( 'event processor associates with session and visitor', function (): void {
    $visitorFingerprint = 'test-fingerprint-' . Str::random( 10 );
    $sessionId          = Str::uuid()->toString();

    $visitor = Visitor::create( [
        'visitor_id'    => Str::uuid()->toString(),
        'fingerprint'   => $visitorFingerprint,
        'first_seen_at' => now(),
        'last_seen_at'  => now(),
    ] );

    $session = Session::create( [
        'session_id'       => $sessionId,
        'visitor_id'       => $visitor->id,
        'started_at'       => now(),
        'last_activity_at' => now(),
        'entry_page'       => '/',
    ] );

    $processor = new EventProcessor( new GoalMatcher );

    $data = new EventData(
        name: 'button_click',
        sessionId: $sessionId,
        visitorId: $visitorFingerprint,
    );

    $event = $processor->process( $data );

    // EventProcessor resolves visitor by fingerprint and stores the model's primary key ID
    expect( $event->session_id )->toBe( $session->id );
    expect( $event->visitor_id )->toBe( $visitor->id );
} );

test( 'event processor handles path context', function (): void {
    $processor = new EventProcessor( new GoalMatcher);

    $data = new EventData(
        name: 'scroll_depth',
        path: '/long-article',
        properties: ['depth' => 75],
    );

    $event = $processor->process( $data);

    expect( $event->path)->toBe( '/long-article');
});
