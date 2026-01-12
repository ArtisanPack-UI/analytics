---
title: Goals & Conversions
---

# Goals & Conversions

Goals help you track important actions users take on your site. A conversion is recorded each time a goal is achieved.

## Goal Types

ArtisanPack UI Analytics supports three types of goals:

| Type | Description | Example |
|------|-------------|---------|
| `url` | Triggered when a specific URL is visited | Thank you page after purchase |
| `event` | Triggered when a specific event occurs | Form submission, button click |
| `engagement` | Triggered based on session metrics | Time on site, pages viewed |

## Creating Goals

### Via the Dashboard

Navigate to your analytics dashboard and use the goals management interface.

### Via Code

```php
use ArtisanPackUI\Analytics\Models\Goal;

// URL-based goal
Goal::create([
    'name' => 'Purchase Complete',
    'type' => 'url',
    'target' => '/checkout/success',
    'value' => 50.00, // Optional default value
    'is_active' => true,
]);

// Event-based goal
Goal::create([
    'name' => 'Newsletter Signup',
    'type' => 'event',
    'target' => 'newsletter_subscribe',
    'is_active' => true,
]);

// Engagement-based goal
Goal::create([
    'name' => 'Engaged Visitor',
    'type' => 'engagement',
    'target' => json_encode([
        'min_pages' => 5,
        'min_duration' => 180, // seconds
    ]),
    'is_active' => true,
]);
```

## Goal Model

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `name` | string | Display name for the goal |
| `type` | string | `url`, `event`, or `engagement` |
| `target` | string | URL path, event name, or JSON conditions |
| `value` | ?float | Default conversion value |
| `is_active` | bool | Whether the goal is currently active |
| `site_id` | ?int | Site ID for multi-tenant setups |

### Relationships

```php
// Get all conversions for a goal
$goal->conversions;

// Get conversion count
$goal->conversions()->count();

// Get total conversion value
$goal->conversions()->sum('value');
```

## Tracking Conversions

### Automatic Conversions

URL and event goals are tracked automatically when:

- A page view matches a URL goal's target path
- An event name matches an event goal's target

### Manual Conversions

```php
use function analyticsTrackConversion;

// Track a conversion with default value
analyticsTrackConversion(goalId: 1);

// Track with custom value
analyticsTrackConversion(goalId: 1, value: 75.00);
```

### Via Event

```php
trackEvent('goal_conversion', [
    'goal_id' => 1,
], 75.00, 'conversions');
```

## Conversion Model

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `goal_id` | int | The goal that was converted |
| `session_id` | int | The session that converted |
| `visitor_id` | int | The visitor who converted |
| `value` | ?float | Conversion value |
| `properties` | ?array | Additional conversion data |

### Relationships

```php
// Get the goal
$conversion->goal;

// Get the session
$conversion->session;

// Get the visitor
$conversion->visitor;
```

## Querying Conversions

```php
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Data\DateRange;

// Get recent conversions
$conversions = Conversion::with(['goal', 'visitor'])
    ->latest()
    ->take(50)
    ->get();

// Get conversions for a date range
$range = DateRange::last30Days();
$conversions = Conversion::whereBetween('created_at', [
    $range->startDate,
    $range->endDate,
])->get();

// Get conversion rate
$sessions = Session::whereBetween('started_at', [
    $range->startDate,
    $range->endDate,
])->count();

$conversions = Conversion::whereBetween('created_at', [
    $range->startDate,
    $range->endDate,
])->distinct('session_id')->count();

$conversionRate = $sessions > 0 ? ($conversions / $sessions) * 100 : 0;
```

## Configuration

### Multiple Conversions Per Session

```php
// config/artisanpack/analytics.php
'goals' => [
    'allow_multiple_per_session' => false,
],
```

When `false`, only one conversion per goal per session is recorded.

### Caching

```php
// config/artisanpack/analytics.php
'goals' => [
    'cache_duration' => 300, // seconds
],
```

## URL Goal Patterns

URL goals support wildcards for flexible matching:

```php
// Exact match
Goal::create([
    'type' => 'url',
    'target' => '/checkout/success',
]);

// Wildcard match
Goal::create([
    'type' => 'url',
    'target' => '/products/*/purchase',
]);

// Query string match
Goal::create([
    'type' => 'url',
    'target' => '/confirm?status=success',
]);
```

## Engagement Goal Conditions

Engagement goals trigger based on session metrics:

```php
Goal::create([
    'type' => 'engagement',
    'target' => json_encode([
        'min_pages' => 5,           // Minimum pages viewed
        'min_duration' => 180,      // Minimum seconds on site
        'required_paths' => ['/pricing'], // Must visit these paths
    ]),
]);
```

## Dashboard Integration

The analytics dashboard displays:

- Total conversions by goal
- Conversion rate trends
- Top converting pages
- Conversion value totals

See [Analytics Dashboard](../components/analytics-dashboard.md) for details.

## Best Practices

1. **Start with key actions** - Focus on conversions that matter to your business
2. **Set meaningful values** - Assign values to understand ROI
3. **Use specific targets** - Be precise with URL and event matching
4. **Monitor regularly** - Review conversion rates and adjust goals
5. **A/B test** - Compare different approaches to improve conversion rates
6. **Segment analysis** - Analyze conversions by traffic source, device, etc.
