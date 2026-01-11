<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Providers\GoogleAnalyticsProvider;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    // Set up Google Analytics config for testing
    config()->set( 'artisanpack.analytics.providers.google', [
        'enabled'        => true,
        'measurement_id' => 'G-TEST12345',
        'api_secret'     => 'test-api-secret',
    ] );
} );

test( 'google provider returns correct name', function (): void {
    $provider = new GoogleAnalyticsProvider;

    expect( $provider->getName() )->toBe( 'google' );
} );

test( 'google provider is enabled when configured', function (): void {
    $provider = new GoogleAnalyticsProvider;

    expect( $provider->isEnabled() )->toBeTrue();
} );

test( 'google provider is disabled when not configured', function (): void {
    config()->set( 'artisanpack.analytics.providers.google.enabled', false );

    $provider = new GoogleAnalyticsProvider;

    expect( $provider->isEnabled() )->toBeFalse();
} );

test( 'google provider does not support queries', function (): void {
    $provider = new GoogleAnalyticsProvider;

    expect( $provider->supportsQueries() )->toBeFalse();
} );

test( 'google provider sends page view to measurement protocol', function (): void {
    Http::fake( [
        '*www.google-analytics.com/mp/collect*' => Http::response( '', 200 ),
    ] );

    $provider = new GoogleAnalyticsProvider;

    $data = new PageViewData(
        path: '/test-page',
        title: 'Test Page',
        visitorId: 'visitor-123',
    );

    $provider->trackPageView( $data );

    Http::assertSent( function ( $request ) {
        return str_contains( $request->url(), 'mp/collect' )
            && str_contains( $request->url(), 'G-TEST12345' )
            && str_contains( $request->url(), 'test-api-secret' );
    } );
} );

test( 'google provider sends event to measurement protocol', function (): void {
    Http::fake( [
        '*www.google-analytics.com/mp/collect*' => Http::response( '', 200 ),
    ] );

    $provider = new GoogleAnalyticsProvider;

    $data = new EventData(
        name: 'button_click',
        category: 'engagement',
        visitorId: 'visitor-123',
        properties: ['button_id' => 'cta'],
    );

    $provider->trackEvent( $data );

    Http::assertSent( function ( $request ) {
        $body = $request->data();

        return str_contains( $request->url(), 'mp/collect' )
            && isset( $body['events'][0]['name'] )
            && 'button_click' === $body['events'][0]['name'];
    } );
} );

test( 'google provider normalizes event names correctly', function (): void {
    Http::fake( [
        '*www.google-analytics.com/mp/collect*' => Http::response( '', 200 ),
    ] );

    $provider = new GoogleAnalyticsProvider;

    $data = new EventData(
        name: 'My Event-Name 123',
        visitorId: 'visitor-123',
    );

    $provider->trackEvent( $data );

    Http::assertSent( function ( $request ) {
        $body = $request->data();

        // Event name should be normalized (special chars replaced with underscores)
        return isset( $body['events'][0]['name'] )
            && 'My_Event_Name_123' === $body['events'][0]['name'];
    } );
} );

test( 'google provider includes utm parameters in page view', function (): void {
    Http::fake( [
        '*www.google-analytics.com/mp/collect*' => Http::response( '', 200 ),
    ] );

    $provider = new GoogleAnalyticsProvider;

    $data = new PageViewData(
        path: '/landing',
        visitorId: 'visitor-123',
        utmSource: 'google',
        utmMedium: 'cpc',
        utmCampaign: 'spring-sale',
    );

    $provider->trackPageView( $data );

    Http::assertSent( function ( $request ) {
        $body = $request->data();

        return isset( $body['events'][0]['params']['traffic_source'] );
    } );
} );

test( 'google provider does not track when disabled', function (): void {
    config()->set( 'artisanpack.analytics.providers.google.enabled', false );
    Http::fake();

    $provider = new GoogleAnalyticsProvider;

    $data = new PageViewData(
        path: '/test',
        visitorId: 'visitor-123',
    );

    $provider->trackPageView( $data );

    Http::assertNothingSent();
} );

test( 'google provider returns zero for query methods', function (): void {
    $provider = new GoogleAnalyticsProvider;
    $range    = DateRange::today();

    expect( $provider->getPageViews( $range ) )->toBe( 0 );
    expect( $provider->getVisitors( $range ) )->toBe( 0 );
    expect( $provider->getSessions( $range ) )->toBe( 0 );
    expect( $provider->getBounceRate( $range ) )->toBe( 0.0 );
    expect( $provider->getAverageSessionDuration( $range ) )->toBe( 0 );
    expect( $provider->getRealTimeVisitors() )->toBe( 0 );
} );

test( 'google provider returns empty collections for query methods', function (): void {
    $provider = new GoogleAnalyticsProvider;
    $range    = DateRange::today();

    expect( $provider->getTopPages( $range ) )->toBeEmpty();
    expect( $provider->getTrafficSources( $range ) )->toBeEmpty();
    expect( $provider->getPageViewsOverTime( $range ) )->toBeEmpty();
    expect( $provider->getDeviceBreakdown( $range ) )->toBeEmpty();
    expect( $provider->getBrowserBreakdown( $range ) )->toBeEmpty();
    expect( $provider->getCountryBreakdown( $range ) )->toBeEmpty();
} );

test( 'google provider handles http errors gracefully', function (): void {
    Http::fake( [
        '*www.google-analytics.com/mp/collect*' => Http::response( 'Error', 500 ),
    ] );

    $provider = new GoogleAnalyticsProvider;

    $data = new PageViewData(
        path: '/test',
        visitorId: 'visitor-123',
    );

    // Should not throw, just log the error
    $provider->trackPageView( $data );

    expect( $provider->getLastError() )->toBeNull();
} );

test( 'google provider generates client id when visitor id is missing', function (): void {
    Http::fake( [
        '*www.google-analytics.com/mp/collect*' => Http::response( '', 200 ),
    ] );

    $provider = new GoogleAnalyticsProvider;

    $data = new PageViewData(
        path: '/test',
    );

    $provider->trackPageView( $data );

    Http::assertSent( function ( $request ) {
        $body = $request->data();

        return isset( $body['client_id'] )
            && ! empty( $body['client_id']);
    });
});

test( 'google provider returns config array', function (): void {
    $provider = new GoogleAnalyticsProvider;
    $config   = $provider->getConfig();

    expect( $config)->toBeArray();
    expect( $config)->toHaveKeys( ['enabled', 'measurement_id', 'api_secret']);
});
