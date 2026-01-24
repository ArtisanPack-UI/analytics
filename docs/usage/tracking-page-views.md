---
title: Tracking Page Views
---

# Tracking Page Views

Page views are the foundation of analytics tracking. ArtisanPack UI Analytics provides both automatic and manual page view tracking.

## Automatic Tracking

### Adding the Tracking Script

Add the `@analyticsScripts` directive to your main layout file:

```blade
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'My App' }}</title>
</head>
<body>
    {{ $slot }}

    @analyticsScripts
</body>
</html>
```

The JavaScript tracker automatically:
- Records page views on page load
- Tracks session duration
- Captures device and browser information
- Detects traffic sources (referrers, UTM parameters)
- Handles hash changes (if enabled)

### Script Configuration

The tracking script respects your configuration settings:

```php
// config/artisanpack/analytics.php
'tracker' => [
    'track_hash_changes' => env('ANALYTICS_TRACK_HASH', false),
    'track_outbound_links' => env('ANALYTICS_TRACK_OUTBOUND', true),
    'track_file_downloads' => env('ANALYTICS_TRACK_DOWNLOADS', true),
],
```

### SPA Support

For single-page applications, enable hash change tracking:

```dotenv
ANALYTICS_TRACK_HASH=true
```

Or manually track navigation:

```javascript
// After route change
analytics.trackPageView(window.location.pathname, document.title);
```

## Manual Tracking

### Using Helper Functions

Track page views from PHP code:

```php
use function trackPageView;

// Basic page view
trackPageView('/products', 'Products Page');

// With custom data
trackPageView('/products/123', 'Widget Pro', [
    'category' => 'widgets',
    'product_id' => 123,
]);
```

### Using the Facade

```php
use ArtisanPackUI\Analytics\Facades\Analytics;
use ArtisanPackUI\Analytics\Data\PageViewData;

$data = new PageViewData(
    path: '/products',
    title: 'Products Page',
    customData: ['category' => 'widgets'],
);

Analytics::trackPageView($data);
```

### Using the Analytics Instance

```php
$analytics = app('analytics');
$analytics->trackPageView($data);
```

## PageViewData Object

The `PageViewData` object contains all information about a page view:

```php
use ArtisanPackUI\Analytics\Data\PageViewData;

$data = new PageViewData(
    path: '/products/widget',
    title: 'Widget Product Page',
    referrer: 'https://google.com',
    customData: [
        'product_id' => 123,
        'category' => 'widgets',
    ],
);
```

### Available Properties

| Property | Type | Description |
|----------|------|-------------|
| `path` | string | The page URL path (required) |
| `title` | ?string | The page title |
| `referrer` | ?string | The referrer URL |
| `customData` | ?array | Additional custom data |

## What Gets Tracked

Each page view records:

| Data Point | Description |
|------------|-------------|
| Path | URL path of the viewed page |
| Title | Page title |
| Referrer | Where the visitor came from |
| Session ID | Links to the visitor's session |
| Visitor ID | Links to the unique visitor |
| Timestamp | When the page was viewed |
| Custom Data | Any additional data you provide |

## Excluding Pages

Configure paths to exclude from tracking:

```php
// config/artisanpack/analytics.php
'privacy' => [
    'excluded_paths' => [
        '/admin/*',
        '/api/*',
        '/_debugbar/*',
        '/telescope/*',
    ],
],
```

## JavaScript API

The tracking script provides a global `analytics` object:

```javascript
// Track a page view
analytics.trackPageView('/page-path', 'Page Title');

// Track with custom data
analytics.trackPageView('/page-path', 'Page Title', {
    category: 'blog',
    author: 'John'
});
```

## Verifying Tracking

### Check the Dashboard

Visit `/analytics/dashboard` to see tracked page views.

### Check the Database

```php
use ArtisanPackUI\Analytics\Models\PageView;

$recentViews = PageView::latest()->take(10)->get();
```

### Debug Mode

Enable debug logging:

```php
// In your AppServiceProvider or a test
\Log::debug('Page views today: ' . PageView::whereDate('created_at', today())->count());
```

## Best Practices

1. **Use meaningful page titles** - Titles appear in reports and make data more readable
2. **Exclude admin routes** - Don't pollute data with internal traffic
3. **Track custom data sparingly** - Only add data you'll actually analyze
4. **Test in development** - Verify tracking works before deploying
5. **Monitor queue workers** - If using queue processing, ensure workers are running
