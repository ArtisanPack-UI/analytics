---
title: Tracking Events
---

# Tracking Events

Events track specific user interactions and custom actions in your application. Use events to understand how users engage with your features.

## Basic Event Tracking

### Using Helper Functions

```php
use function trackEvent;

// Simple event
trackEvent('button_click');

// Event with properties
trackEvent('button_click', [
    'button_id' => 'cta-signup',
    'button_text' => 'Sign Up Now',
]);

// Event with value
trackEvent('donation', ['campaign' => 'summer-2024'], 50.00);

// Event with category
trackEvent('video_play', ['video_id' => '123'], null, 'engagement');
```

### Using the Facade

```php
use ArtisanPackUI\Analytics\Facades\Analytics;
use ArtisanPackUI\Analytics\Data\EventData;

$data = new EventData(
    name: 'button_click',
    properties: ['button_id' => 'cta-signup'],
    value: null,
    category: 'engagement',
);

Analytics::trackEvent($data);
```

### Shorthand Method

```php
Analytics::event('button_click', ['button_id' => 'cta'], 'engagement');
```

## Specialized Event Helpers

### Form Submissions

```php
use function analyticsTrackForm;

analyticsTrackForm('contact-form', [
    'source' => 'homepage',
    'fields_count' => 5,
]);
```

### Purchases/E-commerce

```php
use function analyticsTrackPurchase;

analyticsTrackPurchase(
    value: 149.99,
    currency: 'USD',
    items: [
        ['name' => 'Widget Pro', 'quantity' => 1, 'price' => 99.99],
        ['name' => 'Widget Basic', 'quantity' => 2, 'price' => 25.00],
    ],
    metadata: [
        'order_id' => 'ORD-12345',
        'coupon' => 'SUMMER10',
    ]
);
```

### Goal Conversions

```php
use function analyticsTrackConversion;

analyticsTrackConversion(goalId: 1, value: 50.00);
```

## EventData Object

The `EventData` object contains all information about an event:

```php
use ArtisanPackUI\Analytics\Data\EventData;

$data = new EventData(
    name: 'purchase',
    properties: [
        'product_id' => 123,
        'product_name' => 'Widget',
    ],
    value: 49.99,
    category: 'ecommerce',
);
```

### Available Properties

| Property | Type | Description |
|----------|------|-------------|
| `name` | string | The event name (required) |
| `properties` | ?array | Additional event properties |
| `value` | ?float | Numeric value (revenue, score, etc.) |
| `category` | ?string | Event category for grouping |

## JavaScript Event Tracking

The tracking script provides a global `analytics` object:

```javascript
// Simple event
analytics.trackEvent('button_click');

// With properties
analytics.trackEvent('video_play', {
    video_id: '123',
    video_title: 'Product Demo',
    duration: 120
});

// With value and category
analytics.trackEvent('donation', { campaign: 'charity' }, 25.00, 'fundraising');
```

### Auto-Tracked Events

When enabled in configuration, these events are tracked automatically:

| Event | Description | Config Key |
|-------|-------------|------------|
| `outbound_link` | Click on external link | `auto_track.outbound_links` |
| `file_download` | Click on download link | `auto_track.file_downloads` |
| `scroll_depth` | Scroll milestones (25%, 50%, 75%, 100%) | `auto_track.scroll_depth` |
| `video_engagement` | Video play/pause/complete | `auto_track.video_engagement` |

Configure auto-tracking:

```php
// config/artisanpack/analytics.php
'events' => [
    'auto_track' => [
        'outbound_links' => true,
        'file_downloads' => true,
        'scroll_depth' => true,
        'video_engagement' => false,
    ],
],
```

## Event Schema Validation

Define required properties for specific events:

```php
// config/artisanpack/analytics.php
'events' => [
    'schema' => [
        'purchase' => [
            'required' => ['order_id', 'total'],
        ],
        'form_submitted' => [
            'required' => ['form_id'],
        ],
    ],
],
```

Events missing required properties will be rejected.

## Event Limits

Configure limits to prevent abuse:

```php
// config/artisanpack/analytics.php
'events' => [
    'max_properties' => 25,            // Max properties per event
    'max_property_value_length' => 500, // Max characters per value
    'allowed_names' => [],              // Empty = allow all
],
```

## Common Event Patterns

### User Actions

```php
trackEvent('signup_started');
trackEvent('signup_completed', ['method' => 'email']);
trackEvent('login', ['method' => 'google']);
trackEvent('logout');
```

### Content Engagement

```php
trackEvent('article_read', [
    'article_id' => 123,
    'read_time' => 45, // seconds
]);

trackEvent('comment_posted', [
    'article_id' => 123,
    'comment_length' => 150,
]);
```

### E-commerce

```php
trackEvent('product_viewed', ['product_id' => 123]);
trackEvent('add_to_cart', ['product_id' => 123, 'quantity' => 2]);
trackEvent('checkout_started', ['cart_total' => 149.99]);
trackEvent('checkout_completed', ['order_id' => 'ORD-123'], 149.99);
```

### Search

```php
trackEvent('search', [
    'query' => 'widget',
    'results_count' => 15,
]);

trackEvent('search_click', [
    'query' => 'widget',
    'clicked_result' => 3,
]);
```

## Querying Events

```php
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Data\DateRange;

// Get recent events
$events = Event::latest()->take(100)->get();

// Get events by name
$signups = Event::where('name', 'signup_completed')
    ->whereBetween('created_at', [
        DateRange::last7Days()->startDate,
        DateRange::last7Days()->endDate,
    ])
    ->get();

// Get events with value
$purchases = Event::where('name', 'purchase')
    ->whereNotNull('value')
    ->sum('value');
```

## Best Practices

1. **Use consistent naming** - Stick to a naming convention (snake_case recommended)
2. **Keep events focused** - Track specific actions, not vague concepts
3. **Include context** - Add relevant properties to understand the action
4. **Use values wisely** - Reserve the `value` field for numeric metrics
5. **Categorize events** - Group related events by category
6. **Document your events** - Maintain a list of events and their meanings
7. **Avoid PII** - Never include personally identifiable information in events
