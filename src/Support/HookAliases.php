<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Support;

/**
 * Registers backwards-compat aliases for renamed hooks.
 *
 * @since 1.4.0
 */
class HookAliases
{
    /**
     * Register deprecation aliases for renamed analytics hooks.
     *
     * Aliases keep existing subscribers firing (with an info log) after
     * a hook rename. Removed in the next major version.
     *
     * @since 1.4.0
     */
    public static function register(): void
    {
        if ( ! function_exists( 'deprecateHook' ) ) {
            return;
        }

        deprecateHook( 'ap.analytics.site_selector.query', 'ap.analytics.siteSelector.query' );
    }
}
