<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Support\HookAliases;

test( 'both old and new site selector hook names fire subscribers', function (): void {
    HookAliases::register();

    $newCalled = false;
    $oldCalled = false;

    addFilter( 'ap.analytics.siteSelector.query', function ( $query ) use ( &$newCalled ) {
        $newCalled = true;
        return $query . ':new';
    }, 10 );

    addFilter( 'ap.analytics.site_selector.query', function ( $query ) use ( &$oldCalled ) {
        $oldCalled = true;
        return $query . ':old';
    }, 20 );

    $result = applyFilters( 'ap.analytics.siteSelector.query', 'base' );

    expect( $newCalled )->toBeTrue();
    expect( $oldCalled )->toBeTrue();
    expect( $result )->toContain( ':new' )->toContain( ':old' );
} );
