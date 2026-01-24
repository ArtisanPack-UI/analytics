---
title: Configuration
---

# Configuration

ArtisanPack UI Analytics is highly configurable. All settings are defined in `config/artisanpack/analytics.php`.

## General Settings

### Enable/Disable Analytics

```php
'enabled' => env('ANALYTICS_ENABLED', true),
```

Master switch to enable or disable all analytics tracking.

### Default Provider

```php
'default' => env('ANALYTICS_PROVIDER', 'local'),
```

The primary analytics provider. Supported values: `local`, `google`, `plausible`.

### Active Providers

```php
'active_providers' => array_filter(explode(',', env('ANALYTICS_ACTIVE_PROVIDERS', 'local'))),
```

Enable multiple providers simultaneously by passing a comma-separated list.

## Route Configuration

### Route Prefix

```php
'route_prefix' => env('ANALYTICS_ROUTE_PREFIX', 'api/analytics'),
```

Prefix for all analytics API endpoints.

### Route Middleware

```php
'route_middleware' => ['api', 'analytics'],
```

Middleware applied to tracking endpoints.

### Dashboard Route

```php
'dashboard_route' => env('ANALYTICS_DASHBOARD_ROUTE', 'analytics'),
```

The route for the analytics dashboard. Set to `null` to disable.

### Dashboard Middleware

```php
'dashboard_middleware' => ['web', 'auth'],
```

Middleware applied to the dashboard route.

## Local Provider Settings

### Database Connection

```php
'local' => [
    'connection' => env('ANALYTICS_DB_CONNECTION', null),
    'table_prefix' => env('ANALYTICS_TABLE_PREFIX', 'analytics_'),
],
```

Use a separate database connection for analytics data.

### Queue Processing

```php
'local' => [
    'queue_processing' => env('ANALYTICS_QUEUE_PROCESSING', true),
    'queue_name' => env('ANALYTICS_QUEUE_NAME', 'analytics'),
],
```

Process tracking data asynchronously for better performance.

### IP Anonymization

```php
'local' => [
    'anonymize_ip' => env('ANALYTICS_ANONYMIZE_IP', true),
],
```

Anonymize IP addresses by zeroing the last octet (IPv4) or last 80 bits (IPv6).

## External Providers

### Google Analytics 4

```php
'providers' => [
    'google' => [
        'enabled' => env('ANALYTICS_GOOGLE_ENABLED', false),
        'measurement_id' => env('ANALYTICS_GOOGLE_MEASUREMENT_ID'),
        'api_secret' => env('ANALYTICS_GOOGLE_API_SECRET'),
    ],
],
```

### Plausible Analytics

```php
'providers' => [
    'plausible' => [
        'enabled' => env('ANALYTICS_PLAUSIBLE_ENABLED', false),
        'domain' => env('ANALYTICS_PLAUSIBLE_DOMAIN'),
        'api_url' => env('ANALYTICS_PLAUSIBLE_API_URL', 'https://plausible.io/api'),
        'api_key' => env('ANALYTICS_PLAUSIBLE_API_KEY'),
    ],
],
```

## Session Configuration

```php
'session' => [
    'timeout' => env('ANALYTICS_SESSION_TIMEOUT', 30),
    'cookie_name' => env('ANALYTICS_SESSION_COOKIE', '_ap_sid'),
    'visitor_cookie_name' => env('ANALYTICS_VISITOR_COOKIE', '_ap_vid'),
    'cookie_lifetime' => env('ANALYTICS_COOKIE_LIFETIME', 365),
],
```

| Setting | Description | Default |
|---------|-------------|---------|
| `timeout` | Session inactivity timeout (minutes) | 30 |
| `cookie_name` | Session cookie name | `_ap_sid` |
| `visitor_cookie_name` | Visitor cookie name | `_ap_vid` |
| `cookie_lifetime` | Cookie lifetime (days) | 365 |

## Privacy Configuration

### Consent Settings

```php
'privacy' => [
    'consent_required' => env('ANALYTICS_CONSENT_REQUIRED', false),
    'consent_cookie_lifetime' => env('ANALYTICS_CONSENT_LIFETIME', 365),
    'consent_categories' => [
        'analytics' => [
            'name' => 'Analytics',
            'description' => 'Helps us understand how visitors use our website.',
            'required' => false,
        ],
        'marketing' => [
            'name' => 'Marketing',
            'description' => 'Used to track visitors across websites for advertising.',
            'required' => false,
        ],
    ],
],
```

### Do Not Track

```php
'privacy' => [
    'respect_dnt' => env('ANALYTICS_RESPECT_DNT', true),
],
```

Respect the browser's Do Not Track setting.

### Anonymization

```php
'privacy' => [
    'anonymization' => [
        'ip_address' => env('ANALYTICS_ANONYMIZE_IP', true),
        'user_agent' => env('ANALYTICS_ANONYMIZE_UA', false),
        'screen_resolution' => env('ANALYTICS_ANONYMIZE_SCREEN', false),
    ],
],
```

### Exclusions

```php
'privacy' => [
    'excluded_ips' => array_filter(explode(',', env('ANALYTICS_EXCLUDED_IPS', ''))),
    'excluded_user_agents' => [
        '/bot/i',
        '/crawler/i',
        '/spider/i',
    ],
    'excluded_paths' => [
        '/admin/*',
        '/api/*',
        '/_debugbar/*',
    ],
],
```

## Data Retention

```php
'retention' => [
    'period' => env('ANALYTICS_RETENTION_DAYS', 90),
    'aggregate_before_delete' => env('ANALYTICS_AGGREGATE_BEFORE_DELETE', true),
    'aggregation_retention' => env('ANALYTICS_AGGREGATION_RETENTION', 0),
    'cleanup_schedule' => env('ANALYTICS_CLEANUP_SCHEDULE', '0 3 * * *'),
],
```

| Setting | Description | Default |
|---------|-------------|---------|
| `period` | Days to retain raw data | 90 |
| `aggregate_before_delete` | Aggregate before deleting | true |
| `aggregation_retention` | Days to retain aggregates (0 = forever) | 0 |
| `cleanup_schedule` | Cron schedule for cleanup | Daily at 3 AM |

## Dashboard Configuration

```php
'dashboard' => [
    'default_date_range' => env('ANALYTICS_DEFAULT_DATE_RANGE', 30),
    'cache_duration' => env('ANALYTICS_CACHE_DURATION', 300),
    'realtime_enabled' => env('ANALYTICS_REALTIME_ENABLED', true),
    'realtime_interval' => env('ANALYTICS_REALTIME_INTERVAL', 30),
],
```

## Rate Limiting

```php
'rate_limiting' => [
    'enabled' => env('ANALYTICS_RATE_LIMIT_ENABLED', true),
    'max_attempts' => env('ANALYTICS_RATE_LIMIT_MAX', 60),
    'decay_minutes' => env('ANALYTICS_RATE_LIMIT_DECAY', 1),
],
```

## Multi-Tenant Configuration

```php
'multi_tenant' => [
    'enabled' => env('ANALYTICS_MULTI_TENANT', false),
    'tenant_column' => env('ANALYTICS_TENANT_COLUMN', 'tenant_id'),
    'resolver' => env('ANALYTICS_TENANT_RESOLVER'),
    'resolvers' => [
        ArtisanPackUI\Analytics\Resolvers\ApiKeyResolver::class,
        ArtisanPackUI\Analytics\Resolvers\HeaderResolver::class,
        ArtisanPackUI\Analytics\Resolvers\SubdomainResolver::class,
        ArtisanPackUI\Analytics\Resolvers\DomainResolver::class,
    ],
    'base_domain' => env('ANALYTICS_BASE_DOMAIN'),
    'site_header' => env('ANALYTICS_SITE_HEADER', 'X-Site-ID'),
    'default_site_id' => env('ANALYTICS_DEFAULT_SITE_ID'),
],
```

See [Multi-Tenancy](../advanced/multi-tenancy.md) for detailed configuration.

## Event Tracking

```php
'events' => [
    'allowed_names' => [],
    'max_properties' => env('ANALYTICS_MAX_EVENT_PROPERTIES', 25),
    'max_property_value_length' => env('ANALYTICS_MAX_PROPERTY_LENGTH', 500),
    'auto_track' => [
        'outbound_links' => env('ANALYTICS_AUTO_TRACK_OUTBOUND', true),
        'file_downloads' => env('ANALYTICS_AUTO_TRACK_DOWNLOADS', true),
        'scroll_depth' => env('ANALYTICS_AUTO_TRACK_SCROLL', true),
        'video_engagement' => env('ANALYTICS_AUTO_TRACK_VIDEO', false),
    ],
],
```

## JavaScript Tracker

```php
'tracker' => [
    'script_path' => env('ANALYTICS_TRACKER_PATH', '/js/analytics.js'),
    'minified' => env('ANALYTICS_TRACKER_MINIFIED', true),
    'track_hash_changes' => env('ANALYTICS_TRACK_HASH', false),
    'track_outbound_links' => env('ANALYTICS_TRACK_OUTBOUND', true),
    'track_file_downloads' => env('ANALYTICS_TRACK_DOWNLOADS', true),
    'download_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'],
],
```

## Environment Variables Reference

```dotenv
# General
ANALYTICS_ENABLED=true
ANALYTICS_PROVIDER=local
ANALYTICS_ACTIVE_PROVIDERS=local

# Routes
ANALYTICS_ROUTE_PREFIX=api/analytics
ANALYTICS_DASHBOARD_ROUTE=analytics

# Local Provider
ANALYTICS_DB_CONNECTION=
ANALYTICS_TABLE_PREFIX=analytics_
ANALYTICS_ANONYMIZE_IP=true
ANALYTICS_QUEUE_PROCESSING=true
ANALYTICS_QUEUE_NAME=analytics

# External Providers
ANALYTICS_GOOGLE_ENABLED=false
ANALYTICS_GOOGLE_MEASUREMENT_ID=
ANALYTICS_GOOGLE_API_SECRET=
ANALYTICS_PLAUSIBLE_ENABLED=false
ANALYTICS_PLAUSIBLE_DOMAIN=
ANALYTICS_PLAUSIBLE_API_URL=https://plausible.io/api
ANALYTICS_PLAUSIBLE_API_KEY=

# Session
ANALYTICS_SESSION_TIMEOUT=30
ANALYTICS_SESSION_COOKIE=_ap_sid
ANALYTICS_VISITOR_COOKIE=_ap_vid
ANALYTICS_COOKIE_LIFETIME=365

# Privacy
ANALYTICS_CONSENT_REQUIRED=false
ANALYTICS_CONSENT_LIFETIME=365
ANALYTICS_RESPECT_DNT=true
ANALYTICS_EXCLUDED_IPS=

# Data Retention
ANALYTICS_RETENTION_DAYS=90
ANALYTICS_AGGREGATE_BEFORE_DELETE=true
ANALYTICS_AGGREGATION_RETENTION=0

# Dashboard
ANALYTICS_DEFAULT_DATE_RANGE=30
ANALYTICS_CACHE_DURATION=300
ANALYTICS_REALTIME_ENABLED=true
ANALYTICS_REALTIME_INTERVAL=30

# Rate Limiting
ANALYTICS_RATE_LIMIT_ENABLED=true
ANALYTICS_RATE_LIMIT_MAX=60
ANALYTICS_RATE_LIMIT_DECAY=1

# Multi-Tenant
ANALYTICS_MULTI_TENANT=false
ANALYTICS_TENANT_COLUMN=tenant_id
ANALYTICS_BASE_DOMAIN=
ANALYTICS_SITE_HEADER=X-Site-ID
ANALYTICS_DEFAULT_SITE_ID=

# Events
ANALYTICS_MAX_EVENT_PROPERTIES=25
ANALYTICS_MAX_PROPERTY_LENGTH=500

# Tracker
ANALYTICS_TRACKER_PATH=/js/analytics.js
ANALYTICS_TRACKER_MINIFIED=true
ANALYTICS_TRACK_HASH=false
ANALYTICS_TRACK_OUTBOUND=true
ANALYTICS_TRACK_DOWNLOADS=true
```
