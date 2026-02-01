---
title: API Overview
---

# API Reference

This section provides detailed documentation of the ArtisanPack UI Analytics API, including models, services, data objects, events, and contracts.

## Reference Guides

- [Models](Api-Models) - Eloquent models for analytics data
- [Services](Api-Services) - Service classes for analytics operations
- [Data Objects](Api-Data-Objects) - Data Transfer Objects (DTOs)
- [Events](Api-Events) - Laravel events dispatched by the package
- [Contracts](Api-Contracts) - Interfaces for custom implementations

## Quick Reference

### Core Classes

| Class | Purpose |
|-------|---------|
| `Analytics` | Main analytics manager |
| `AnalyticsQuery` | Query builder for analytics data |
| `TrackingService` | Handles tracking operations |
| `SessionManager` | Session management |
| `ConsentService` | GDPR consent handling |

### Models

| Model | Table | Description |
|-------|-------|-------------|
| `Site` | `analytics_sites` | Multi-tenant sites |
| `Visitor` | `analytics_visitors` | Unique visitors |
| `Session` | `analytics_sessions` | Visit sessions |
| `PageView` | `analytics_page_views` | Page view records |
| `Event` | `analytics_events` | Custom events |
| `Goal` | `analytics_goals` | Conversion goals |
| `Conversion` | `analytics_conversions` | Goal conversions |
| `Consent` | `analytics_consents` | Consent records |
| `Aggregate` | `analytics_aggregates` | Aggregated data |

### Data Objects

| Class | Purpose |
|-------|---------|
| `PageViewData` | Page view tracking data |
| `EventData` | Event tracking data |
| `SessionData` | Session information |
| `VisitorData` | Visitor information |
| `DateRange` | Date range for queries |
| `DeviceInfo` | Device/browser information |

### Facades

```php
use ArtisanPackUI\Analytics\Facades\Analytics;

// Track page view
Analytics::trackPageView($pageViewData);

// Track event
Analytics::trackEvent($eventData);

// Check if tracking is allowed
Analytics::canTrack();

// Get a specific provider
Analytics::provider('local');
```

## Namespace Structure

```
ArtisanPackUI\Analytics\
├── Analytics                    # Main manager class
├── AnalyticsServiceProvider     # Service provider
├── Contracts\                   # Interfaces
│   ├── AnalyticsProviderInterface
│   ├── SiteResolverInterface
│   └── TenantResolverInterface
├── Data\                        # DTOs
│   ├── DateRange
│   ├── DeviceInfo
│   ├── EventData
│   ├── PageViewData
│   ├── SessionData
│   └── VisitorData
├── Events\                      # Laravel events
│   ├── PageViewRecorded
│   ├── EventTracked
│   └── ...
├── Facades\                     # Facades
│   └── Analytics
├── Http\
│   ├── Controllers\
│   ├── Livewire\               # Livewire components
│   └── Middleware\
├── Models\                      # Eloquent models
│   ├── Aggregate
│   ├── Consent
│   ├── Conversion
│   ├── Event
│   ├── Goal
│   ├── PageView
│   ├── Session
│   ├── Site
│   └── Visitor
├── Providers\                   # Analytics providers
│   ├── LocalProvider
│   ├── GoogleProvider
│   └── PlausibleProvider
├── Resolvers\                   # Site resolvers
│   ├── ApiKeyResolver
│   ├── DomainResolver
│   ├── HeaderResolver
│   └── SubdomainResolver
└── Services\                    # Service classes
    ├── AnalyticsQuery
    ├── ConsentService
    ├── SessionManager
    ├── TenantManager
    ├── TrackingService
    └── ...
```

## Using the API

### Direct Model Access

```php
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Event;

// Query page views
$views = PageView::query()
    ->whereBetween('created_at', [$start, $end])
    ->forPath('/products')
    ->get();

// Query events
$events = Event::query()
    ->where('name', 'purchase')
    ->sum('value');
```

### Using Services

```php
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use ArtisanPackUI\Analytics\Data\DateRange;

$query = app(AnalyticsQuery::class);
$range = DateRange::last30Days();

$stats = $query->getStats($range);
$topPages = $query->getTopPages($range, 10);
```

### Using Helper Functions

```php
// Tracking
trackPageView('/page', 'Title');
trackEvent('click', ['button' => 'submit']);

// Querying
$stats = analyticsStats(DateRange::last7Days());
$visitors = analyticsVisitors(DateRange::today());
```

## Extending the Package

### Custom Provider

```php
use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;

class CustomProvider implements AnalyticsProviderInterface
{
    public function trackPageView(PageViewData $data): void
    {
        // Custom implementation
    }

    public function trackEvent(EventData $data): void
    {
        // Custom implementation
    }
}
```

### Custom Site Resolver

```php
use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

class CustomResolver implements SiteResolverInterface
{
    public function resolve(Request $request): ?Site
    {
        // Custom resolution logic
    }

    public function getPriority(): int
    {
        return 50;
    }
}
```

## Error Handling

The package throws specific exceptions:

```php
use ArtisanPackUI\Analytics\Exceptions\AnalyticsException;
use ArtisanPackUI\Analytics\Exceptions\InvalidProviderException;
use ArtisanPackUI\Analytics\Exceptions\TrackingDisabledException;

try {
    trackEvent('test');
} catch (TrackingDisabledException $e) {
    // Tracking is disabled
} catch (AnalyticsException $e) {
    // General analytics error
}
```
