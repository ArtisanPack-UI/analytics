<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Events\EventTracked;
use ArtisanPackUI\Analytics\Events\GoalConverted;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;

test( 'event tracked event can be instantiated', function (): void {
    $event       = new Event;
    $event->id   = 1;
    $event->name = 'button_click';

    $eventTracked = new EventTracked( $event );

    expect( $eventTracked->event )->toBe( $event );
    expect( $eventTracked->session )->toBeNull();
    expect( $eventTracked->visitor )->toBeNull();
} );

test( 'event tracked event can include session and visitor', function (): void {
    $event       = new Event;
    $event->id   = 1;
    $event->name = 'button_click';

    $session     = new Session;
    $session->id = 'session-123';

    $visitor     = new Visitor;
    $visitor->id = 'visitor-123';

    $eventTracked = new EventTracked( $event, $session, $visitor );

    expect( $eventTracked->event )->toBe( $event );
    expect( $eventTracked->session )->toBe( $session );
    expect( $eventTracked->visitor )->toBe( $visitor );
} );

test( 'goal converted event can be instantiated', function (): void {
    $goal       = new Goal;
    $goal->id   = 1;
    $goal->name = 'Test Goal';

    $conversion          = new Conversion;
    $conversion->id      = 1;
    $conversion->goal_id = $goal->id;

    $goalConverted = new GoalConverted( $goal, $conversion );

    expect( $goalConverted->goal )->toBe( $goal );
    expect( $goalConverted->conversion )->toBe( $conversion );
    expect( $goalConverted->session )->toBeNull();
    expect( $goalConverted->visitor )->toBeNull();
} );

test( 'goal converted event can include session and visitor', function (): void {
    $goal       = new Goal;
    $goal->id   = 1;
    $goal->name = 'Test Goal';

    $conversion          = new Conversion;
    $conversion->id      = 1;
    $conversion->goal_id = $goal->id;

    $session     = new Session;
    $session->id = 'session-123';

    $visitor     = new Visitor;
    $visitor->id = 'visitor-123';

    $goalConverted = new GoalConverted( $goal, $conversion, $session, $visitor );

    expect( $goalConverted->goal )->toBe( $goal );
    expect( $goalConverted->conversion )->toBe( $conversion );
    expect( $goalConverted->session )->toBe( $session );
    expect( $goalConverted->visitor )->toBe( $visitor );
} );

test( 'event tracked uses dispatchable trait', function (): void {
    expect( class_uses_recursive( EventTracked::class ) )
        ->toContain( Illuminate\Foundation\Events\Dispatchable::class );
} );

test( 'goal converted uses dispatchable trait', function (): void {
    expect( class_uses_recursive( GoalConverted::class ) )
        ->toContain( Illuminate\Foundation\Events\Dispatchable::class );
} );
