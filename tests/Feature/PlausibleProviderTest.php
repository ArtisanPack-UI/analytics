<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Providers\PlausibleProvider;
use Illuminate\Support\Facades\Http;

beforeEach( function (): void {
    // Set up Plausible config for testing
    config()->set( 'artisanpack.analytics.providers.plausible', [
        'enabled' => true,
        'domain'  => 'example.com',
        'api_url' => 'https://plausible.io/api',
        'api_key' => 'test-api-key',
    ] );
} );

test( 'plausible provider returns correct name', function (): void {
    $provider = new PlausibleProvider;

    expect( $provider->getName() )->toBe( 'plausible' );
} );

test( 'plausible provider is enabled when configured', function (): void {
    $provider = new PlausibleProvider;

    expect( $provider->isEnabled() )->toBeTrue();
} );

test( 'plausible provider is disabled when not configured', function (): void {
    config()->set( 'artisanpack.analytics.providers.plausible.enabled', false );

    $provider = new PlausibleProvider;

    expect( $provider->isEnabled() )->toBeFalse();
} );

test( 'plausible provider supports queries when api key is present', function (): void {
    $provider = new PlausibleProvider;

    expect( $provider->supportsQueries() )->toBeTrue();
} );

test( 'plausible provider does not support queries without api key', function (): void {
    config()->set( 'artisanpack.analytics.providers.plausible.api_key', null );

    $provider = new PlausibleProvider;

    expect( $provider->supportsQueries() )->toBeFalse();
} );

test( 'plausible provider sends page view to events api', function (): void {
    Http::fake( [
        '*plausible.io/api/event*' => Http::response( 'ok', 202 ),
    ] );

    $provider = new PlausibleProvider;

    $data = new PageViewData(
        path: '/test-page',
        title: 'Test Page',
        visitorId: 'visitor-123',
    );

    $provider->trackPageView( $data );

    Http::assertSent( function ( $request ) {
        $body = $request->data();

        return str_contains( $request->url(), 'event' )
            && 'pageview' === $body['name']
            && 'example.com' === $body['domain']
            && str_contains( $body['url'], '/test-page' );
    } );
} );

test( 'plausible provider sends event to events api', function (): void {
    Http::fake( [
        '*plausible.io/api/event*' => Http::response( 'ok', 202 ),
    ] );

    $provider = new PlausibleProvider;

    $data = new EventData(
        name: 'signup',
        path: '/register',
        visitorId: 'visitor-123',
        properties: ['plan' => 'premium'],
    );

    $provider->trackEvent( $data );

    Http::assertSent( function ( $request ) {
        $body = $request->data();

        return str_contains( $request->url(), 'event' )
            && 'signup' === $body['name']
            && isset( $body['props']['plan'] );
    } );
} );

test( 'plausible provider includes utm parameters in page view', function (): void {
    Http::fake( [
        '*plausible.io/api/event*' => Http::response( 'ok', 202 ),
    ] );

    $provider = new PlausibleProvider;

    $data = new PageViewData(
        path: '/landing',
        visitorId: 'visitor-123',
        utmSource: 'newsletter',
        utmMedium: 'email',
        utmCampaign: 'weekly',
    );

    $provider->trackPageView( $data );

    Http::assertSent( function ( $request ) {
        $body = $request->data();

        return isset( $body['props']['utm_source'] )
            && 'newsletter' === $body['props']['utm_source'];
    } );
} );

test( 'plausible provider does not track when disabled', function (): void {
    config()->set( 'artisanpack.analytics.providers.plausible.enabled', false );
    Http::fake();

    $provider = new PlausibleProvider;

    $data = new PageViewData(
        path: '/test',
        visitorId: 'visitor-123',
    );

    $provider->trackPageView( $data );

    Http::assertNothingSent();
} );

test( 'plausible provider queries page views from stats api', function (): void {
    Http::fake( [
        '*plausible.io/api/v1/stats/aggregate*' => Http::response( [
            'results' => [
                'pageviews' => ['value' => 1500],
            ],
        ], 200 ),
    ] );

    $provider = new PlausibleProvider;
    $range    = DateRange::lastDays( 7 );

    $pageViews = $provider->getPageViews( $range );

    expect( $pageViews )->toBe( 1500 );
} );

test( 'plausible provider queries visitors from stats api', function (): void {
    Http::fake( [
        '*plausible.io/api/v1/stats/aggregate*' => Http::response( [
            'results' => [
                'visitors' => ['value' => 500],
            ],
        ], 200 ),
    ] );

    $provider = new PlausibleProvider;
    $range    = DateRange::lastDays( 7 );

    $visitors = $provider->getVisitors( $range );

    expect( $visitors )->toBe( 500 );
} );

test( 'plausible provider queries sessions from stats api', function (): void {
    Http::fake( [
        '*plausible.io/api/v1/stats/aggregate*' => Http::response( [
            'results' => [
                'visits' => ['value' => 750],
            ],
        ], 200 ),
    ] );

    $provider = new PlausibleProvider;
    $range    = DateRange::lastDays( 7 );

    $sessions = $provider->getSessions( $range );

    expect( $sessions )->toBe( 750 );
} );

test( 'plausible provider queries bounce rate from stats api', function (): void {
    Http::fake( [
        '*plausible.io/api/v1/stats/aggregate*' => Http::response( [
            'results' => [
                'bounce_rate' => ['value' => 45.5],
            ],
        ], 200 ),
    ] );

    $provider = new PlausibleProvider;
    $range    = DateRange::lastDays( 7 );

    $bounceRate = $provider->getBounceRate( $range );

    expect( $bounceRate )->toBe( 45.5 );
} );

test( 'plausible provider queries top pages from stats api', function (): void {
    Http::fake( [
        '*plausible.io/api/v1/stats/breakdown*' => Http::response( [
            'results' => [
                ['page' => '/', 'pageviews' => 500, 'visitors' => 300],
                ['page' => '/about', 'pageviews' => 200, 'visitors' => 150],
            ],
        ], 200 ),
    ] );

    $provider = new PlausibleProvider;
    $range    = DateRange::lastDays( 7 );

    $topPages = $provider->getTopPages( $range );

    expect( $topPages )->toHaveCount( 2 );
    expect( $topPages->first()['path'] )->toBe( '/' );
    expect( $topPages->first()['views'] )->toBe( 500 );
} );

test( 'plausible provider queries traffic sources from stats api', function (): void {
    Http::fake( [
        '*plausible.io/api/v1/stats/breakdown*' => Http::response( [
            'results' => [
                ['source' => 'Google', 'visits' => 300, 'visitors' => 200],
                ['source' => 'Direct / None', 'visits' => 200, 'visitors' => 150],
            ],
        ], 200 ),
    ] );

    $provider = new PlausibleProvider;
    $range    = DateRange::lastDays( 7 );

    $sources = $provider->getTrafficSources( $range );

    expect( $sources )->toHaveCount( 2 );
    expect( $sources->first()['source'] )->toBe( 'Google' );
    expect( $sources->first()['sessions'] )->toBe( 300 );
} );

test( 'plausible provider queries real-time visitors from stats api', function (): void {
    Http::fake( [
        '*plausible.io/api/v1/stats/realtime/visitors*' => Http::response( 42, 200 ),
    ] );

    $provider = new PlausibleProvider;

    $realtime = $provider->getRealTimeVisitors();

    expect( $realtime )->toBe( 42 );
} );

test( 'plausible provider returns zero for queries without api key', function (): void {
    config()->set( 'artisanpack.analytics.providers.plausible.api_key', null );

    $provider = new PlausibleProvider;
    $range    = DateRange::today();

    expect( $provider->getPageViews( $range ) )->toBe( 0 );
    expect( $provider->getVisitors( $range ) )->toBe( 0 );
    expect( $provider->getSessions( $range ) )->toBe( 0 );
    expect( $provider->getRealTimeVisitors() )->toBe( 0 );
} );

test( 'plausible provider returns empty collections without api key', function (): void {
    config()->set( 'artisanpack.analytics.providers.plausible.api_key', null );

    $provider = new PlausibleProvider;
    $range    = DateRange::today();

    expect( $provider->getTopPages( $range ) )->toBeEmpty();
    expect( $provider->getTrafficSources( $range ) )->toBeEmpty();
    expect( $provider->getDeviceBreakdown( $range ) )->toBeEmpty();
} );

test( 'plausible provider handles http errors gracefully', function (): void {
    Http::fake( [
        '*plausible.io/api/event*' => Http::response( 'Error', 500 ),
    ] );

    $provider = new PlausibleProvider;

    $data = new PageViewData(
        path: '/test',
        visitorId: 'visitor-123',
    );

    // Should not throw, just log the error
    $provider->trackPageView( $data );

    expect( $provider->getLastError() )->toBeNull();
} );

test( 'plausible provider includes referrer in page view', function (): void {
    Http::fake( [
        '*plausible.io/api/event*' => Http::response( 'ok', 202 ),
    ] );

    $provider = new PlausibleProvider;

    $data = new PageViewData(
        path: '/landing',
        visitorId: 'visitor-123',
        referrer: 'https://google.com/search?q=test',
    );

    $provider->trackPageView( $data );

    Http::assertSent( function ( $request ) {
        $body = $request->data();

        return isset( $body['referrer'] )
            && str_contains( $body['referrer'], 'google.com' );
    } );
} );

test( 'plausible provider includes revenue in event with value', function (): void {
    Http::fake( [
        '*plausible.io/api/event*' => Http::response( 'ok', 202 ),
    ] );

    $provider = new PlausibleProvider;

    $data = new EventData(
        name: 'purchase',
        visitorId: 'visitor-123',
        value: 99.99,
    );

    $provider->trackEvent( $data );

    Http::assertSent( function ( $request) {
        $body = $request->data();

        return isset( $body['revenue'])
            && 99.99 === $body['revenue']['amount'];
    });
});

test( 'plausible provider returns config array', function (): void {
    $provider = new PlausibleProvider;
    $config   = $provider->getConfig();

    expect( $config)->toBeArray();
    expect( $config)->toHaveKeys( ['enabled', 'domain', 'api_url', 'api_key']);
});
