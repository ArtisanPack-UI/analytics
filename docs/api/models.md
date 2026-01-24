---
title: Models
---

# Models

ArtisanPack UI Analytics provides several Eloquent models for interacting with analytics data.

## Site

Represents a site in multi-tenant configurations.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `name` | string | Site name |
| `domain` | string | Site domain |
| `api_key` | ?string | API key for tracking |
| `settings` | ?array | Site-specific settings |
| `is_active` | bool | Whether site is active |
| `tenant_id` | ?int\|string | Tenant identifier |
| `created_at` | Carbon | Creation timestamp |
| `updated_at` | Carbon | Update timestamp |

### Relationships

```php
$site->visitors;    // HasMany Visitor
$site->sessions;    // HasMany Session
$site->pageViews;   // HasMany PageView
$site->events;      // HasMany Event
$site->goals;       // HasMany Goal
```

### Scopes

```php
Site::active()->get();           // Active sites only
Site::forTenant($tenantId)->get(); // Filter by tenant
```

---

## Visitor

Represents a unique visitor.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `site_id` | ?int | Associated site |
| `fingerprint` | string | Unique visitor identifier |
| `first_seen_at` | Carbon | First visit timestamp |
| `last_seen_at` | Carbon | Last visit timestamp |
| `visit_count` | int | Number of visits |
| `country` | ?string | Country code |
| `city` | ?string | City name |
| `region` | ?string | Region/state |

### Relationships

```php
$visitor->site;       // BelongsTo Site
$visitor->sessions;   // HasMany Session
$visitor->pageViews;  // HasMany PageView
$visitor->events;     // HasMany Event
$visitor->consents;   // HasMany Consent
```

### Scopes

```php
Visitor::forSite($siteId)->get();
Visitor::forTenant($tenantId)->get();
Visitor::seenAfter($date)->get();
```

---

## Session

Represents a visit session.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `site_id` | ?int | Associated site |
| `visitor_id` | int | Associated visitor |
| `session_id` | string | Unique session identifier |
| `started_at` | Carbon | Session start time |
| `ended_at` | ?Carbon | Session end time |
| `duration` | ?int | Duration in seconds |
| `page_views` | int | Number of page views |
| `is_bounce` | bool | Single-page session |
| `entry_page` | ?string | First page viewed |
| `exit_page` | ?string | Last page viewed |
| `referrer` | ?string | Traffic referrer |
| `utm_source` | ?string | UTM source |
| `utm_medium` | ?string | UTM medium |
| `utm_campaign` | ?string | UTM campaign |
| `device_type` | ?string | desktop/mobile/tablet |
| `browser` | ?string | Browser name |
| `browser_version` | ?string | Browser version |
| `os` | ?string | Operating system |
| `screen_resolution` | ?string | Screen size |

### Relationships

```php
$session->site;        // BelongsTo Site
$session->visitor;     // BelongsTo Visitor
$session->pageViews;   // HasMany PageView
$session->events;      // HasMany Event
$session->conversions; // HasMany Conversion
```

### Scopes

```php
Session::active()->get();              // Not ended
Session::bounced()->get();             // Single-page sessions
Session::forSite($siteId)->get();
Session::fromSource($source)->get();
Session::onDevice($type)->get();       // 'desktop', 'mobile', 'tablet'
```

### Methods

```php
$session->end();        // Mark session as ended
$session->extend();     // Update last activity
$session->getDuration(); // Get session duration
```

---

## PageView

Represents a page view.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `site_id` | ?int | Associated site |
| `session_id` | string | Session identifier |
| `visitor_id` | string | Visitor identifier |
| `path` | string | Page path |
| `title` | ?string | Page title |
| `hash` | ?string | URL hash fragment |
| `query_string` | ?string | Query parameters |
| `referrer_path` | ?string | Previous page |
| `time_on_page` | ?int | Time in seconds |
| `engaged_time` | ?int | Active engagement time |
| `scroll_depth` | ?int | Max scroll percentage |
| `custom_data` | ?array | Additional data |
| `created_at` | Carbon | View timestamp |

### Relationships

```php
$pageView->site;     // BelongsTo Site
$pageView->session;  // BelongsTo Session
$pageView->visitor;  // BelongsTo Visitor
$pageView->events;   // HasMany Event
```

### Scopes

```php
PageView::forPath('/products')->get();
PageView::forPaths(['/products', '/services'])->get();
PageView::forSite($siteId)->get();
PageView::withEngagement()->get();  // Has engagement data
```

---

## Event

Represents a custom event.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `site_id` | ?int | Associated site |
| `session_id` | string | Session identifier |
| `visitor_id` | string | Visitor identifier |
| `page_view_id` | ?int | Associated page view |
| `name` | string | Event name |
| `category` | ?string | Event category |
| `value` | ?float | Numeric value |
| `properties` | ?array | Event properties |
| `source_package` | ?string | Originating package |
| `created_at` | Carbon | Event timestamp |

### Relationships

```php
$event->site;      // BelongsTo Site
$event->session;   // BelongsTo Session
$event->visitor;   // BelongsTo Visitor
$event->pageView;  // BelongsTo PageView
```

### Scopes

```php
Event::named('purchase')->get();
Event::inCategory('ecommerce')->get();
Event::forSite($siteId)->get();
Event::withValue()->get();  // Has numeric value
```

---

## Goal

Represents a conversion goal.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `site_id` | ?int | Associated site |
| `name` | string | Goal name |
| `type` | string | url/event/engagement |
| `target` | string | Target URL/event/conditions |
| `value` | ?float | Default conversion value |
| `is_active` | bool | Whether goal is active |
| `created_at` | Carbon | Creation timestamp |
| `updated_at` | Carbon | Update timestamp |

### Relationships

```php
$goal->site;        // BelongsTo Site
$goal->conversions; // HasMany Conversion
```

### Scopes

```php
Goal::active()->get();
Goal::ofType('url')->get();
Goal::forSite($siteId)->get();
```

---

## Conversion

Represents a goal conversion.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `goal_id` | int | Associated goal |
| `session_id` | int | Associated session |
| `visitor_id` | int | Associated visitor |
| `value` | ?float | Conversion value |
| `properties` | ?array | Conversion properties |
| `created_at` | Carbon | Conversion timestamp |

### Relationships

```php
$conversion->goal;    // BelongsTo Goal
$conversion->session; // BelongsTo Session
$conversion->visitor; // BelongsTo Visitor
```

---

## Consent

Represents a visitor's consent record.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `site_id` | ?int | Associated site |
| `visitor_id` | int | Associated visitor |
| `category` | string | Consent category |
| `granted` | bool | Whether granted |
| `granted_at` | ?Carbon | Grant timestamp |
| `revoked_at` | ?Carbon | Revoke timestamp |
| `ip_address` | ?string | IP at consent time |

### Relationships

```php
$consent->site;    // BelongsTo Site
$consent->visitor; // BelongsTo Visitor
```

---

## Aggregate

Represents aggregated historical data.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Primary key |
| `site_id` | ?int | Associated site |
| `date` | Carbon | Aggregation date |
| `period` | string | day/week/month |
| `metric` | string | Metric name |
| `dimension` | ?string | Dimension name |
| `dimension_value` | ?string | Dimension value |
| `value` | int | Aggregated value |

### Scopes

```php
Aggregate::forMetric('pageviews')->get();
Aggregate::forPeriod('day')->get();
Aggregate::forDimension('path', '/products')->get();
```
