# Local Analytics Engine

**Purpose:** Define the JavaScript tracker, data collection, session management, and server-side processing
**Last Updated:** January 3, 2026

---

## Overview

The Local Analytics Engine is the core of the privacy-first analytics system. It consists of:

1. **JavaScript Tracker** - Client-side script that collects page views, events, and user interactions
2. **Session Manager** - Manages visitor sessions and identifies returning visitors
3. **Data Collector** - Server-side API that receives and processes tracking data
4. **Query Engine** - Optimized querying for dashboard and reporting

---

## JavaScript Tracker

### File Structure

```
resources/js/
├── analytics.js           # Main tracker (development)
├── analytics.min.js       # Minified production version
├── modules/
│   ├── config.js          # Configuration management
│   ├── session.js         # Session handling
│   ├── visitor.js         # Visitor identification
│   ├── tracker.js         # Page view and event tracking
│   ├── performance.js     # Performance metrics
│   ├── consent.js         # Consent management
│   └── transport.js       # Data transmission
└── utils/
    ├── storage.js         # Cookie/localStorage helpers
    ├── fingerprint.js     # Visitor fingerprinting
    └── debounce.js        # Utility functions
```

### Main Tracker Implementation

```javascript
// analytics.js

(function(window, document) {
    'use strict';

    const VERSION = '1.0.0';
    const STORAGE_PREFIX = 'ap_analytics_';

    // Default configuration
    const defaultConfig = {
        endpoint: '/api/analytics',
        sessionTimeout: 30 * 60 * 1000, // 30 minutes in ms
        heartbeatInterval: 15 * 1000,   // 15 seconds
        respectDNT: true,
        consentRequired: false,
        consentCategory: 'analytics',
        trackPageViews: true,
        trackPerformance: true,
        trackScrollDepth: true,
        trackEngagement: true,
        anonymizeIP: true,
        debug: false,
    };

    // State
    let config = { ...defaultConfig };
    let isInitialized = false;
    let hasConsent = false;
    let sessionId = null;
    let visitorId = null;
    let currentPageView = null;
    let pageEntryTime = null;
    let maxScrollDepth = 0;
    let engagedTime = 0;
    let lastActivityTime = null;
    let heartbeatTimer = null;

    // ==========================================
    // Storage Module
    // ==========================================

    const Storage = {
        set(key, value, days = 365) {
            const prefixedKey = STORAGE_PREFIX + key;

            // Try localStorage first, fallback to cookie
            try {
                localStorage.setItem(prefixedKey, JSON.stringify({
                    value,
                    expires: days ? Date.now() + (days * 24 * 60 * 60 * 1000) : null
                }));
            } catch (e) {
                // Fallback to cookie
                const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
                document.cookie = `${prefixedKey}=${encodeURIComponent(JSON.stringify(value))}; expires=${expires}; path=/; SameSite=Lax`;
            }
        },

        get(key) {
            const prefixedKey = STORAGE_PREFIX + key;

            // Try localStorage first
            try {
                const item = localStorage.getItem(prefixedKey);
                if (item) {
                    const parsed = JSON.parse(item);
                    if (!parsed.expires || parsed.expires > Date.now()) {
                        return parsed.value;
                    }
                    localStorage.removeItem(prefixedKey);
                }
            } catch (e) {}

            // Fallback to cookie
            const match = document.cookie.match(new RegExp('(^| )' + prefixedKey + '=([^;]+)'));
            if (match) {
                try {
                    return JSON.parse(decodeURIComponent(match[2]));
                } catch (e) {}
            }

            return null;
        },

        remove(key) {
            const prefixedKey = STORAGE_PREFIX + key;
            try {
                localStorage.removeItem(prefixedKey);
            } catch (e) {}
            document.cookie = `${prefixedKey}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
        }
    };

    // ==========================================
    // Fingerprint Module (Privacy-Preserving)
    // ==========================================

    const Fingerprint = {
        generate() {
            const components = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                screen.colorDepth,
                new Date().getTimezoneOffset(),
                !!window.sessionStorage,
                !!window.localStorage,
                navigator.hardwareConcurrency || 0,
            ];

            // Simple hash function
            let hash = 0;
            const str = components.join('|');
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash; // Convert to 32bit integer
            }

            return Math.abs(hash).toString(36);
        }
    };

    // ==========================================
    // Visitor Module
    // ==========================================

    const Visitor = {
        getId() {
            if (visitorId) return visitorId;

            visitorId = Storage.get('visitor_id');

            if (!visitorId) {
                visitorId = this.generateId();
                Storage.set('visitor_id', visitorId, config.cookieLifetime || 365);
            }

            return visitorId;
        },

        generateId() {
            const fingerprint = Fingerprint.generate();
            const random = Math.random().toString(36).substring(2, 10);
            const timestamp = Date.now().toString(36);
            return `${fingerprint}-${random}-${timestamp}`;
        },

        getData() {
            return {
                visitor_id: this.getId(),
                fingerprint: Fingerprint.generate(),
                screen_width: screen.width,
                screen_height: screen.height,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                device_pixel_ratio: window.devicePixelRatio || 1,
                language: navigator.language,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                user_agent: navigator.userAgent,
            };
        }
    };

    // ==========================================
    // Session Module
    // ==========================================

    const Session = {
        getId() {
            if (sessionId) return sessionId;

            const stored = Storage.get('session');

            if (stored && stored.id && stored.lastActivity) {
                const elapsed = Date.now() - stored.lastActivity;
                if (elapsed < config.sessionTimeout) {
                    sessionId = stored.id;
                    this.touch();
                    return sessionId;
                }
            }

            // Create new session
            return this.start();
        },

        start() {
            sessionId = this.generateId();

            Storage.set('session', {
                id: sessionId,
                startedAt: Date.now(),
                lastActivity: Date.now(),
                pageCount: 0,
            }, null); // Session cookie (no expiry = session only)

            // Notify server of new session
            this.notifyStart();

            return sessionId;
        },

        generateId() {
            return 'xxxx-xxxx-xxxx-xxxx'.replace(/x/g, () => {
                return Math.floor(Math.random() * 16).toString(16);
            });
        },

        touch() {
            const stored = Storage.get('session') || {};
            stored.lastActivity = Date.now();
            stored.pageCount = (stored.pageCount || 0) + 1;
            Storage.set('session', stored, null);
            lastActivityTime = Date.now();
        },

        async notifyStart() {
            const referrer = document.referrer;
            const urlParams = new URLSearchParams(window.location.search);

            await Transport.send('/session/start', {
                session_id: sessionId,
                visitor: Visitor.getData(),
                entry_page: window.location.pathname,
                referrer: referrer,
                referrer_domain: referrer ? new URL(referrer).hostname : null,
                utm_source: urlParams.get('utm_source'),
                utm_medium: urlParams.get('utm_medium'),
                utm_campaign: urlParams.get('utm_campaign'),
                utm_term: urlParams.get('utm_term'),
                utm_content: urlParams.get('utm_content'),
            });
        },

        async end() {
            if (!sessionId) return;

            await Transport.sendBeacon('/session/end', {
                session_id: sessionId,
                exit_page: window.location.pathname,
                duration: Date.now() - (Storage.get('session')?.startedAt || Date.now()),
            });
        },

        getData() {
            return {
                session_id: this.getId(),
                page_count: Storage.get('session')?.pageCount || 0,
            };
        }
    };

    // ==========================================
    // Performance Module
    // ==========================================

    const Performance = {
        getMetrics() {
            if (!config.trackPerformance || !window.performance) {
                return {};
            }

            const timing = performance.timing || {};
            const navigation = performance.getEntriesByType('navigation')[0] || {};

            return {
                load_time: navigation.loadEventEnd
                    ? Math.round(navigation.loadEventEnd - navigation.startTime)
                    : (timing.loadEventEnd - timing.navigationStart) || null,

                dom_ready_time: navigation.domContentLoadedEventEnd
                    ? Math.round(navigation.domContentLoadedEventEnd - navigation.startTime)
                    : (timing.domContentLoadedEventEnd - timing.navigationStart) || null,

                first_contentful_paint: this.getFCP(),
            };
        },

        getFCP() {
            try {
                const entries = performance.getEntriesByType('paint');
                const fcp = entries.find(e => e.name === 'first-contentful-paint');
                return fcp ? Math.round(fcp.startTime) : null;
            } catch (e) {
                return null;
            }
        }
    };

    // ==========================================
    // Consent Module
    // ==========================================

    const Consent = {
        check() {
            // If consent not required, always allow
            if (!config.consentRequired) {
                hasConsent = true;
                return true;
            }

            // Check for Do Not Track
            if (config.respectDNT && navigator.doNotTrack === '1') {
                hasConsent = false;
                return false;
            }

            // Check stored consent
            const consent = Storage.get('consent');
            if (consent && consent[config.consentCategory]) {
                hasConsent = true;
                return true;
            }

            hasConsent = false;
            return false;
        },

        grant(categories = ['analytics']) {
            const consent = Storage.get('consent') || {};
            categories.forEach(cat => consent[cat] = true);
            Storage.set('consent', consent, config.cookieLifetime || 365);

            if (categories.includes(config.consentCategory)) {
                hasConsent = true;

                // Send consent to server
                Transport.send('/consent', {
                    visitor_id: Visitor.getId(),
                    category: config.consentCategory,
                    granted: true,
                });

                // Start tracking if not already
                if (isInitialized && config.trackPageViews) {
                    Tracker.pageView();
                }
            }
        },

        revoke(categories = ['analytics']) {
            const consent = Storage.get('consent') || {};
            categories.forEach(cat => consent[cat] = false);
            Storage.set('consent', consent, config.cookieLifetime || 365);

            if (categories.includes(config.consentCategory)) {
                hasConsent = false;

                // Send revocation to server
                Transport.send('/consent', {
                    visitor_id: Visitor.getId(),
                    category: config.consentCategory,
                    granted: false,
                });
            }
        },

        hasCategory(category) {
            const consent = Storage.get('consent') || {};
            return consent[category] === true;
        }
    };

    // ==========================================
    // Transport Module
    // ==========================================

    const Transport = {
        queue: [],
        isOnline: navigator.onLine,

        async send(path, data) {
            if (!hasConsent && config.consentRequired) {
                if (config.debug) console.log('[Analytics] Tracking blocked - no consent');
                return;
            }

            const payload = {
                ...data,
                timestamp: new Date().toISOString(),
                url: window.location.href,
                path: window.location.pathname,
            };

            if (!this.isOnline) {
                this.queue.push({ path, payload });
                return;
            }

            try {
                const response = await fetch(config.endpoint + path, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                    keepalive: true,
                });

                if (!response.ok && config.debug) {
                    console.error('[Analytics] Failed to send:', response.status);
                }
            } catch (error) {
                if (config.debug) console.error('[Analytics] Error:', error);
                this.queue.push({ path, payload });
            }
        },

        sendBeacon(path, data) {
            if (!hasConsent && config.consentRequired) return false;

            const payload = {
                ...data,
                timestamp: new Date().toISOString(),
            };

            if (navigator.sendBeacon) {
                return navigator.sendBeacon(
                    config.endpoint + path,
                    new Blob([JSON.stringify(payload)], { type: 'application/json' })
                );
            }

            // Fallback to sync XHR for older browsers
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', config.endpoint + path, false);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(JSON.stringify(payload));
                return true;
            } catch (e) {
                return false;
            }
        },

        flushQueue() {
            if (!this.isOnline || this.queue.length === 0) return;

            const items = [...this.queue];
            this.queue = [];

            items.forEach(({ path, payload }) => {
                this.send(path, payload);
            });
        },

        init() {
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.flushQueue();
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
            });
        }
    };

    // ==========================================
    // Tracker Module
    // ==========================================

    const Tracker = {
        async pageView(customData = {}) {
            if (!hasConsent && config.consentRequired) return;

            // Record page entry time
            pageEntryTime = Date.now();
            maxScrollDepth = 0;
            engagedTime = 0;

            Session.touch();

            const data = {
                ...Visitor.getData(),
                ...Session.getData(),
                ...Performance.getMetrics(),
                title: document.title,
                referrer_path: currentPageView?.path || document.referrer,
                ...customData,
            };

            currentPageView = {
                path: window.location.pathname,
                startTime: pageEntryTime,
            };

            await Transport.send('/pageview', data);

            // Start engagement tracking
            this.startEngagementTracking();
        },

        async event(name, properties = {}, options = {}) {
            if (!hasConsent && config.consentRequired) return;

            const data = {
                ...Session.getData(),
                visitor_id: Visitor.getId(),
                name: name,
                category: options.category || null,
                action: options.action || null,
                label: options.label || null,
                properties: properties,
                value: options.value || null,
                page_path: window.location.pathname,
            };

            await Transport.send('/event', data);
        },

        startEngagementTracking() {
            // Track scroll depth
            if (config.trackScrollDepth) {
                const scrollHandler = debounce(() => {
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                    const docHeight = Math.max(
                        document.body.scrollHeight,
                        document.documentElement.scrollHeight
                    );
                    const winHeight = window.innerHeight;
                    const scrollPercent = Math.round((scrollTop / (docHeight - winHeight)) * 100);

                    if (scrollPercent > maxScrollDepth) {
                        maxScrollDepth = Math.min(scrollPercent, 100);
                    }
                }, 100);

                window.addEventListener('scroll', scrollHandler, { passive: true });
            }

            // Track engaged time (time when page is visible and user is active)
            if (config.trackEngagement) {
                let lastEngagedCheck = Date.now();
                let isEngaged = true;

                const checkEngagement = () => {
                    const now = Date.now();
                    if (isEngaged && !document.hidden) {
                        engagedTime += now - lastEngagedCheck;
                    }
                    lastEngagedCheck = now;
                };

                // Activity events
                const activityEvents = ['mousemove', 'keydown', 'scroll', 'touchstart'];
                activityEvents.forEach(event => {
                    document.addEventListener(event, debounce(() => {
                        isEngaged = true;
                        checkEngagement();
                    }, 1000), { passive: true });
                });

                // Idle timeout (10 seconds of no activity = not engaged)
                setInterval(() => {
                    if (Date.now() - lastActivityTime > 10000) {
                        isEngaged = false;
                    }
                    checkEngagement();
                }, 1000);

                // Visibility change
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        isEngaged = false;
                    }
                    checkEngagement();
                });
            }

            // Start heartbeat for time on page
            this.startHeartbeat();
        },

        startHeartbeat() {
            if (heartbeatTimer) {
                clearInterval(heartbeatTimer);
            }

            heartbeatTimer = setInterval(() => {
                if (!document.hidden) {
                    Session.touch();
                }
            }, config.heartbeatInterval);
        },

        async sendPageLeaveData() {
            if (!currentPageView) return;

            const timeOnPage = Date.now() - currentPageView.startTime;

            await Transport.sendBeacon('/pageview/update', {
                session_id: Session.getId(),
                path: currentPageView.path,
                time_on_page: Math.round(timeOnPage / 1000),
                engaged_time: Math.round(engagedTime / 1000),
                scroll_depth: maxScrollDepth,
            });
        }
    };

    // ==========================================
    // Utility Functions
    // ==========================================

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // ==========================================
    // SPA Support
    // ==========================================

    const SPASupport = {
        init() {
            // Handle History API navigation
            const originalPushState = history.pushState;
            const originalReplaceState = history.replaceState;

            history.pushState = function(...args) {
                Tracker.sendPageLeaveData();
                originalPushState.apply(this, args);
                setTimeout(() => Tracker.pageView(), 0);
            };

            history.replaceState = function(...args) {
                originalReplaceState.apply(this, args);
            };

            window.addEventListener('popstate', () => {
                Tracker.sendPageLeaveData();
                setTimeout(() => Tracker.pageView(), 0);
            });
        }
    };

    // ==========================================
    // Public API
    // ==========================================

    const ArtisanPackAnalytics = {
        version: VERSION,

        init(userConfig = {}) {
            if (isInitialized) {
                if (config.debug) console.warn('[Analytics] Already initialized');
                return this;
            }

            // Merge config
            config = { ...defaultConfig, ...userConfig };

            // Check consent
            Consent.check();

            // Initialize transport
            Transport.init();

            // Initialize SPA support
            SPASupport.init();

            isInitialized = true;

            // Track initial page view
            if (config.trackPageViews && hasConsent) {
                // Wait for DOM and performance metrics
                if (document.readyState === 'complete') {
                    Tracker.pageView();
                } else {
                    window.addEventListener('load', () => Tracker.pageView());
                }
            }

            // Handle page unload
            window.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    Tracker.sendPageLeaveData();
                }
            });

            window.addEventListener('pagehide', () => {
                Tracker.sendPageLeaveData();
                Session.end();
            });

            if (config.debug) {
                console.log('[Analytics] Initialized', { config, hasConsent });
            }

            return this;
        },

        // Page view tracking
        pageView(data = {}) {
            return Tracker.pageView(data);
        },

        // Event tracking
        event(name, properties = {}, options = {}) {
            return Tracker.event(name, properties, options);
        },

        // Shorthand for common events
        track: {
            click(element, properties = {}) {
                return Tracker.event('click', {
                    element: element,
                    ...properties
                });
            },

            formSubmit(formName, properties = {}) {
                return Tracker.event('form_submitted', {
                    form_name: formName,
                    ...properties
                }, { category: 'forms' });
            },

            purchase(orderId, total, properties = {}) {
                return Tracker.event('purchase', {
                    order_id: orderId,
                    total: total,
                    ...properties
                }, { category: 'ecommerce', value: total });
            },

            addToCart(productId, properties = {}) {
                return Tracker.event('add_to_cart', {
                    product_id: productId,
                    ...properties
                }, { category: 'ecommerce' });
            },

            booking(serviceId, properties = {}) {
                return Tracker.event('booking_created', {
                    service_id: serviceId,
                    ...properties
                }, { category: 'booking' });
            }
        },

        // Consent management
        consent: {
            grant(categories) {
                return Consent.grant(categories);
            },

            revoke(categories) {
                return Consent.revoke(categories);
            },

            hasConsent(category = 'analytics') {
                return Consent.hasCategory(category);
            },

            check() {
                return Consent.check();
            }
        },

        // Session management
        session: {
            getId() {
                return Session.getId();
            },

            end() {
                return Session.end();
            }
        },

        // Visitor management
        visitor: {
            getId() {
                return Visitor.getId();
            }
        },

        // Debugging
        debug(enabled = true) {
            config.debug = enabled;
            return this;
        }
    };

    // Expose globally
    window.ArtisanPackAnalytics = ArtisanPackAnalytics;

    // Auto-initialize if config is present
    if (window.AP_ANALYTICS_CONFIG) {
        ArtisanPackAnalytics.init(window.AP_ANALYTICS_CONFIG);
    }

})(window, document);
```

---

## Blade Component for Script Inclusion

```blade
{{-- resources/views/components/tracker.blade.php --}}

@props([
    'config' => [],
])

@php
    $defaultConfig = [
        'endpoint' => url('/api/analytics'),
        'sessionTimeout' => config('analytics.local.session_lifetime', 30) * 60 * 1000,
        'respectDNT' => config('analytics.local.respect_dnt', true),
        'consentRequired' => config('analytics.privacy.require_consent', false),
        'trackPageViews' => true,
        'trackPerformance' => true,
        'trackScrollDepth' => true,
        'trackEngagement' => true,
        'debug' => config('app.debug', false),
    ];

    $mergedConfig = array_merge($defaultConfig, $config);
@endphp

<script>
    window.AP_ANALYTICS_CONFIG = @json($mergedConfig);
</script>
<script src="{{ asset('vendor/analytics/analytics.min.js') }}" defer></script>
```

**Usage in Layout:**

```blade
<!DOCTYPE html>
<html>
<head>
    {{-- Other head elements --}}

    {{-- Analytics tracker - privacy-respecting --}}
    <x-analytics-tracker />

    {{-- Or with custom config --}}
    <x-analytics-tracker :config="['consentRequired' => true]" />
</head>
<body>
    {{-- Content --}}
</body>
</html>
```

---

## Server-Side Data Collection

### API Controller

```php
// src/Http/Controllers/AnalyticsController.php

namespace ArtisanPackUI\Analytics\Http\Controllers;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analytics
    ) {}

    public function pageview(Request $request): Response
    {
        $validated = $request->validate([
            'visitor_id' => 'required|string|max:100',
            'session_id' => 'required|string|max:36',
            'fingerprint' => 'required|string|max:64',
            'path' => 'required|string|max:2048',
            'title' => 'nullable|string|max:500',
            'referrer_path' => 'nullable|string|max:2048',
            'screen_width' => 'nullable|integer|min:0|max:10000',
            'screen_height' => 'nullable|integer|min:0|max:10000',
            'viewport_width' => 'nullable|integer|min:0|max:10000',
            'viewport_height' => 'nullable|integer|min:0|max:10000',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'user_agent' => 'nullable|string|max:500',
            'load_time' => 'nullable|integer|min:0',
            'dom_ready_time' => 'nullable|integer|min:0',
            'first_contentful_paint' => 'nullable|integer|min:0',
        ]);

        $data = PageViewData::from([
            ...$validated,
            'ip_address' => $request->ip(),
        ]);

        $this->analytics->trackPageView($data);

        return response()->noContent();
    }

    public function updatePageview(Request $request): Response
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:36',
            'path' => 'required|string|max:2048',
            'time_on_page' => 'nullable|integer|min:0',
            'engaged_time' => 'nullable|integer|min:0',
            'scroll_depth' => 'nullable|integer|min:0|max:100',
        ]);

        $this->analytics->updatePageView($validated);

        return response()->noContent();
    }

    public function event(Request $request): Response
    {
        $validated = $request->validate([
            'visitor_id' => 'required|string|max:100',
            'session_id' => 'nullable|string|max:36',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'action' => 'nullable|string|max:100',
            'label' => 'nullable|string|max:255',
            'properties' => 'nullable|array',
            'value' => 'nullable|numeric',
            'page_path' => 'nullable|string|max:2048',
        ]);

        $data = EventData::from($validated);

        $this->analytics->trackEvent($data);

        return response()->noContent();
    }

    public function startSession(Request $request): Response
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:36',
            'visitor' => 'required|array',
            'visitor.visitor_id' => 'required|string|max:100',
            'visitor.fingerprint' => 'required|string|max:64',
            'entry_page' => 'required|string|max:2048',
            'referrer' => 'nullable|string|max:2048',
            'referrer_domain' => 'nullable|string|max:255',
            'utm_source' => 'nullable|string|max:255',
            'utm_medium' => 'nullable|string|max:255',
            'utm_campaign' => 'nullable|string|max:255',
            'utm_term' => 'nullable|string|max:255',
            'utm_content' => 'nullable|string|max:255',
        ]);

        $data = SessionData::from([
            ...$validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->analytics->startSession($data);

        return response()->noContent();
    }

    public function endSession(Request $request): Response
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:36',
            'exit_page' => 'nullable|string|max:2048',
            'duration' => 'nullable|integer|min:0',
        ]);

        $this->analytics->endSession($validated['session_id'], $validated);

        return response()->noContent();
    }
}
```

### Data Transfer Objects

```php
// src/Data/PageViewData.php

namespace ArtisanPackUI\Analytics\Data;

use Spatie\LaravelData\Data;

class PageViewData extends Data
{
    public function __construct(
        public string $visitor_id,
        public string $session_id,
        public string $fingerprint,
        public string $path,
        public ?string $title = null,
        public ?string $referrer_path = null,
        public ?string $ip_address = null,
        public ?string $user_agent = null,
        public ?int $screen_width = null,
        public ?int $screen_height = null,
        public ?int $viewport_width = null,
        public ?int $viewport_height = null,
        public ?string $language = null,
        public ?string $timezone = null,
        public ?int $load_time = null,
        public ?int $dom_ready_time = null,
        public ?int $first_contentful_paint = null,
    ) {}
}
```

```php
// src/Data/EventData.php

namespace ArtisanPackUI\Analytics\Data;

use Spatie\LaravelData\Data;

class EventData extends Data
{
    public function __construct(
        public string $visitor_id,
        public string $name,
        public ?string $session_id = null,
        public ?string $category = null,
        public ?string $action = null,
        public ?string $label = null,
        public ?array $properties = null,
        public ?float $value = null,
        public ?string $page_path = null,
        public ?string $source_package = null,
    ) {}
}
```

```php
// src/Data/SessionData.php

namespace ArtisanPackUI\Analytics\Data;

use Spatie\LaravelData\Data;

class SessionData extends Data
{
    public function __construct(
        public string $session_id,
        public array $visitor,
        public string $entry_page,
        public ?string $ip_address = null,
        public ?string $user_agent = null,
        public ?string $referrer = null,
        public ?string $referrer_domain = null,
        public ?string $utm_source = null,
        public ?string $utm_medium = null,
        public ?string $utm_campaign = null,
        public ?string $utm_term = null,
        public ?string $utm_content = null,
    ) {}
}
```

### Analytics Service

```php
// src/Services/AnalyticsService.php

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Contracts\AnalyticsServiceInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Jobs\ProcessEvent;
use ArtisanPackUI\Analytics\Jobs\ProcessPageView;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;

class AnalyticsService implements AnalyticsServiceInterface
{
    public function __construct(
        protected VisitorResolver $visitorResolver,
        protected SessionManager $sessionManager,
        protected AnalyticsManager $providers,
        protected GoalMatcher $goalMatcher,
    ) {}

    public function trackPageView(PageViewData $data): void
    {
        // Queue for async processing
        if (config('analytics.queue_tracking', true)) {
            ProcessPageView::dispatch($data);
            return;
        }

        $this->processPageView($data);
    }

    public function processPageView(PageViewData $data): void
    {
        // Resolve or create visitor
        $visitor = $this->visitorResolver->resolve($data);

        // Get or create session
        $session = $this->sessionManager->getOrCreate($data->session_id, $visitor);

        // Create page view record
        $pageView = PageView::create([
            'site_id' => $this->getSiteId(),
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            'path' => $data->path,
            'title' => $data->title,
            'referrer_path' => $data->referrer_path,
            'load_time' => $data->load_time,
            'dom_ready_time' => $data->dom_ready_time,
            'first_contentful_paint' => $data->first_contentful_paint,
        ]);

        // Update visitor stats
        $visitor->increment('total_pageviews');
        $visitor->update(['last_seen_at' => now()]);

        // Update session
        $session->update([
            'page_count' => $session->page_count + 1,
            'last_activity_at' => now(),
            'is_bounce' => $session->page_count === 0, // Will be false after this
        ]);

        // Check for pageview-based goals
        $this->goalMatcher->matchPageView($pageView, $session, $visitor);

        // Send to external providers
        $this->providers->trackPageView($data);
    }

    public function updatePageView(array $data): void
    {
        $pageView = PageView::query()
            ->whereHas('session', fn($q) => $q->where('session_id', $data['session_id']))
            ->where('path', $data['path'])
            ->latest()
            ->first();

        if ($pageView) {
            $pageView->update([
                'time_on_page' => $data['time_on_page'] ?? null,
                'engaged_time' => $data['engaged_time'] ?? null,
                'scroll_depth' => $data['scroll_depth'] ?? null,
            ]);
        }
    }

    public function trackEvent(EventData $data): void
    {
        if (config('analytics.queue_tracking', true)) {
            ProcessEvent::dispatch($data);
            return;
        }

        $this->processEvent($data);
    }

    public function processEvent(EventData $data): void
    {
        // Find visitor
        $visitor = Visitor::where('visitor_id', $data->visitor_id)->first();

        // Find session
        $session = $data->session_id
            ? Session::where('session_id', $data->session_id)->first()
            : null;

        // Create event
        $event = Event::create([
            'site_id' => $this->getSiteId(),
            'session_id' => $session?->id,
            'visitor_id' => $visitor?->id,
            'name' => $data->name,
            'category' => $data->category,
            'action' => $data->action,
            'label' => $data->label,
            'properties' => $data->properties,
            'value' => $data->value,
            'source_package' => $data->source_package,
        ]);

        // Update visitor stats
        if ($visitor) {
            $visitor->increment('total_events');
        }

        // Check for event-based goals
        $this->goalMatcher->matchEvent($event, $session, $visitor);

        // Send to external providers
        $this->providers->trackEvent($data);
    }

    public function startSession(SessionData $data): Session
    {
        // Resolve visitor
        $visitorData = VisitorData::from([
            ...$data->visitor,
            'ip_address' => $data->ip_address,
            'user_agent' => $data->user_agent,
        ]);

        $visitor = $this->visitorResolver->resolve($visitorData);

        // Determine referrer type
        $referrerType = $this->determineReferrerType($data);

        // Create session
        $session = Session::create([
            'site_id' => $this->getSiteId(),
            'visitor_id' => $visitor->id,
            'session_id' => $data->session_id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'entry_page' => $data->entry_page,
            'referrer' => $data->referrer,
            'referrer_domain' => $data->referrer_domain,
            'referrer_type' => $referrerType,
            'utm_source' => $data->utm_source,
            'utm_medium' => $data->utm_medium,
            'utm_campaign' => $data->utm_campaign,
            'utm_term' => $data->utm_term,
            'utm_content' => $data->utm_content,
        ]);

        // Update visitor stats
        $visitor->increment('total_sessions');

        return $session;
    }

    public function endSession(string $sessionId, array $data = []): void
    {
        $session = Session::where('session_id', $sessionId)->first();

        if (!$session) {
            return;
        }

        $session->update([
            'ended_at' => now(),
            'exit_page' => $data['exit_page'] ?? $session->entry_page,
            'duration' => now()->diffInSeconds($session->started_at),
        ]);

        // Check for session-based goals (duration, pages per session)
        $this->goalMatcher->matchSession($session);
    }

    protected function determineReferrerType(SessionData $data): string
    {
        if (!$data->referrer) {
            return 'direct';
        }

        // Check UTM parameters
        if ($data->utm_medium === 'cpc' || $data->utm_medium === 'ppc') {
            return 'paid';
        }

        if ($data->utm_medium === 'email') {
            return 'email';
        }

        // Check domain
        $domain = $data->referrer_domain;

        if (!$domain) {
            return 'direct';
        }

        // Search engines
        $searchEngines = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex'];
        foreach ($searchEngines as $engine) {
            if (str_contains($domain, $engine)) {
                return 'organic';
            }
        }

        // Social networks
        $socialNetworks = ['facebook', 'twitter', 'linkedin', 'instagram', 'pinterest', 'tiktok', 'youtube'];
        foreach ($socialNetworks as $network) {
            if (str_contains($domain, $network)) {
                return 'social';
            }
        }

        return 'referral';
    }

    protected function getSiteId(): ?int
    {
        if (!config('analytics.multi_tenant.enabled')) {
            return null;
        }

        return app('analytics.tenant')?->id;
    }
}
```

---

## Rate Limiting

```php
// src/Http/Middleware/AnalyticsThrottle.php

namespace ArtisanPackUI\Analytics\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

class AnalyticsThrottle
{
    public function __construct(
        protected RateLimiter $limiter
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $key = 'analytics:' . $request->ip();

        // Allow 100 requests per minute per IP
        if ($this->limiter->tooManyAttempts($key, 100)) {
            return response()->json([
                'error' => __('Too many requests'),
            ], 429);
        }

        $this->limiter->hit($key, 60);

        return $next($request);
    }
}
```

---

## Queued Processing

```php
// src/Jobs/ProcessPageView.php

namespace ArtisanPackUI\Analytics\Jobs;

use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Services\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPageView implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public PageViewData $data
    ) {
        $this->onQueue('analytics');
    }

    public function handle(AnalyticsService $analytics): void
    {
        $analytics->processPageView($this->data);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(5);
    }
}
```

---

## Related Documents

- [01-architecture.md](./01-architecture.md) - Overall architecture
- [02-database-schema.md](./02-database-schema.md) - Database tables
- [04-provider-interface.md](./04-provider-interface.md) - External providers
- [06-event-tracking.md](./06-event-tracking.md) - Event system details

---

*Last Updated: January 3, 2026*
