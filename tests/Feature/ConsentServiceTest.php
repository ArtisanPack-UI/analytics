<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\ConsentService;
use ArtisanPackUI\Analytics\Services\IpAnonymizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	config()->set( 'artisanpack.analytics.privacy.consent_required', true );
	config()->set( 'artisanpack.analytics.privacy.respect_dnt', true );
	config()->set( 'artisanpack.analytics.privacy.consent_cookie_lifetime', 365 );
	config()->set( 'artisanpack.analytics.privacy.consent_categories', [
		'analytics' => [
			'name'        => 'Analytics',
			'description' => 'Helps us understand how visitors use our website.',
			'required'    => false,
		],
		'marketing' => [
			'name'        => 'Marketing',
			'description' => 'Used to track visitors across websites for advertising.',
			'required'    => false,
		],
	] );
} );

test( 'consent service grants consent for categories', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$visitor = $service->grantConsent( $fingerprint, ['analytics', 'marketing'] );

	expect( $visitor )->toBeInstanceOf( Visitor::class );
	expect( $visitor->fingerprint )->toBe( $fingerprint );

	$this->assertDatabaseHas( 'analytics_consents', [
		'visitor_id' => $visitor->id,
		'category'   => 'analytics',
		'granted'    => true,
	] );

	$this->assertDatabaseHas( 'analytics_consents', [
		'visitor_id' => $visitor->id,
		'category'   => 'marketing',
		'granted'    => true,
	] );
} );

test( 'consent service checks consent status correctly', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	// Initially no consent
	expect( $service->hasConsent( $fingerprint, 'analytics' ) )->toBeFalse();

	// Grant consent
	$service->grantConsent( $fingerprint, ['analytics'] );

	// Now has consent
	expect( $service->hasConsent( $fingerprint, 'analytics' ) )->toBeTrue();
	expect( $service->hasConsent( $fingerprint, 'marketing' ) )->toBeFalse();
} );

test( 'consent service revokes consent correctly', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	// Grant then revoke
	$service->grantConsent( $fingerprint, ['analytics', 'marketing'] );
	$service->revokeConsent( $fingerprint, ['analytics'] );

	expect( $service->hasConsent( $fingerprint, 'analytics' ) )->toBeFalse();
	expect( $service->hasConsent( $fingerprint, 'marketing' ) )->toBeTrue();
} );

test( 'consent service revokes all consents', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$service->grantConsent( $fingerprint, ['analytics', 'marketing'] );
	$service->revokeAllConsents( $fingerprint );

	expect( $service->hasConsent( $fingerprint, 'analytics' ) )->toBeFalse();
	expect( $service->hasConsent( $fingerprint, 'marketing' ) )->toBeFalse();
} );

test( 'consent service returns consent status for all categories', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$service->grantConsent( $fingerprint, ['analytics'] );

	$status = $service->getConsentStatus( $fingerprint );

	expect( $status )->toHaveKey( 'analytics' );
	expect( $status )->toHaveKey( 'marketing' );
	expect( $status['analytics']['granted'] )->toBeTrue();
	expect( $status['marketing']['granted'] )->toBeFalse();
} );

test( 'consent service returns true when consent not required', function (): void {
	config()->set( 'artisanpack.analytics.privacy.consent_required', false );

	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	expect( $service->hasConsent( $fingerprint, 'analytics' ) )->toBeTrue();
} );

test( 'consent service stores IP and user agent on grant', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	$request = Request::create( '/test', 'GET', [], [], [], [
		'REMOTE_ADDR'     => '192.168.1.100',
		'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser',
	] );

	$visitor = $service->grantConsent( $fingerprint, ['analytics'], $request );

	$consent = Consent::where( 'visitor_id', $visitor->id )
		->where( 'category', 'analytics' )
		->first();

	expect( $consent->ip_address )->not->toBeNull();
	expect( $consent->user_agent )->toBe( 'Mozilla/5.0 Test Browser' );
} );

test( 'consent service creates visitor if not exists', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'new-visitor-' . Str::random( 10 );

	$this->assertDatabaseMissing( 'analytics_visitors', [
		'fingerprint' => $fingerprint,
	] );

	$service->grantConsent( $fingerprint, ['analytics'] );

	$this->assertDatabaseHas( 'analytics_visitors', [
		'fingerprint' => $fingerprint,
	] );
} );

test( 'consent service updates existing consent', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'test-fingerprint-' . Str::random( 10 );

	// Grant, revoke, then grant again
	$visitor = $service->grantConsent( $fingerprint, ['analytics'] );
	$service->revokeConsent( $fingerprint, ['analytics'] );
	$service->grantConsent( $fingerprint, ['analytics'] );

	// Should only have one consent record
	$consentCount = Consent::where( 'visitor_id', $visitor->id )
		->where( 'category', 'analytics' )
		->count();

	expect( $consentCount )->toBe( 1 );
} );

test( 'consent service handles non-existent visitor for revoke', function (): void {
	$service = new ConsentService( new IpAnonymizer );

	$fingerprint = 'nonexistent-visitor-' . Str::random( 10 );

	// Should not throw exception
	$service->revokeConsent( $fingerprint, ['analytics'] );

	expect( true )->toBeTrue();
} );
