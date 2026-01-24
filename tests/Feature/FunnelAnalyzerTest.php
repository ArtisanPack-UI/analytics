<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\FunnelAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses( RefreshDatabase::class );

test( 'funnel analyzer throws exception when goal has no funnel steps', function (): void {
    $goal = Goal::create( [
        'name'       => 'No Funnel Goal',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'purchase',
        ],
        'is_active' => true,
    ] );

    $analyzer = new FunnelAnalyzer;
    $range    = DateRange::today();

    expect( fn () => $analyzer->analyze( $goal, $range ) )
        ->toThrow( InvalidArgumentException::class );
} );

test( 'funnel analyzer analyzes event-based funnel', function (): void {
    $goal = Goal::create( [
        'name'       => 'Purchase Funnel',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'purchase',
        ],
        'funnel_steps' => [
            ['type' => 'event', 'name' => 'product_view'],
            ['type' => 'event', 'name' => 'add_to_cart'],
            ['type' => 'event', 'name' => 'checkout_start'],
            ['type' => 'event', 'name' => 'purchase'],
        ],
        'is_active' => true,
    ] );

    // Create visitors who complete various steps
    $visitor1 = Str::uuid()->toString();
    $visitor2 = Str::uuid()->toString();
    $visitor3 = Str::uuid()->toString();

    // Visitor 1 - completes all steps
    Event::create( ['name' => 'product_view', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'add_to_cart', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'checkout_start', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'purchase', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );

    // Visitor 2 - drops off at checkout
    Event::create( ['name' => 'product_view', 'visitor_id' => $visitor2, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'add_to_cart', 'visitor_id' => $visitor2, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'checkout_start', 'visitor_id' => $visitor2, 'session_id' => Str::uuid()->toString()] );

    // Visitor 3 - drops off at cart
    Event::create( ['name' => 'product_view', 'visitor_id' => $visitor3, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'add_to_cart', 'visitor_id' => $visitor3, 'session_id' => Str::uuid()->toString()] );

    $analyzer = new FunnelAnalyzer;
    $range    = DateRange::today();
    $result   = $analyzer->analyze( $goal, $range );

    expect( $result )->toBeArray();
    expect( $result )->toHaveKey( 'steps' );
    expect( $result )->toHaveKey( 'overall_conversion' );
    expect( $result['steps'] )->toHaveCount( 4 );
} );

test( 'funnel analyzer analyzes pageview-based funnel', function (): void {
    $goal = Goal::create( [
        'name'       => 'Registration Funnel',
        'type'       => Goal::TYPE_PAGEVIEW,
        'conditions' => [
            'path_exact' => '/thank-you',
        ],
        'funnel_steps' => [
            ['type' => 'pageview', 'path' => '/pricing'],
            ['type' => 'pageview', 'path' => '/signup'],
            ['type' => 'pageview', 'path' => '/thank-you'],
        ],
        'is_active' => true,
    ] );

    // Create visitors who view pages
    $visitor1 = Str::uuid()->toString();
    $visitor2 = Str::uuid()->toString();

    PageView::create( ['path' => '/pricing', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );
    PageView::create( ['path' => '/signup', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );
    PageView::create( ['path' => '/thank-you', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );

    PageView::create( ['path' => '/pricing', 'visitor_id' => $visitor2, 'session_id' => Str::uuid()->toString()] );
    PageView::create( ['path' => '/signup', 'visitor_id' => $visitor2, 'session_id' => Str::uuid()->toString()] );

    $analyzer = new FunnelAnalyzer;
    $range    = DateRange::today();
    $result   = $analyzer->analyze( $goal, $range );

    expect( $result )->toBeArray();
    expect( $result['steps'] )->toHaveCount( 3 );
} );

test( 'funnel analyzer compares two periods', function (): void {
    $goal = Goal::create( [
        'name'       => 'Simple Funnel',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'purchase',
        ],
        'funnel_steps' => [
            ['type' => 'event', 'name' => 'product_view'],
            ['type' => 'event', 'name' => 'purchase'],
        ],
        'is_active' => true,
    ] );

    // Create events for current period
    Event::create( [
        'name'       => 'product_view',
        'visitor_id' => Str::uuid()->toString(),
        'session_id' => Str::uuid()->toString(),
        'created_at' => now(),
    ] );

    // Create events for previous period (yesterday)
    Event::create( [
        'name'       => 'product_view',
        'visitor_id' => Str::uuid()->toString(),
        'session_id' => Str::uuid()->toString(),
        'created_at' => now()->subDay(),
    ] );

    $analyzer      = new FunnelAnalyzer;
    $currentRange  = DateRange::today();
    $previousRange = DateRange::yesterday();
    $result        = $analyzer->compare( $goal, $currentRange, $previousRange );

    expect( $result )->toBeArray();
    expect( $result )->toHaveKey( 'current' );
    expect( $result )->toHaveKey( 'previous' );
    expect( $result )->toHaveKey( 'change' );
} );

test( 'funnel analyzer identifies bottlenecks', function (): void {
    $goal = Goal::create( [
        'name'       => 'Checkout Funnel',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'purchase',
        ],
        'funnel_steps' => [
            ['type' => 'event', 'name' => 'cart_view'],
            ['type' => 'event', 'name' => 'checkout_start'],
            ['type' => 'event', 'name' => 'payment_info'],
            ['type' => 'event', 'name' => 'purchase'],
        ],
        'is_active' => true,
    ] );

    // Create a funnel with significant drop-off at checkout_start
    for ( $i = 0; $i < 10; $i++ ) {
        $visitor = Str::uuid()->toString();
        Event::create( ['name' => 'cart_view', 'visitor_id' => $visitor, 'session_id' => Str::uuid()->toString()] );
    }

    for ( $i = 0; $i < 3; $i++ ) {
        $visitor = Str::uuid()->toString();
        Event::create( ['name' => 'cart_view', 'visitor_id' => $visitor, 'session_id' => Str::uuid()->toString()] );
        Event::create( ['name' => 'checkout_start', 'visitor_id' => $visitor, 'session_id' => Str::uuid()->toString()] );
        Event::create( ['name' => 'payment_info', 'visitor_id' => $visitor, 'session_id' => Str::uuid()->toString()] );
        Event::create( ['name' => 'purchase', 'visitor_id' => $visitor, 'session_id' => Str::uuid()->toString()] );
    }

    $analyzer    = new FunnelAnalyzer;
    $range       = DateRange::today();
    $bottlenecks = $analyzer->getBottlenecks( $goal, $range, 3 );

    expect( $bottlenecks )->toBeArray();
    expect( $bottlenecks )->not->toBeEmpty();
} );

test( 'funnel analyzer calculates step conversion rates', function (): void {
    $goal = Goal::create( [
        'name'       => 'Onboarding Funnel',
        'type'       => Goal::TYPE_EVENT,
        'conditions' => [
            'event_name' => 'onboarding_complete',
        ],
        'funnel_steps' => [
            ['type' => 'event', 'name' => 'signup'],
            ['type' => 'event', 'name' => 'profile_setup'],
            ['type' => 'event', 'name' => 'onboarding_complete'],
        ],
        'is_active' => true,
    ] );

    // Visitor completes all steps
    $visitor1 = Str::uuid()->toString();
    Event::create( ['name' => 'signup', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'profile_setup', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'onboarding_complete', 'visitor_id' => $visitor1, 'session_id' => Str::uuid()->toString()] );

    // Visitor drops off after profile_setup
    $visitor2 = Str::uuid()->toString();
    Event::create( ['name' => 'signup', 'visitor_id' => $visitor2, 'session_id' => Str::uuid()->toString()] );
    Event::create( ['name' => 'profile_setup', 'visitor_id' => $visitor2, 'session_id' => Str::uuid()->toString()] );

    $analyzer = new FunnelAnalyzer;
    $range    = DateRange::today();
    $result   = $analyzer->analyze( $goal, $range );

    // Check step structure
    foreach ( $result['steps'] as $step ) {
        expect( $step )->toHaveKey( 'name' );
        expect( $step )->toHaveKey( 'visitors' );
        expect( $step )->toHaveKey( 'conversion_rate' );
        expect( $step )->toHaveKey( 'dropoff_rate' );
    }
} );
