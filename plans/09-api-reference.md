# API Reference

This document provides a complete reference for all public APIs, facades, helpers, configuration options, Blade directives, Artisan commands, and event hooks.

## Table of Contents

1. [Facades](#facades)
2. [Helper Functions](#helper-functions)
3. [Blade Directives](#blade-directives)
4. [Configuration](#configuration)
5. [Artisan Commands](#artisan-commands)
6. [Event Hooks](#event-hooks)
7. [JavaScript API](#javascript-api)
8. [REST API Endpoints](#rest-api-endpoints)

---

## Facades

### Analytics Facade

The main facade for interacting with the analytics system.

```php
use ArtisanPackUI\Analytics\Facades\Analytics;
```

#### Tracking Methods

```php
// Track a page view
Analytics::trackPageView(
    url: '/products/widget',
    title: 'Widget Product Page',
    referrer: 'https://google.com',
    metadata: ['category' => 'products']
);

// Track an event
Analytics::trackEvent(
    name: 'button_click',
    category: 'engagement',
    properties: ['button_id' => 'cta-hero', 'page' => '/home']
);

// Track a form submission
Analytics::trackFormSubmission(
    formId: 'contact-form',
    formName: 'Contact Us',
    fields: ['name', 'email', 'message'],
    success: true
);

// Track an ecommerce event
Analytics::trackPurchase(
    orderId: 'ORD-12345',
    total: 99.99,
    currency: 'USD',
    items: [
        ['id' => 'PROD-1', 'name' => 'Widget', 'price' => 49.99, 'quantity' => 2],
    ]
);

// Track a custom conversion
Analytics::trackConversion(
    goalId: 1,
    value: 150.00,
    metadata: ['source' => 'email_campaign']
);
```

#### Query Methods

```php
// Get dashboard statistics
$stats = Analytics::getStats(period: '7d');
// Returns: ['visitors' => 1234, 'sessions' => 2345, 'page_views' => 5678, ...]

// Get page view data
$pageViews = Analytics::getPageViews(
    period: '30d',
    groupBy: 'day'
);

// Get top pages
$topPages = Analytics::getTopPages(
    period: '7d',
    limit: 10
);

// Get traffic sources
$sources = Analytics::getTrafficSources(period: '7d');

// Get visitor data
$visitors = Analytics::getVisitors(
    period: '30d',
    returning: false // Only new visitors
);

// Get event data
$events = Analytics::getEvents(
    period: '7d',
    category: 'engagement'
);

// Get goal conversions
$conversions = Analytics::getConversions(
    goalId: 1,
    period: '30d'
);

// Get real-time data
$realtime = Analytics::getRealtime();
// Returns: ['active_visitors' => 42, 'pages' => [...], 'sources' => [...]]
```

#### Configuration Methods

```php
// Check if tracking is enabled
$enabled = Analytics::isEnabled();

// Temporarily disable tracking
Analytics::disable();

// Re-enable tracking
Analytics::enable();

// Get the current provider
$provider = Analytics::getProvider();

// Switch providers
Analytics::setProvider('google_analytics');

// Check consent status
$hasConsent = Analytics::hasConsent();
```

---

### AnalyticsQuery Facade

A dedicated facade for building complex analytics queries.

```php
use ArtisanPackUI\Analytics\Facades\AnalyticsQuery;
```

#### Building Queries

```php
// Basic stats query
$stats = AnalyticsQuery::stats()
    ->period('30d')
    ->get();

// Page views with filtering
$pageViews = AnalyticsQuery::pageViews()
    ->period('7d')
    ->where('path', 'like', '/blog%')
    ->groupBy('path')
    ->orderBy('count', 'desc')
    ->limit(20)
    ->get();

// Visitors with segments
$visitors = AnalyticsQuery::visitors()
    ->period('30d')
    ->returning(true)
    ->fromCountry('US')
    ->withDevice('mobile')
    ->get();

// Events query
$events = AnalyticsQuery::events()
    ->period('7d')
    ->category('ecommerce')
    ->name('purchase')
    ->get();

// Sessions with details
$sessions = AnalyticsQuery::sessions()
    ->period('24h')
    ->withPageViews()
    ->withEvents()
    ->where('duration', '>', 60)
    ->get();

// Goal conversions
$conversions = AnalyticsQuery::conversions()
    ->period('30d')
    ->goalId(1)
    ->withFunnel()
    ->get();

// Aggregated metrics
$metrics = AnalyticsQuery::aggregate()
    ->metrics(['visitors', 'sessions', 'page_views', 'bounce_rate'])
    ->period('30d')
    ->groupBy('day')
    ->get();

// Compare periods
$comparison = AnalyticsQuery::compare()
    ->metric('visitors')
    ->currentPeriod('7d')
    ->previousPeriod('7d')
    ->get();
// Returns: ['current' => 1234, 'previous' => 1100, 'change' => 12.18, 'trend' => 'up']
```

#### Raw Query Access

```php
// Get the underlying query builder
$query = AnalyticsQuery::pageViews()
    ->period('7d')
    ->toQuery();

// Execute custom SQL
$results = AnalyticsQuery::raw("
    SELECT DATE(viewed_at) as date, COUNT(*) as views
    FROM analytics_page_views
    WHERE viewed_at >= ?
    GROUP BY DATE(viewed_at)
", [now()->subDays(7)]);
```

---

### Goal Facade

Manage analytics goals and conversions.

```php
use ArtisanPackUI\Analytics\Facades\Goal;
```

#### Goal Management

```php
// Create a goal
$goal = Goal::create([
    'name' => 'Newsletter Signup',
    'type' => 'event',
    'match_conditions' => [
        'event_name' => 'form_submitted',
        'event_category' => 'newsletter',
    ],
    'value' => 5.00,
]);

// Update a goal
Goal::update($goalId, [
    'name' => 'Newsletter Signup (Updated)',
    'value' => 10.00,
]);

// Delete a goal
Goal::delete($goalId);

// Get all goals
$goals = Goal::all();

// Get goal by ID
$goal = Goal::find($goalId);

// Check if an event matches a goal
$matches = Goal::matches($event);
// Returns: Collection of matching goals

// Manually trigger a conversion
Goal::convert($goalId, [
    'value' => 25.00,
    'visitor_id' => $visitorId,
    'session_id' => $sessionId,
]);
```

---

### Consent Facade

Manage user consent for analytics tracking.

```php
use ArtisanPackUI\Analytics\Facades\Consent;
```

#### Consent Management

```php
// Check if consent is required
$required = Consent::isRequired();

// Check current consent status
$hasConsent = Consent::hasConsent();
$hasConsent = Consent::hasConsent('analytics'); // Specific type

// Grant consent
Consent::grant(['analytics', 'marketing']);

// Revoke consent
Consent::revoke(['marketing']);
Consent::revokeAll();

// Get consent details
$consent = Consent::getConsent();
// Returns: ['analytics' => true, 'marketing' => false, 'granted_at' => '...']

// Check consent from request
$hasConsent = Consent::checkFromRequest($request);

// Set consent cookie
Consent::setCookie(['analytics' => true]);

// Clear consent cookie
Consent::clearCookie();
```

---

## Helper Functions

Global helper functions for common analytics operations.

### Tracking Helpers

```php
// Track a page view
analyticsTrackPageView('/products', 'Products Page');

// Track an event
analyticsTrackEvent('click', 'engagement', ['element' => 'cta']);

// Track a form submission
analyticsTrackForm('contact-form', 'Contact Form', true);

// Track a purchase
analyticsTrackPurchase('ORD-123', 99.99, 'USD', $items);

// Track a conversion
analyticsTrackConversion($goalId, 50.00);
```

### Query Helpers

```php
// Get quick stats
$stats = analyticsStats('7d');

// Get page view count
$views = analyticsPageViews('30d');

// Get visitor count
$visitors = analyticsVisitors('7d');

// Get top pages
$pages = analyticsTopPages(10, '7d');

// Get traffic sources
$sources = analyticsTrafficSources('7d');

// Check if analytics is enabled
$enabled = analyticsEnabled();

// Get current site (multi-tenant)
$site = analyticsSite();
```

### Consent Helpers

```php
// Check consent
$hasConsent = analyticsHasConsent();
$hasConsent = analyticsHasConsent('marketing');

// Grant consent
analyticsGrantConsent(['analytics', 'marketing']);

// Revoke consent
analyticsRevokeConsent(['marketing']);
```

---

## Blade Directives

### Tracking Script

```blade
{{-- Include the analytics tracking script --}}
@analyticsScripts

{{-- With custom configuration --}}
@analyticsScripts(['debug' => true, 'respectDNT' => true])
```

### Consent Banner

```blade
{{-- Include the consent banner --}}
@analyticsConsentBanner

{{-- Customized consent banner --}}
@analyticsConsentBanner([
    'position' => 'bottom',
    'theme' => 'dark',
    'privacyUrl' => '/privacy-policy',
])
```

### Conditional Tracking

```blade
{{-- Only render if consent granted --}}
@analyticsConsent
    <script>
        // Marketing scripts here
    </script>
@endanalyticsConsent

{{-- Check specific consent type --}}
@analyticsConsent('marketing')
    <!-- Marketing pixels -->
@endanalyticsConsent
```

### Event Tracking

```blade
{{-- Track a page view with custom data --}}
@analyticsPageView(['category' => 'blog', 'author' => 'John'])

{{-- Track an event --}}
@analyticsEvent('page_loaded', 'lifecycle', ['load_time' => 1.23])
```

### Dashboard Widgets

```blade
{{-- Embed the full analytics dashboard --}}
@analyticsDashboard

{{-- Embed specific widgets --}}
@analyticsWidget('stats-cards', ['period' => '7d'])
@analyticsWidget('visitors-chart', ['period' => '30d'])
@analyticsWidget('top-pages', ['limit' => 5])
@analyticsWidget('traffic-sources')

{{-- Page-level analytics (for visual editor) --}}
@analyticsPageStats('/products/widget')
```

---

## Configuration

### Full Configuration File

```php
<?php

// config/analytics.php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for enabling or disabling analytics tracking.
    |
    */
    'enabled' => env('ANALYTICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The default analytics provider to use. Supported: "local", "google_analytics", "plausible"
    |
    */
    'default' => env('ANALYTICS_PROVIDER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each analytics provider.
    |
    */
    'providers' => [
        'local' => [
            'driver' => 'local',
            'connection' => env('ANALYTICS_DB_CONNECTION', null), // null = default connection
            'queue' => env('ANALYTICS_QUEUE', 'analytics'),
            'batch_size' => env('ANALYTICS_BATCH_SIZE', 100),
        ],

        'google_analytics' => [
            'driver' => 'google_analytics',
            'measurement_id' => env('GOOGLE_ANALYTICS_MEASUREMENT_ID'),
            'api_secret' => env('GOOGLE_ANALYTICS_API_SECRET'),
            'debug' => env('GOOGLE_ANALYTICS_DEBUG', false),
        ],

        'plausible' => [
            'driver' => 'plausible',
            'domain' => env('PLAUSIBLE_DOMAIN'),
            'api_url' => env('PLAUSIBLE_API_URL', 'https://plausible.io'),
            'api_key' => env('PLAUSIBLE_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the JavaScript tracker and server-side tracking.
    |
    */
    'tracking' => [
        // Anonymize visitor IP addresses (recommended for GDPR)
        'anonymize_ip' => env('ANALYTICS_ANONYMIZE_IP', true),

        // Track bot visits
        'track_bots' => env('ANALYTICS_TRACK_BOTS', false),

        // Track logged-in users
        'track_authenticated' => env('ANALYTICS_TRACK_AUTHENTICATED', true),

        // Respect Do Not Track header
        'respect_dnt' => env('ANALYTICS_RESPECT_DNT', true),

        // Session timeout in minutes
        'session_timeout' => env('ANALYTICS_SESSION_TIMEOUT', 30),

        // Visitor cookie lifetime in days
        'cookie_lifetime' => env('ANALYTICS_COOKIE_LIFETIME', 365),

        // Cookie name
        'cookie_name' => env('ANALYTICS_COOKIE_NAME', 'ap_analytics'),

        // Excluded IP addresses
        'excluded_ips' => array_filter(explode(',', env('ANALYTICS_EXCLUDED_IPS', ''))),

        // Excluded paths (regex patterns)
        'excluded_paths' => [
            '/admin/*',
            '/api/*',
            '/_debugbar/*',
        ],

        // Excluded user agents (regex patterns)
        'excluded_user_agents' => [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy & Consent
    |--------------------------------------------------------------------------
    |
    | Privacy-related settings and consent configuration.
    |
    */
    'privacy' => [
        // Require consent before tracking
        'consent_required' => env('ANALYTICS_CONSENT_REQUIRED', true),

        // Consent types available
        'consent_types' => ['analytics', 'marketing', 'personalization'],

        // Default consent (if not explicitly set)
        'default_consent' => [],

        // Show consent banner automatically
        'show_consent_banner' => env('ANALYTICS_SHOW_CONSENT_BANNER', true),

        // Consent banner position: 'top', 'bottom'
        'consent_banner_position' => 'bottom',

        // Link to privacy policy
        'privacy_policy_url' => '/privacy-policy',

        // Data retention period in days (0 = forever)
        'data_retention_days' => env('ANALYTICS_DATA_RETENTION', 730),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the analytics dashboard and widgets.
    |
    */
    'dashboard' => [
        // Route prefix for dashboard
        'route_prefix' => 'analytics',

        // Middleware for dashboard routes
        'middleware' => ['web', 'auth'],

        // Default time period
        'default_period' => '7d',

        // Available time periods
        'periods' => [
            '24h' => __('Last 24 Hours'),
            '7d' => __('Last 7 Days'),
            '30d' => __('Last 30 Days'),
            '90d' => __('Last 90 Days'),
            '365d' => __('Last Year'),
        ],

        // Real-time update interval (seconds)
        'realtime_interval' => 30,

        // Cache dashboard queries (minutes)
        'cache_ttl' => env('ANALYTICS_DASHBOARD_CACHE', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events & Goals
    |--------------------------------------------------------------------------
    |
    | Configuration for event tracking and goal conversions.
    |
    */
    'events' => [
        // Enable event tracking
        'enabled' => true,

        // Auto-track these events
        'auto_track' => [
            'page_view' => true,
            'form_submit' => true,
            'outbound_link' => true,
            'file_download' => true,
            'scroll_depth' => false,
            'time_on_page' => true,
        ],

        // File extensions to track as downloads
        'download_extensions' => ['pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx'],
    ],

    'goals' => [
        // Enable goal tracking
        'enabled' => true,

        // Check goals synchronously (false = use queue)
        'sync' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for multi-tenant SaaS deployments.
    |
    */
    'multi_tenant' => [
        // Enable multi-tenant mode
        'enabled' => env('ANALYTICS_MULTI_TENANT', false),

        // Tenant resolvers (in order of priority)
        'resolvers' => [
            \ArtisanPackUI\Analytics\MultiTenant\Resolvers\ApiKeyResolver::class,
            \ArtisanPackUI\Analytics\MultiTenant\Resolvers\HeaderResolver::class,
            \ArtisanPackUI\Analytics\MultiTenant\Resolvers\SubdomainResolver::class,
            \ArtisanPackUI\Analytics\MultiTenant\Resolvers\DomainResolver::class,
        ],

        // Base domain for subdomain resolution
        'base_domain' => env('ANALYTICS_BASE_DOMAIN'),

        // Header name for site identification
        'site_header' => 'X-Site-ID',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the Analytics REST API.
    |
    */
    'api' => [
        // Enable API endpoints
        'enabled' => env('ANALYTICS_API_ENABLED', true),

        // API route prefix
        'prefix' => 'api/analytics/v1',

        // API middleware
        'middleware' => ['api'],

        // Rate limiting (requests per minute)
        'rate_limit' => env('ANALYTICS_API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for pre-computing aggregated metrics.
    |
    */
    'aggregation' => [
        // Enable aggregation
        'enabled' => true,

        // Metrics to aggregate
        'metrics' => [
            'visitors',
            'sessions',
            'page_views',
            'events',
            'conversions',
            'bounce_rate',
            'avg_session_duration',
            'pages_per_session',
        ],

        // Dimensions to aggregate by
        'dimensions' => [
            'page',
            'source',
            'country',
            'device',
            'browser',
        ],

        // Granularities to compute
        'granularities' => ['hour', 'day', 'week', 'month'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for background job processing.
    |
    */
    'queue' => [
        // Queue connection to use
        'connection' => env('ANALYTICS_QUEUE_CONNECTION', null),

        // Queue name
        'queue' => env('ANALYTICS_QUEUE_NAME', 'analytics'),

        // Batch processing
        'batch_size' => 100,
        'batch_delay' => 5, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Site Settings
    |--------------------------------------------------------------------------
    |
    | Default settings applied to new sites in multi-tenant mode.
    |
    */
    'site_defaults' => [
        'tracking' => [
            'anonymize_ip' => true,
            'track_bots' => false,
        ],
        'dashboard' => [
            'default_period' => '7d',
        ],
        'privacy' => [
            'consent_required' => true,
            'data_retention_days' => 730,
        ],
        'features' => [
            'events' => true,
            'goals' => true,
            'realtime' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Hooks
    |--------------------------------------------------------------------------
    |
    | Hook names for integration with other ArtisanPack UI packages.
    |
    */
    'hooks' => [
        'before_track' => 'ap.analytics.before_track',
        'after_track' => 'ap.analytics.after_track',
        'before_query' => 'ap.analytics.before_query',
        'after_query' => 'ap.analytics.after_query',
        'consent_changed' => 'ap.analytics.consent_changed',
        'goal_converted' => 'ap.analytics.goal_converted',
    ],
];
```

### Environment Variables

```bash
# .env

# Master switch
ANALYTICS_ENABLED=true

# Provider configuration
ANALYTICS_PROVIDER=local
ANALYTICS_DB_CONNECTION=mysql
ANALYTICS_QUEUE=analytics

# Google Analytics
GOOGLE_ANALYTICS_MEASUREMENT_ID=G-XXXXXXXXXX
GOOGLE_ANALYTICS_API_SECRET=
GOOGLE_ANALYTICS_DEBUG=false

# Plausible
PLAUSIBLE_DOMAIN=example.com
PLAUSIBLE_API_URL=https://plausible.io
PLAUSIBLE_API_KEY=

# Tracking
ANALYTICS_ANONYMIZE_IP=true
ANALYTICS_TRACK_BOTS=false
ANALYTICS_TRACK_AUTHENTICATED=true
ANALYTICS_RESPECT_DNT=true
ANALYTICS_SESSION_TIMEOUT=30
ANALYTICS_COOKIE_LIFETIME=365
ANALYTICS_EXCLUDED_IPS=127.0.0.1,192.168.1.1

# Privacy
ANALYTICS_CONSENT_REQUIRED=true
ANALYTICS_SHOW_CONSENT_BANNER=true
ANALYTICS_DATA_RETENTION=730

# Dashboard
ANALYTICS_DASHBOARD_CACHE=5

# Multi-tenant
ANALYTICS_MULTI_TENANT=false
ANALYTICS_BASE_DOMAIN=example.com

# API
ANALYTICS_API_ENABLED=true
ANALYTICS_API_RATE_LIMIT=60

# Queue
ANALYTICS_QUEUE_CONNECTION=redis
ANALYTICS_QUEUE_NAME=analytics
```

---

## Artisan Commands

### Installation & Setup

```bash
# Install the analytics package
php artisan analytics:install

# Publish configuration
php artisan vendor:publish --tag=analytics-config

# Publish views (for customization)
php artisan vendor:publish --tag=analytics-views

# Publish assets (JavaScript tracker)
php artisan vendor:publish --tag=analytics-assets

# Run migrations
php artisan migrate
```

### Data Management

```bash
# Run data aggregation for all metrics
php artisan analytics:aggregate

# Aggregate specific date range
php artisan analytics:aggregate --from=2024-01-01 --to=2024-01-31

# Aggregate specific metrics only
php artisan analytics:aggregate --metrics=visitors,page_views

# Clean old data based on retention policy
php artisan analytics:cleanup

# Cleanup with custom retention
php artisan analytics:cleanup --days=365

# Force cleanup without confirmation
php artisan analytics:cleanup --force

# Export analytics data
php artisan analytics:export --period=30d --format=csv --output=analytics-export.csv

# Export for specific site (multi-tenant)
php artisan analytics:export --site=1 --period=30d --format=json
```

### Site Management (Multi-Tenant)

```bash
# List all sites
php artisan analytics:sites

# Create a new site
php artisan analytics:site:create example.com --name="Example Site"

# Generate API key for a site
php artisan analytics:site:api-key 1

# Rotate API key
php artisan analytics:site:api-key 1 --rotate

# Delete a site
php artisan analytics:site:delete 1

# Show site details
php artisan analytics:site:show 1
```

### Goal Management

```bash
# List all goals
php artisan analytics:goals

# Create a goal interactively
php artisan analytics:goal:create

# Create a goal with options
php artisan analytics:goal:create "Newsletter Signup" --type=event --event="form_submitted" --category="newsletter" --value=5.00

# Delete a goal
php artisan analytics:goal:delete 1

# Show goal performance
php artisan analytics:goal:stats 1 --period=30d
```

### Reporting

```bash
# Generate a report
php artisan analytics:report --period=30d --email=admin@example.com

# Generate platform report (multi-tenant admin)
php artisan analytics:report:platform --period=30d

# Show real-time stats
php artisan analytics:realtime

# Show dashboard stats in CLI
php artisan analytics:stats --period=7d
```

### Debugging

```bash
# Test tracking configuration
php artisan analytics:test

# Verify provider connection
php artisan analytics:test --provider=google_analytics

# Clear analytics cache
php artisan analytics:cache:clear

# Show queue status
php artisan analytics:queue:status

# Process queued tracking events manually
php artisan analytics:queue:process --batch=100
```

---

## Event Hooks

The analytics package integrates with the `artisanpack-ui/hooks` package for extensibility.

### Available Hooks

#### Tracking Hooks

```php
use function addFilter;
use function addAction;

// Before tracking any data
addFilter('ap.analytics.before_track', function (array $data, string $type) {
    // Modify tracking data before it's saved
    $data['custom_field'] = 'value';
    return $data;
});

// After tracking completes
addAction('ap.analytics.after_track', function (array $data, string $type, $result) {
    // Perform actions after tracking
    logger()->info('Tracked', compact('type', 'data'));
});

// Filter page view data
addFilter('ap.analytics.page_view_data', function (array $data) {
    // Customize page view data
    return $data;
});

// Filter event data
addFilter('ap.analytics.event_data', function (array $data) {
    // Customize event data
    return $data;
});

// Filter visitor fingerprint
addFilter('ap.analytics.visitor_fingerprint', function (string $fingerprint, array $data) {
    // Customize visitor identification
    return $fingerprint;
});
```

#### Query Hooks

```php
// Before executing a query
addFilter('ap.analytics.before_query', function ($query, string $type) {
    // Modify query before execution
    return $query;
});

// After query results
addFilter('ap.analytics.after_query', function ($results, string $type) {
    // Modify query results
    return $results;
});

// Filter dashboard stats
addFilter('ap.analytics.dashboard_stats', function (array $stats, string $period) {
    // Add custom stats
    $stats['custom_metric'] = calculateCustomMetric($period);
    return $stats;
});
```

#### Consent Hooks

```php
// When consent changes
addAction('ap.analytics.consent_changed', function (array $consent, bool $granted) {
    if ($granted) {
        // User granted consent
    } else {
        // User revoked consent
    }
});

// Filter consent banner content
addFilter('ap.analytics.consent_banner', function (array $config) {
    $config['message'] = 'Custom consent message';
    return $config;
});
```

#### Goal & Conversion Hooks

```php
// Before goal matching
addFilter('ap.analytics.before_goal_match', function (array $event, $goals) {
    // Filter which goals to check
    return $goals;
});

// When a goal converts
addAction('ap.analytics.goal_converted', function ($goal, $conversion, array $data) {
    // Send notification
    Notification::send($admins, new GoalConvertedNotification($goal, $conversion));
});

// Filter conversion value
addFilter('ap.analytics.conversion_value', function (float $value, $goal, array $data) {
    // Calculate dynamic value
    return $value * 1.1; // Add 10%
});
```

#### Provider Hooks

```php
// Register custom provider
addFilter('ap.analytics.register_providers', function (array $providers) {
    $providers['custom'] = CustomAnalyticsProvider::class;
    return $providers;
});

// Before sending to external provider
addFilter('ap.analytics.provider_data', function (array $data, string $provider) {
    // Transform data for specific provider
    return $data;
});
```

#### Multi-Tenant Hooks

```php
// When site context changes
addAction('ap.analytics.site_changed', function ($site, $previousSite) {
    // Handle site context change
});

// Filter site resolvers
addFilter('ap.analytics.site_resolvers', function (array $resolvers) {
    // Add custom resolver
    $resolvers[] = CustomTenantResolver::class;
    return $resolvers;
});

// After site creation
addAction('ap.analytics.site_created', function ($site) {
    // Initialize site-specific settings
});
```

---

## JavaScript API

### Global Analytics Object

```javascript
// The analytics object is available globally
window.APAnalytics = {
    siteId: 'uuid-here',
    endpoint: '/api/analytics/track',
    debug: false,

    // Methods
    trackPageView: function(data) { /* ... */ },
    trackEvent: function(name, category, properties) { /* ... */ },
    setConsent: function(types) { /* ... */ },
    // ...
};
```

### Tracking Methods

```javascript
// Track a page view (usually automatic)
APAnalytics.trackPageView({
    url: '/custom-path',
    title: 'Custom Title',
    referrer: 'https://example.com'
});

// Track an event
APAnalytics.trackEvent('button_click', 'engagement', {
    button_id: 'cta-main',
    button_text: 'Get Started'
});

// Track a form submission
APAnalytics.trackForm('contact-form', 'Contact Us', true);

// Track ecommerce events
APAnalytics.trackPurchase({
    orderId: 'ORD-123',
    total: 99.99,
    currency: 'USD',
    items: [
        { id: 'PROD-1', name: 'Widget', price: 49.99, quantity: 2 }
    ]
});

// Track a custom conversion
APAnalytics.trackConversion(goalId, {
    value: 50.00,
    metadata: { source: 'landing_page' }
});
```

### Consent Management

```javascript
// Check consent
if (APAnalytics.hasConsent('analytics')) {
    // Tracking is enabled
}

// Set consent
APAnalytics.setConsent({
    analytics: true,
    marketing: false
});

// Revoke all consent
APAnalytics.revokeConsent();

// Listen for consent changes
APAnalytics.on('consentChanged', function(consent) {
    console.log('Consent updated:', consent);
});
```

### Event Listeners

```javascript
// Before tracking
APAnalytics.on('beforeTrack', function(type, data) {
    console.log('About to track:', type, data);
    // Return false to cancel tracking
    // Return modified data to change what's tracked
    return data;
});

// After tracking
APAnalytics.on('afterTrack', function(type, data, response) {
    console.log('Tracked:', type, response);
});

// On error
APAnalytics.on('error', function(error) {
    console.error('Analytics error:', error);
});
```

### Configuration

```javascript
// Update configuration
APAnalytics.configure({
    debug: true,
    respectDNT: true,
    autoTrack: {
        pageViews: true,
        outboundLinks: true,
        fileDownloads: true
    }
});

// Disable tracking temporarily
APAnalytics.disable();

// Re-enable tracking
APAnalytics.enable();

// Check if enabled
if (APAnalytics.isEnabled()) {
    // Tracking is active
}
```

---

## REST API Endpoints

### Authentication

All API requests require authentication via API key:

```bash
# Using Bearer token (recommended)
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://example.com/api/analytics/v1/stats

# Using X-API-Key header
curl -H "X-API-Key: YOUR_API_KEY" \
     https://example.com/api/analytics/v1/stats

# Using query parameter (not recommended for production)
curl https://example.com/api/analytics/v1/stats?api_key=YOUR_API_KEY
```

### Tracking Endpoints

#### Track Page View

```bash
POST /api/analytics/v1/track/pageview

{
    "url": "/products/widget",
    "title": "Widget Product Page",
    "referrer": "https://google.com",
    "metadata": {
        "category": "products"
    }
}

# Response
{
    "success": true,
    "id": "uuid-here"
}
```

#### Track Event

```bash
POST /api/analytics/v1/track/event

{
    "name": "button_click",
    "category": "engagement",
    "properties": {
        "button_id": "cta-hero"
    }
}

# Response
{
    "success": true,
    "id": "uuid-here"
}
```

### Query Endpoints

#### Get Stats

```bash
GET /api/analytics/v1/stats?period=7d

# Response
{
    "visitors": 1234,
    "sessions": 2345,
    "page_views": 5678,
    "bounce_rate": 45.2,
    "avg_session_duration": 182,
    "pages_per_session": 2.4
}
```

#### Get Visitors

```bash
GET /api/analytics/v1/visitors?period=30d&returning=true

# Response
{
    "data": [
        {
            "date": "2024-01-15",
            "new_visitors": 150,
            "returning_visitors": 75,
            "total": 225
        }
    ],
    "total": 4500,
    "period": "30d"
}
```

#### Get Pages

```bash
GET /api/analytics/v1/pages?period=7d&limit=10

# Response
{
    "data": [
        {
            "path": "/",
            "title": "Home",
            "views": 1234,
            "unique_views": 890,
            "avg_time": 45,
            "bounce_rate": 42.5
        }
    ],
    "period": "7d"
}
```

#### Get Events

```bash
GET /api/analytics/v1/events?period=7d&category=engagement

# Response
{
    "data": [
        {
            "name": "button_click",
            "category": "engagement",
            "count": 456,
            "unique_count": 234
        }
    ],
    "period": "7d"
}
```

#### Get Goals

```bash
GET /api/analytics/v1/goals?period=30d

# Response
{
    "data": [
        {
            "id": 1,
            "name": "Newsletter Signup",
            "conversions": 234,
            "value": 1170.00,
            "conversion_rate": 5.2
        }
    ],
    "period": "30d"
}
```

### Site Management Endpoints

#### Get Site Info

```bash
GET /api/analytics/v1/site

# Response
{
    "id": 1,
    "uuid": "uuid-here",
    "name": "Example Site",
    "domain": "example.com",
    "timezone": "UTC",
    "settings": { ... }
}
```

#### Update Site Settings

```bash
PUT /api/analytics/v1/site/settings

{
    "tracking": {
        "anonymize_ip": true
    },
    "dashboard": {
        "default_period": "30d"
    }
}

# Response
{
    "success": true,
    "settings": { ... }
}
```

### Rate Limiting

API requests are rate-limited. The current limits are returned in response headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1705320000
```

---

## Summary

This API reference covers all public interfaces for the analytics package:

- **Facades**: `Analytics`, `AnalyticsQuery`, `Goal`, `Consent` for programmatic access
- **Helper Functions**: Quick access to common operations like tracking and querying
- **Blade Directives**: Easy integration in templates for scripts, widgets, and conditional content
- **Configuration**: Comprehensive settings for all package features
- **Artisan Commands**: CLI tools for installation, data management, and reporting
- **Event Hooks**: Integration points for extending and customizing behavior
- **JavaScript API**: Client-side tracking and consent management
- **REST API**: External access for headless and third-party integrations

All APIs are designed to be intuitive, well-documented, and consistent with Laravel conventions.
