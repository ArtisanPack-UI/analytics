---
title: Helper Functions
---

# Helper Functions

ArtisanPack UI Analytics provides global helper functions for common operations.

## Core Functions

### analytics()

Get the Analytics instance:

```php
$analytics = analytics();
$analytics->trackPageView($pageViewData);
```

### analyticsEnabled()

Check if analytics is enabled:

```php
if (analyticsEnabled()) {
    trackPageView('/page', 'Title');
}
```

### getAnalyticsConfig()

Get configuration values:

```php
$timeout = getAnalyticsConfig('session.timeout', 30);
$enabled = getAnalyticsConfig('enabled', true);
```

## Tracking Functions

### trackPageView()

Track a page view:

```php
// Basic
trackPageView('/products', 'Products Page');

// With custom data
trackPageView('/products/123', 'Widget Pro', [
    'category' => 'widgets',
    'product_id' => 123,
]);
```

**Parameters:**
- `$path` (string) - The page path
- `$title` (?string) - The page title
- `$customData` (?array) - Additional data

### trackEvent()

Track a custom event:

```php
// Simple event
trackEvent('button_click');

// With properties
trackEvent('button_click', ['button_id' => 'cta']);

// With value
trackEvent('purchase', ['order_id' => '123'], 99.99);

// With category
trackEvent('video_play', ['video_id' => '456'], null, 'engagement');
```

**Parameters:**
- `$name` (string) - Event name
- `$properties` (?array) - Event properties
- `$value` (?float) - Numeric value
- `$category` (?string) - Event category

### analyticsTrackForm()

Track form submissions:

```php
analyticsTrackForm('contact-form', [
    'source' => 'homepage',
]);
```

**Parameters:**
- `$formId` (string) - Form identifier
- `$data` (array) - Additional data

### analyticsTrackPurchase()

Track purchases:

```php
analyticsTrackPurchase(
    value: 149.99,
    currency: 'USD',
    items: [
        ['name' => 'Widget', 'quantity' => 2],
    ],
    metadata: ['order_id' => 'ORD-123']
);
```

**Parameters:**
- `$value` (float) - Purchase value
- `$currency` (string) - Currency code (default: 'USD')
- `$items` (array) - List of items
- `$metadata` (array) - Additional data

### analyticsTrackConversion()

Track goal conversions:

```php
analyticsTrackConversion(goalId: 1, value: 50.00);
```

**Parameters:**
- `$goalId` (int) - Goal ID
- `$value` (?float) - Conversion value

## Query Functions

### analyticsStats()

Get comprehensive statistics:

```php
$stats = analyticsStats(
    range: DateRange::last7Days(),
    withCompare: true,
    filters: ['path' => '/products/*']
);
```

**Returns:** Array with visitors, page views, sessions, bounce rate, etc.

### analyticsPageViews()

Get page views over time:

```php
$pageViews = analyticsPageViews(
    range: DateRange::last30Days(),
    granularity: 'day',
    filters: []
);
```

**Returns:** Collection of page view data grouped by time period.

**Granularity options:** `'hour'`, `'day'`, `'week'`, `'month'`

### analyticsVisitors()

Get unique visitor count:

```php
$count = analyticsVisitors(DateRange::last7Days());
```

**Returns:** Integer count of unique visitors.

### analyticsTopPages()

Get top pages by views:

```php
$topPages = analyticsTopPages(
    range: DateRange::thisMonth(),
    limit: 10,
    filters: []
);
```

**Returns:** Collection with path, title, views, unique visitors.

### analyticsTrafficSources()

Get traffic sources:

```php
$sources = analyticsTrafficSources(
    range: DateRange::last7Days(),
    limit: 10
);
```

**Returns:** Collection with source, medium, visitors, percentage.

### analyticsRealtime()

Get real-time visitor data:

```php
$realtime = analyticsRealtime(minutes: 5);
```

**Returns:** Array with active visitors, current pages, sources.

## Date Range Functions

### dateRangeLastDays()

Create a date range for last N days:

```php
$range = dateRangeLastDays(14);
```

### dateRangeToday()

Create a date range for today:

```php
$range = dateRangeToday();
```

### dateRangeThisMonth()

Create a date range for this month:

```php
$range = dateRangeThisMonth();
```

## Site/Tenant Functions

### analyticsSite()

Get the current analytics site:

```php
$site = analyticsSite();
if ($site) {
    echo $site->name;
}
```

**Returns:** Site model or null if not in multi-tenant mode.

### analyticsTenantId()

Get the current tenant ID:

```php
$tenantId = analyticsTenantId();
```

**Returns:** Integer, string, or null.

## Consent Functions

### analyticsHasConsent()

Check if visitor has consent:

```php
if (analyticsHasConsent($fingerprint, 'analytics')) {
    // Track data
}
```

**Parameters:**
- `$fingerprint` (?string) - Visitor fingerprint
- `$category` (string) - Consent category (default: 'analytics')

### analyticsGrantConsent()

Grant consent for categories:

```php
analyticsGrantConsent($fingerprint, ['analytics', 'marketing']);
```

**Parameters:**
- `$fingerprint` (string) - Visitor fingerprint
- `$categories` (array) - Categories to grant

### analyticsRevokeConsent()

Revoke consent for categories:

```php
analyticsRevokeConsent($fingerprint, ['marketing']);
```

**Parameters:**
- `$fingerprint` (string) - Visitor fingerprint
- `$categories` (array) - Categories to revoke

### analyticsConsentStatus()

Get consent status:

```php
$status = analyticsConsentStatus($fingerprint);
// Returns array of categories with their status
```

## Utility Functions

### country_flag()

Convert country code to flag emoji:

```php
echo country_flag('US'); // 🇺🇸
echo country_flag('GB'); // 🇬🇧
echo country_flag('FR'); // 🇫🇷
```

**Parameters:**
- `$countryCode` (string) - ISO 3166-1 alpha-2 code

**Returns:** Flag emoji or empty string if invalid.

## Function Reference Table

| Function | Purpose | Returns |
|----------|---------|---------|
| `analytics()` | Get Analytics instance | Analytics |
| `analyticsEnabled()` | Check if enabled | bool |
| `getAnalyticsConfig()` | Get config value | mixed |
| `trackPageView()` | Track page view | void |
| `trackEvent()` | Track event | void |
| `analyticsTrackForm()` | Track form | void |
| `analyticsTrackPurchase()` | Track purchase | void |
| `analyticsTrackConversion()` | Track conversion | void |
| `analyticsStats()` | Get statistics | array |
| `analyticsPageViews()` | Get page views | Collection |
| `analyticsVisitors()` | Get visitor count | int |
| `analyticsTopPages()` | Get top pages | Collection |
| `analyticsTrafficSources()` | Get sources | Collection |
| `analyticsRealtime()` | Get realtime data | array |
| `dateRangeLastDays()` | Create date range | DateRange |
| `dateRangeToday()` | Today's range | DateRange |
| `dateRangeThisMonth()` | Month's range | DateRange |
| `analyticsSite()` | Get current site | ?Site |
| `analyticsTenantId()` | Get tenant ID | int\|string\|null |
| `analyticsHasConsent()` | Check consent | bool |
| `analyticsGrantConsent()` | Grant consent | void |
| `analyticsRevokeConsent()` | Revoke consent | void |
| `analyticsConsentStatus()` | Get consent status | array |
| `country_flag()` | Get flag emoji | string |
