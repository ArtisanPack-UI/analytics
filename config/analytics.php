<?php

declare( strict_types=1 );

/**
 * ArtisanPack UI - Analytics Configuration
 *
 * This configuration file defines settings for the Analytics package.
 * Settings are merged into the main artisanpack.php config file under the
 * 'analytics' key, following ArtisanPack UI package conventions.
 *
 * After publishing, this file can be found at: config/artisanpack/analytics.php
 *
 * @package    ArtisanPackUI\Analytics
 *
 * @since      1.0.0
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch to enable or disable all analytics tracking. When disabled,
    | no data will be collected and all tracking endpoints will return early.
    |
    */
    'enabled' => env( 'ANALYTICS_ENABLED', true ),

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The default analytics provider to use. The 'local' provider stores
    | all data in your database for complete privacy and control.
    |
    | Supported: "local", "google", "plausible"
    |
    */
    'default' => env( 'ANALYTICS_PROVIDER', 'local' ),

    /*
    |--------------------------------------------------------------------------
    | Active Providers
    |--------------------------------------------------------------------------
    |
    | Array of providers that should receive analytics data. You can use
    | multiple providers simultaneously for hybrid tracking.
    |
    */
    'active_providers' => array_filter( explode( ',', env( 'ANALYTICS_ACTIVE_PROVIDERS', 'local' ) ) ),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix for all analytics API routes. This will be prepended to
    | all analytics endpoints (e.g., /api/analytics/pageview).
    |
    */
    'route_prefix' => env( 'ANALYTICS_ROUTE_PREFIX', 'api/analytics' ),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to analytics API routes. The 'analytics' middleware
    | alias is registered by the package and includes throttling and privacy
    | filtering.
    |
    */
    'route_middleware' => [ 'api', 'analytics' ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Route
    |--------------------------------------------------------------------------
    |
    | The route for the analytics dashboard. Set to null to disable the
    | built-in dashboard route.
    |
    */
    'dashboard_route' => env( 'ANALYTICS_DASHBOARD_ROUTE', 'analytics' ),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to the analytics dashboard route.
    |
    */
    'dashboard_middleware' => [ 'web', 'auth' ],

    /*
    |--------------------------------------------------------------------------
    | Local Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the local (database) analytics provider. This provider
    | stores all analytics data in your database for complete privacy.
    |
    */
    'local' => [
        'enabled' => env( 'ANALYTICS_LOCAL_ENABLED', true ),

        /*
        |----------------------------------------------------------------------
        | Database Connection
        |----------------------------------------------------------------------
        |
        | The database connection to use for analytics tables. Set to null
        | to use the default connection.
        |
        */
        'connection' => env( 'ANALYTICS_DB_CONNECTION', null ),

        /*
        |----------------------------------------------------------------------
        | Table Prefix
        |----------------------------------------------------------------------
        |
        | Prefix for all analytics database tables.
        |
        */
        'table_prefix' => env( 'ANALYTICS_TABLE_PREFIX', 'analytics_' ),

        /*
        |----------------------------------------------------------------------
        | IP Address Anonymization
        |----------------------------------------------------------------------
        |
        | When enabled, IP addresses will be anonymized by zeroing out
        | the last octet (IPv4) or last 80 bits (IPv6).
        |
        */
        'anonymize_ip' => env( 'ANALYTICS_ANONYMIZE_IP', true ),

        /*
        |----------------------------------------------------------------------
        | Queue Processing
        |----------------------------------------------------------------------
        |
        | When enabled, page views and events are processed asynchronously
        | via queued jobs for better performance.
        |
        */
        'queue_processing' => env( 'ANALYTICS_QUEUE_PROCESSING', true ),

        /*
        |----------------------------------------------------------------------
        | Queue Name
        |----------------------------------------------------------------------
        |
        | The queue name to use for processing analytics jobs.
        |
        */
        'queue_name' => env( 'ANALYTICS_QUEUE_NAME', 'analytics' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | External Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for external analytics providers (Google Analytics, Plausible).
    | These can be used alongside or instead of the local provider.
    |
    */
    'providers' => [
        'google' => [
            'enabled'        => env( 'ANALYTICS_GOOGLE_ENABLED', false ),
            'measurement_id' => env( 'ANALYTICS_GOOGLE_MEASUREMENT_ID' ),
            'api_secret'     => env( 'ANALYTICS_GOOGLE_API_SECRET' ),
        ],

        'plausible' => [
            'enabled' => env( 'ANALYTICS_PLAUSIBLE_ENABLED', false ),
            'domain'  => env( 'ANALYTICS_PLAUSIBLE_DOMAIN' ),
            'api_url' => env( 'ANALYTICS_PLAUSIBLE_API_URL', 'https://plausible.io/api' ),
            'api_key' => env( 'ANALYTICS_PLAUSIBLE_API_KEY' ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for session tracking and management.
    |
    */
    'session' => [
        /*
        |----------------------------------------------------------------------
        | Session Timeout
        |----------------------------------------------------------------------
        |
        | Time in minutes after which a session is considered expired
        | due to inactivity.
        |
        */
        'timeout' => env( 'ANALYTICS_SESSION_TIMEOUT', 30 ),

        /*
        |----------------------------------------------------------------------
        | Session Cookie Name
        |----------------------------------------------------------------------
        |
        | Name of the cookie used to store the session identifier.
        |
        */
        'cookie_name' => env( 'ANALYTICS_SESSION_COOKIE', '_ap_sid' ),

        /*
        |----------------------------------------------------------------------
        | Visitor Cookie Name
        |----------------------------------------------------------------------
        |
        | Name of the cookie used to store the visitor identifier.
        |
        */
        'visitor_cookie_name' => env( 'ANALYTICS_VISITOR_COOKIE', '_ap_vid' ),

        /*
        |----------------------------------------------------------------------
        | Cookie Lifetime
        |----------------------------------------------------------------------
        |
        | Lifetime of the visitor cookie in days.
        |
        */
        'cookie_lifetime' => env( 'ANALYTICS_COOKIE_LIFETIME', 365 ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for privacy compliance (GDPR, CCPA, etc.).
    |
    */
    'privacy' => [
        /*
        |----------------------------------------------------------------------
        | Consent Required
        |----------------------------------------------------------------------
        |
        | When enabled, tracking will only occur after user consent is
        | obtained. Integrate with a consent management platform.
        |
        */
        'consent_required' => env( 'ANALYTICS_CONSENT_REQUIRED', false ),

        /*
        |----------------------------------------------------------------------
        | Respect Do Not Track
        |----------------------------------------------------------------------
        |
        | When enabled, the DNT (Do Not Track) browser header will be
        | respected and no tracking will occur for those visitors.
        |
        */
        'respect_dnt' => env( 'ANALYTICS_RESPECT_DNT', true ),

        /*
        |----------------------------------------------------------------------
        | Excluded IP Addresses
        |----------------------------------------------------------------------
        |
        | IP addresses or CIDR ranges to exclude from tracking.
        | Useful for excluding internal traffic.
        |
        */
        'excluded_ips' => array_filter( explode( ',', env( 'ANALYTICS_EXCLUDED_IPS', '' ) ) ),

        /*
        |----------------------------------------------------------------------
        | Excluded User Agents
        |----------------------------------------------------------------------
        |
        | User agent patterns to exclude from tracking (regex patterns).
        | Bots and crawlers are excluded by default.
        |
        */
        'excluded_user_agents' => [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/slurp/i',
            '/mediapartners/i',
        ],

        /*
        |----------------------------------------------------------------------
        | Excluded Paths
        |----------------------------------------------------------------------
        |
        | URL paths to exclude from tracking. Supports wildcards (*).
        |
        */
        'excluded_paths' => [
            '/admin/*',
            '/api/*',
            '/_debugbar/*',
            '/telescope/*',
            '/horizon/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Settings for data retention and cleanup.
    |
    */
    'retention' => [
        /*
        |----------------------------------------------------------------------
        | Retention Period
        |----------------------------------------------------------------------
        |
        | Number of days to retain raw analytics data. After this period,
        | data is either deleted or aggregated based on settings below.
        |
        */
        'period' => env( 'ANALYTICS_RETENTION_DAYS', 90 ),

        /*
        |----------------------------------------------------------------------
        | Aggregate Before Deletion
        |----------------------------------------------------------------------
        |
        | When enabled, data is aggregated into summary tables before
        | raw data is deleted, preserving historical trends.
        |
        */
        'aggregate_before_delete' => env( 'ANALYTICS_AGGREGATE_BEFORE_DELETE', true ),

        /*
        |----------------------------------------------------------------------
        | Aggregation Retention
        |----------------------------------------------------------------------
        |
        | Number of days to retain aggregated data. Set to 0 for indefinite.
        |
        */
        'aggregation_retention' => env( 'ANALYTICS_AGGREGATION_RETENTION', 0 ),

        /*
        |----------------------------------------------------------------------
        | Cleanup Schedule
        |----------------------------------------------------------------------
        |
        | Cron expression for when to run the cleanup job.
        |
        */
        'cleanup_schedule' => env( 'ANALYTICS_CLEANUP_SCHEDULE', '0 3 * * *' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the analytics dashboard.
    |
    */
    'dashboard' => [
        /*
        |----------------------------------------------------------------------
        | Default Date Range
        |----------------------------------------------------------------------
        |
        | The default date range to show on the dashboard (in days).
        |
        */
        'default_date_range' => env( 'ANALYTICS_DEFAULT_DATE_RANGE', 30 ),

        /*
        |----------------------------------------------------------------------
        | Cache Duration
        |----------------------------------------------------------------------
        |
        | How long to cache dashboard queries in seconds.
        |
        */
        'cache_duration' => env( 'ANALYTICS_CACHE_DURATION', 300 ),

        /*
        |----------------------------------------------------------------------
        | Real-time Enabled
        |----------------------------------------------------------------------
        |
        | Enable the real-time visitors widget on the dashboard.
        |
        */
        'realtime_enabled' => env( 'ANALYTICS_REALTIME_ENABLED', true ),

        /*
        |----------------------------------------------------------------------
        | Real-time Interval
        |----------------------------------------------------------------------
        |
        | How often to refresh real-time data in seconds.
        |
        */
        'realtime_interval' => env( 'ANALYTICS_REALTIME_INTERVAL', 30 ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Settings for API rate limiting to prevent abuse.
    |
    */
    'rate_limiting' => [
        /*
        |----------------------------------------------------------------------
        | Enabled
        |----------------------------------------------------------------------
        |
        | Enable rate limiting on tracking endpoints.
        |
        */
        'enabled' => env( 'ANALYTICS_RATE_LIMIT_ENABLED', true ),

        /*
        |----------------------------------------------------------------------
        | Max Attempts
        |----------------------------------------------------------------------
        |
        | Maximum number of tracking requests per minute from a single IP.
        |
        */
        'max_attempts' => env( 'ANALYTICS_RATE_LIMIT_MAX', 60 ),

        /*
        |----------------------------------------------------------------------
        | Decay Minutes
        |----------------------------------------------------------------------
        |
        | Time window for rate limiting in minutes.
        |
        */
        'decay_minutes' => env( 'ANALYTICS_RATE_LIMIT_DECAY', 1 ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for multi-tenant (SaaS) deployments.
    |
    */
    'multi_tenant' => [
        /*
        |----------------------------------------------------------------------
        | Enabled
        |----------------------------------------------------------------------
        |
        | Enable multi-tenant support for analytics data isolation.
        |
        */
        'enabled' => env( 'ANALYTICS_MULTI_TENANT', false ),

        /*
        |----------------------------------------------------------------------
        | Tenant Identifier Column
        |----------------------------------------------------------------------
        |
        | The column name used to identify tenants in the database.
        |
        */
        'tenant_column' => env( 'ANALYTICS_TENANT_COLUMN', 'tenant_id' ),

        /*
        |----------------------------------------------------------------------
        | Tenant Resolver
        |----------------------------------------------------------------------
        |
        | Class responsible for resolving the current tenant. Must implement
        | ArtisanPackUI\Analytics\Contracts\TenantResolverInterface.
        |
        */
        'resolver' => env( 'ANALYTICS_TENANT_RESOLVER' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for custom event tracking.
    |
    */
    'events' => [
        /*
        |----------------------------------------------------------------------
        | Allowed Event Names
        |----------------------------------------------------------------------
        |
        | List of allowed event names. Set to empty array to allow all.
        | Helps prevent spam and invalid event tracking.
        |
        */
        'allowed_names' => [],

        /*
        |----------------------------------------------------------------------
        | Max Properties
        |----------------------------------------------------------------------
        |
        | Maximum number of custom properties allowed per event.
        |
        */
        'max_properties' => env( 'ANALYTICS_MAX_EVENT_PROPERTIES', 25 ),

        /*
        |----------------------------------------------------------------------
        | Max Property Value Length
        |----------------------------------------------------------------------
        |
        | Maximum length for event property values.
        |
        */
        'max_property_value_length' => env( 'ANALYTICS_MAX_PROPERTY_LENGTH', 500 ),
    ],

    /*
    |--------------------------------------------------------------------------
    | JavaScript Tracker Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the JavaScript analytics tracker.
    |
    */
    'tracker' => [
        /*
        |----------------------------------------------------------------------
        | Tracker Script Path
        |----------------------------------------------------------------------
        |
        | Path to serve the JavaScript tracker script.
        |
        */
        'script_path' => env( 'ANALYTICS_TRACKER_PATH', '/js/analytics.js' ),

        /*
        |----------------------------------------------------------------------
        | Minified
        |----------------------------------------------------------------------
        |
        | Serve the minified version of the tracker script.
        |
        */
        'minified' => env( 'ANALYTICS_TRACKER_MINIFIED', true ),

        /*
        |----------------------------------------------------------------------
        | Track Hash Changes
        |----------------------------------------------------------------------
        |
        | Automatically track hash changes as page views (for SPAs).
        |
        */
        'track_hash_changes' => env( 'ANALYTICS_TRACK_HASH', false ),

        /*
        |----------------------------------------------------------------------
        | Track Outbound Links
        |----------------------------------------------------------------------
        |
        | Automatically track clicks on outbound links.
        |
        */
        'track_outbound_links' => env( 'ANALYTICS_TRACK_OUTBOUND', true ),

        /*
        |----------------------------------------------------------------------
        | Track File Downloads
        |----------------------------------------------------------------------
        |
        | Automatically track file download link clicks.
        |
        */
        'track_file_downloads' => env( 'ANALYTICS_TRACK_DOWNLOADS', true ),

        /*
        |----------------------------------------------------------------------
        | Download Extensions
        |----------------------------------------------------------------------
        |
        | File extensions to track as downloads.
        |
        */
        'download_extensions' => [
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'zip',
            'rar',
            'gz',
            'tar',
            'exe',
            'dmg',
        ],
    ],
];
