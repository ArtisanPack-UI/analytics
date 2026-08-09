{{--
    Analytics Tracker Script Component

    Usage:
    <x-artisanpack-analytics::tracker-script />

    With custom config:
    <x-artisanpack-analytics::tracker-script :debug="true" />

    Overriding individual tracker settings for one page:
    <x-artisanpack-analytics::tracker-script :config="[ 'anonymousMode' => true ]" />

    @since 1.0.0
    @since 1.5.0 Added the config prop.
--}}

@props([
    'async' => true,
    'defer' => false,
    'minified' => true,
    'debug' => false,
    'config' => [],
])

@php
    $scriptPath = $minified
        ? route( 'analytics.tracker.script.min' )
        : route( 'analytics.tracker.script' );

    $trackerConfig = is_array( $config ) ? $config : [];

    $attributes = [];

    if ( $async ) {
        $attributes[] = 'async';
    }

    if ( $defer ) {
        $attributes[] = 'defer';
    }
@endphp

@if ( config( 'artisanpack.analytics.enabled', true ) )
    @if ( $debug )
        <script>
            window.__ARTISANPACK_ANALYTICS_DEBUG__ = true;
        </script>
    @endif
    {{--
        Page-level tracker overrides. Emitted before the tracker so the served
        script's own config merges onto them rather than replacing them, which
        is the same override route the documentation describes for setting the
        global by hand.
    --}}
    @if ( [] !== $trackerConfig )
        <script>
            window.__ARTISANPACK_ANALYTICS_CONFIG__ = Object.assign(
                window.__ARTISANPACK_ANALYTICS_CONFIG__ || {},
                {!! json_encode( $trackerConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) !!}
            );
        </script>
    @endif
    <script src="{{ $scriptPath }}" {{ implode( ' ', $attributes ) }}></script>

    {{--
        Client-side snippets contributed by active providers (see
        ProvidesTrackerScript). Emitted unescaped because they are script
        markup; each provider is responsible for encoding anything it
        interpolates. Without this, a provider whose tracking half runs in
        the browser registers successfully and then contributes nothing.
    --}}
    @foreach ( \ArtisanPackUI\Analytics\Facades\Analytics::trackerScripts() as $providerScript )
        {!! $providerScript !!}
    @endforeach
@endif
