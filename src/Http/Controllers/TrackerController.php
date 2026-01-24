<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

/**
 * Tracker script controller.
 *
 * Serves the JavaScript tracker file with configuration injected
 * from the server-side config.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Controllers
 */
class TrackerController extends Controller
{
	/**
	 * Serve the tracker script.
	 *
	 * GET /js/analytics.js
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function script(): Response
	{
		$content = $this->getTrackerScript( false );

		return $this->createJavaScriptResponse( $content );
	}

	/**
	 * Serve the minified tracker script.
	 *
	 * GET /js/analytics.min.js
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function minifiedScript(): Response
	{
		$content = $this->getTrackerScript( true );

		return $this->createJavaScriptResponse( $content );
	}

	/**
	 * Get the tracker script content.
	 *
	 * @param bool $minified Whether to get the minified version.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function getTrackerScript( bool $minified = false ): string
	{
		$filename = $minified ? 'tracker.min.js' : 'tracker.js';

		// Try package resources first
		$paths = [
			__DIR__ . '/../../../resources/js/' . $filename,
			resource_path( 'vendor/analytics/js/' . $filename ),
			public_path( 'vendor/analytics/js/' . $filename ),
		];

		foreach ( $paths as $path ) {
			if ( File::exists( $path ) ) {
				$script = File::get( $path );

				return $this->injectConfig( $script );
			}
		}

		// Return inline fallback script if files don't exist
		return $this->getInlineTrackerScript();
	}

	/**
	 * Inject server configuration into the tracker script.
	 *
	 * @param string $script The tracker script content.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function injectConfig( string $script ): string
	{
		$config = $this->getTrackerConfig();

		// Inject config at the beginning of the script
		$configJson = json_encode( $config, JSON_UNESCAPED_SLASHES );

		// Handle json_encode failure
		if ( false === $configJson ) {
			$errorMessage = json_last_error_msg();
			\Illuminate\Support\Facades\Log::warning( 'Failed to encode analytics tracker config', [
				'error' => $errorMessage,
			] );
			$configJson = '{}';
		}

		$configScript = "window.__ARTISANPACK_ANALYTICS_CONFIG__ = {$configJson};";

		return $configScript . "\n" . $script;
	}

	/**
	 * Get the tracker configuration from server config.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	protected function getTrackerConfig(): array
	{
		$routePrefix = config( 'artisanpack.analytics.route_prefix', 'api/analytics' );

		return [
			'endpoint'           => url( $routePrefix ),
			'sessionTimeout'     => config( 'artisanpack.analytics.session.timeout', 30 ) * 60 * 1000,
			'respectDNT'         => config( 'artisanpack.analytics.privacy.respect_dnt', true ),
			'consentRequired'    => config( 'artisanpack.analytics.privacy.consent_required', false ),
			'trackPageViews'     => true,
			'trackPerformance'   => true,
			'trackScrollDepth'   => true,
			'trackEngagement'    => true,
			'trackHashChanges'   => config( 'artisanpack.analytics.tracker.track_hash_changes', false ),
			'trackOutboundLinks' => config( 'artisanpack.analytics.tracker.track_outbound_links', true ),
			'trackFileDownloads' => config( 'artisanpack.analytics.tracker.track_file_downloads', true ),
			'downloadExtensions' => config( 'artisanpack.analytics.tracker.download_extensions', [
				'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
				'zip', 'rar', 'gz', 'tar', '7z',
				'exe', 'dmg', 'pkg', 'deb', 'rpm',
				'mp3', 'mp4', 'avi', 'mov', 'wmv',
			] ),
			'cookieLifetime'     => config( 'artisanpack.analytics.session.cookie_lifetime', 365 ),
			'sessionCookieName'  => config( 'artisanpack.analytics.session.cookie_name', '_ap_sid' ),
			'visitorCookieName'  => config( 'artisanpack.analytics.session.visitor_cookie_name', '_ap_vid' ),
			'batchSize'          => 10,
			'batchInterval'      => 5000,
			'debug'              => config( 'app.debug', false ),
		];
	}

	/**
	 * Get an inline tracker script as fallback.
	 *
	 * This is a minimal tracker that will be used if the main script
	 * files are not found.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function getInlineTrackerScript(): string
	{
		$config = json_encode( $this->getTrackerConfig(), JSON_UNESCAPED_SLASHES );

		return <<<JS
(function(window, document) {
    'use strict';

    var config = {$config};
    var visitorId = null;
    var sessionId = null;

    // Generate UUID
    function uuid() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // Cookie helpers
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + (value || '') + expires + '; path=/; SameSite=Lax' + secure;
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // Check DNT
    function shouldTrack() {
        if (config.respectDNT && (navigator.doNotTrack === '1' || navigator.globalPrivacyControl === true)) {
            return false;
        }
        return true;
    }

    // Initialize
    function init() {
        if (!shouldTrack()) return;

        visitorId = getCookie(config.visitorCookieName) || uuid();
        sessionId = getCookie(config.sessionCookieName) || uuid();

        setCookie(config.visitorCookieName, visitorId, config.cookieLifetime);
        setCookie(config.sessionCookieName, sessionId, 0);

        if (config.trackPageViews) {
            trackPageView();
        }
    }

    // Send data
    function send(endpoint, data) {
        data.visitor_id = visitorId;
        data.session_id = sessionId;

        var jsonData = JSON.stringify(data);
        if (navigator.sendBeacon) {
            var blob = new Blob([jsonData], { type: 'application/json' });
            navigator.sendBeacon(config.endpoint + '/' + endpoint, blob);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', config.endpoint + '/' + endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(jsonData);
        }
    }

    // Track page view
    function trackPageView(customData) {
        send('pageview', Object.assign({
            path: window.location.pathname,
            title: document.title,
            referrer: document.referrer,
            screen_width: window.screen.width,
            screen_height: window.screen.height,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            language: navigator.language,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        }, customData || {}));
    }

    // Track event
    function trackEvent(name, properties, options) {
        send('event', Object.assign({
            name: name,
            properties: properties || {},
            path: window.location.pathname
        }, options || {}));
    }

    // Public API
    window.ArtisanPackAnalytics = {
        version: '1.0.0',
        init: init,
        pageView: trackPageView,
        event: trackEvent,
        visitor: { getId: function() { return visitorId; } },
        session: { getId: function() { return sessionId; } },
        debug: function(enabled) { config.debug = enabled; }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
JS;
	}

	/**
	 * Create a JavaScript response with proper headers.
	 *
	 * @param string $content The JavaScript content.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	protected function createJavaScriptResponse( string $content ): Response
	{
		$etag = md5( $content );

		return response( $content )
			->header( 'Content-Type', 'application/javascript; charset=utf-8' )
			->header( 'Cache-Control', 'public, max-age=3600' )
			->header( 'ETag', '"' . $etag . '"' )
			->header( 'X-Content-Type-Options', 'nosniff' );
	}
}
