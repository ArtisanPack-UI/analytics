<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Enums\EventType;

test( 'event type has core events', function (): void {
    expect( EventType::PAGE_VIEW->value )->toBe( 'page_view' );
    expect( EventType::SESSION_START->value )->toBe( 'session_start' );
    expect( EventType::SESSION_END->value )->toBe( 'session_end' );
    expect( EventType::SCROLL->value )->toBe( 'scroll' );
    expect( EventType::CLICK->value )->toBe( 'click' );
    expect( EventType::SEARCH->value )->toBe( 'search' );
} );

test( 'event type has form events', function (): void {
    expect( EventType::FORM_VIEW->value )->toBe( 'form_view' );
    expect( EventType::FORM_START->value )->toBe( 'form_start' );
    expect( EventType::FORM_SUBMIT->value )->toBe( 'form_submitted' );
    expect( EventType::FORM_ERROR->value )->toBe( 'form_error' );
} );

test( 'event type has ecommerce events', function (): void {
    expect( EventType::PRODUCT_VIEW->value )->toBe( 'product_view' );
    expect( EventType::ADD_TO_CART->value )->toBe( 'add_to_cart' );
    expect( EventType::REMOVE_FROM_CART->value )->toBe( 'remove_from_cart' );
    expect( EventType::BEGIN_CHECKOUT->value )->toBe( 'begin_checkout' );
    expect( EventType::ADD_PAYMENT_INFO->value )->toBe( 'add_payment_info' );
    expect( EventType::PURCHASE->value )->toBe( 'purchase' );
    expect( EventType::REFUND->value )->toBe( 'refund' );
} );

test( 'event type has booking events', function (): void {
    expect( EventType::SERVICE_VIEW->value )->toBe( 'service_view' );
    expect( EventType::BOOKING_START->value )->toBe( 'booking_start' );
    expect( EventType::TIME_SELECTED->value )->toBe( 'time_selected' );
    expect( EventType::BOOKING_CREATED->value )->toBe( 'booking_created' );
    expect( EventType::BOOKING_CANCELLED->value )->toBe( 'booking_cancelled' );
} );

test( 'core events have null category', function (): void {
    expect( EventType::PAGE_VIEW->getCategory() )->toBeNull();
    expect( EventType::SESSION_START->getCategory() )->toBeNull();
    expect( EventType::SESSION_END->getCategory() )->toBeNull();
} );

test( 'engagement events have engagement category', function (): void {
    expect( EventType::CLICK->getCategory() )->toBe( 'engagement' );
    expect( EventType::SCROLL->getCategory() )->toBe( 'engagement' );
    expect( EventType::DOWNLOAD->getCategory() )->toBe( 'engagement' );
    expect( EventType::VIDEO_PLAY->getCategory() )->toBe( 'engagement' );
} );

test( 'form events have forms category', function (): void {
    expect( EventType::FORM_VIEW->getCategory() )->toBe( 'forms' );
    expect( EventType::FORM_SUBMIT->getCategory() )->toBe( 'forms' );
    expect( EventType::FORM_ERROR->getCategory() )->toBe( 'forms' );
} );

test( 'ecommerce events have ecommerce category', function (): void {
    expect( EventType::PRODUCT_VIEW->getCategory() )->toBe( 'ecommerce' );
    expect( EventType::ADD_TO_CART->getCategory() )->toBe( 'ecommerce' );
    expect( EventType::PURCHASE->getCategory() )->toBe( 'ecommerce' );
} );

test( 'booking events have booking category', function (): void {
    expect( EventType::SERVICE_VIEW->getCategory() )->toBe( 'booking' );
    expect( EventType::BOOKING_START->getCategory() )->toBe( 'booking' );
    expect( EventType::BOOKING_CREATED->getCategory() )->toBe( 'booking' );
} );

test( 'can infer category from event name', function (): void {
    expect( EventType::inferCategory( 'form_submitted' ) )->toBe( 'forms' );
    expect( EventType::inferCategory( 'purchase' ) )->toBe( 'ecommerce' );
    expect( EventType::inferCategory( 'booking_created' ) )->toBe( 'booking' );
} );

test( 'inferred category returns null for unknown events', function (): void {
    expect( EventType::inferCategory( 'custom_event' ) )->toBeNull();
    expect( EventType::inferCategory( 'something_random' ) )->toBeNull();
} );

test( 'can get events for category', function (): void {
    $formEvents = EventType::forCategory( 'forms' );

    expect( $formEvents )->toBeArray();
    expect( $formEvents )->toContain( EventType::FORM_VIEW );
    expect( $formEvents )->toContain( EventType::FORM_SUBMIT );
    expect( $formEvents )->not->toContain( EventType::PURCHASE );
} );

test( 'can get required properties for purchase event', function (): void {
    $required = EventType::PURCHASE->getRequiredProperties();

    expect( $required )->toBeArray();
    expect( $required )->toContain( 'order_id' );
    expect( $required )->toContain( 'total' );
} );

test( 'can get required properties for form submit event', function (): void {
    $required = EventType::FORM_SUBMIT->getRequiredProperties();

    expect( $required )->toBeArray();
    expect( $required )->toContain( 'form_id' );
} );

test( 'events without required properties return empty array', function (): void {
    expect( EventType::PAGE_VIEW->getRequiredProperties() )->toBe( [] );
    expect( EventType::CLICK->getRequiredProperties() )->toBe( [] );
} );

test( 'purchase and refund events have value', function (): void {
    expect( EventType::PURCHASE->hasValue() )->toBeTrue();
    expect( EventType::REFUND->hasValue() )->toBeTrue();
    expect( EventType::ADD_TO_CART->hasValue() )->toBeTrue();
} );

test( 'core events do not have value', function (): void {
    expect( EventType::PAGE_VIEW->hasValue() )->toBeFalse();
    expect( EventType::CLICK->hasValue() )->toBeFalse();
} );

test( 'can get core events', function (): void {
    $coreEvents = EventType::coreEvents();

    expect( $coreEvents )->toContain( EventType::PAGE_VIEW );
    expect( $coreEvents )->toContain( EventType::SESSION_START );
    expect( $coreEvents )->toContain( EventType::SESSION_END );
} );

test( 'can get form events', function (): void {
    $formEvents = EventType::formEvents();

    expect( $formEvents )->toContain( EventType::FORM_VIEW );
    expect( $formEvents )->toContain( EventType::FORM_SUBMIT );
} );

test( 'can get ecommerce events', function (): void {
    $ecommerceEvents = EventType::ecommerceEvents();

    expect( $ecommerceEvents )->toContain( EventType::PURCHASE );
    expect( $ecommerceEvents )->toContain( EventType::ADD_TO_CART );
} );

test( 'can get booking events', function (): void {
    $bookingEvents = EventType::bookingEvents();

    expect( $bookingEvents )->toContain( EventType::BOOKING_START );
    expect( $bookingEvents )->toContain( EventType::BOOKING_CREATED );
} );

test( 'can get engagement events', function (): void {
    $engagementEvents = EventType::engagementEvents();

    expect( $engagementEvents )->toContain( EventType::CLICK );
    expect( $engagementEvents )->toContain( EventType::SCROLL );
    expect( $engagementEvents )->toContain( EventType::DOWNLOAD);
});
