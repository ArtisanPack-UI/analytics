<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\EventData;

test( 'can create event data', function (): void {
	$data = new EventData(
		name: 'button_click',
		properties: [ 'button_id' => 'cta-main' ],
		value: 100.00,
	);

	expect( $data->name )->toBe( 'button_click' );
	expect( $data->properties )->toBe( [ 'button_id' => 'cta-main' ] );
	expect( $data->value )->toBe( 100.00 );
} );

test( 'event data name is required', function (): void {
	$data = new EventData( name: 'required_event' );

	expect( $data->name )->toBe( 'required_event' );
	expect( $data->properties )->toBeNull();
	expect( $data->value )->toBeNull();
} );

test( 'event data supports category', function (): void {
	$data = new EventData(
		name: 'signup_completed',
		category: 'conversion',
	);

	expect( $data->category )->toBe( 'conversion' );
} );

test( 'event data can check for property', function (): void {
	$data = new EventData(
		name: 'purchase',
		properties: [ 'amount' => 99.99, 'currency' => 'USD' ],
	);

	expect( $data->hasProperty( 'amount' ) )->toBeTrue();
	expect( $data->hasProperty( 'missing' ) )->toBeFalse();
} );

test( 'event data can get property value', function (): void {
	$data = new EventData(
		name: 'purchase',
		properties: [ 'amount' => 99.99 ],
	);

	expect( $data->getProperty( 'amount' ) )->toBe( 99.99 );
	expect( $data->getProperty( 'missing', 'default' ) )->toBe( 'default' );
} );

test( 'event data can be converted to array', function (): void {
	$data = new EventData(
		name: 'form_submit',
		properties: [ 'form_id' => 'contact' ],
		sessionId: 'session-456',
	);

	$array = $data->toArray();

	expect( $array )->toBeArray();
	expect( $array['name'] )->toBe( 'form_submit' );
	expect( $array['properties'] )->toBe( [ 'form_id' => 'contact' ] );
	expect( $array['session_id'] )->toBe( 'session-456' );
} );

test( 'event data array excludes null values', function (): void {
	$data = new EventData( name: 'simple_event' );

	$array = $data->toArray();

	expect( $array )->toHaveKey( 'name' );
	expect( $array )->not->toHaveKey( 'properties' );
	expect( $array )->not->toHaveKey( 'value' );
} );

test( 'event data supports tenant id', function (): void {
	$data = new EventData(
		name: 'tenant_event',
		tenantId: 42,
	);

	expect( $data->tenantId )->toBe( 42 );
} );

test( 'event data supports path', function (): void {
	$data = new EventData(
		name: 'page_scroll',
		path: '/long-article',
	);

	expect( $data->path )->toBe( '/long-article' );
} );
