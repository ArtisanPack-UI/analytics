---
title: API Resources
---

# API Resources

> **Since 1.1.0**

Laravel API Resources that transform analytics data into consistent JSON structures. These resources are used by the `InertiaDashboardController` to serialize data as Inertia page props and can also be used in custom API endpoints.

## Overview

All resources extend `Illuminate\Http\Resources\Json\JsonResource` and provide a standardized `toArray()` transformation with safe defaults (empty strings for text, `0` for integers, `0.0` for floats).

| Resource | Purpose |
|----------|---------|
| `StatsResource` | Overall analytics statistics |
| `PageViewTimeSeriesResource` | Time series chart data |
| `TopPageResource` | Most viewed pages |
| `TrafficSourceResource` | Traffic source breakdown |
| `BrowserBreakdownResource` | Browser usage breakdown |
| `CountryBreakdownResource` | Geographic breakdown |
| `DeviceBreakdownResource` | Device type breakdown |
| `EventBreakdownResource` | Custom event breakdown |

## StatsResource

Transforms overall analytics statistics.

**Output format:**

```json
{
    "pageviews": 12500,
    "visitors": 3200,
    "sessions": 4100,
    "bounce_rate": 45.2,
    "avg_session_duration": 185,
    "pages_per_session": 3.2,
    "realtime_visitors": 42,
    "comparison": null
}
```

**Usage:**

```php
use ArtisanPackUI\Analytics\Http\Resources\StatsResource;

$stats = $analyticsQuery->getStats( $dateRange, true );

return new StatsResource( $stats );
```

## PageViewTimeSeriesResource

Transforms time series data for chart visualization.

**Output format:**

```json
{
    "date": "2025-01-15",
    "pageviews": 450,
    "visitors": 120
}
```

**Usage:**

```php
use ArtisanPackUI\Analytics\Http\Resources\PageViewTimeSeriesResource;

$chartData = $analyticsQuery->getPageViews( $dateRange, 'day' );

return PageViewTimeSeriesResource::collection( $chartData );
```

## TopPageResource

Transforms top page data with view counts.

**Output format:**

```json
{
    "path": "/products",
    "title": "Products",
    "views": 1250,
    "unique_views": 890
}
```

**Usage:**

```php
use ArtisanPackUI\Analytics\Http\Resources\TopPageResource;

$topPages = $analyticsQuery->getTopPages( $dateRange, 10 );

return TopPageResource::collection( $topPages );
```

## TrafficSourceResource

Transforms traffic source data with session and visitor counts.

**Output format:**

```json
{
    "source": "google",
    "medium": "organic",
    "sessions": 850,
    "visitors": 620
}
```

**Usage:**

```php
use ArtisanPackUI\Analytics\Http\Resources\TrafficSourceResource;

$sources = $analyticsQuery->getTrafficSources( $dateRange, 10 );

return TrafficSourceResource::collection( $sources );
```

## BrowserBreakdownResource

Transforms browser usage breakdown data.

**Output format:**

```json
{
    "browser": "Chrome",
    "version": "120",
    "sessions": 2100,
    "percentage": 51.2
}
```

**Usage:**

```php
use ArtisanPackUI\Analytics\Http\Resources\BrowserBreakdownResource;

$browsers = $analyticsQuery->getBrowserBreakdown( $dateRange, 10 );

return BrowserBreakdownResource::collection( $browsers );
```

## CountryBreakdownResource

Transforms geographic breakdown data.

**Output format:**

```json
{
    "country": "United States",
    "country_code": "US",
    "sessions": 1800,
    "percentage": 43.9
}
```

**Usage:**

```php
use ArtisanPackUI\Analytics\Http\Resources\CountryBreakdownResource;

$countries = $analyticsQuery->getCountryBreakdown( $dateRange, 10 );

return CountryBreakdownResource::collection( $countries );
```

## DeviceBreakdownResource

Transforms device type breakdown data.

**Output format:**

```json
{
    "device_type": "desktop",
    "sessions": 2500,
    "percentage": 61.0
}
```

**Usage:**

```php
use ArtisanPackUI\Analytics\Http\Resources\DeviceBreakdownResource;

$devices = $analyticsQuery->getDeviceBreakdown( $dateRange );

return DeviceBreakdownResource::collection( $devices );
```

## EventBreakdownResource

Transforms custom event breakdown data.

**Output format:**

```json
{
    "name": "purchase",
    "category": "ecommerce",
    "count": 340,
    "total_value": 28500.00,
    "percentage": 12.5
}
```

**Usage:**

```php
use ArtisanPackUI\Analytics\Http\Resources\EventBreakdownResource;

$events = $analyticsQuery->getEventBreakdown( $dateRange, 10 );

return EventBreakdownResource::collection( $events );
```

## Using Resources in Custom Endpoints

You can use these resources in your own controllers to build custom analytics API endpoints:

```php
use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Http\Resources\StatsResource;
use ArtisanPackUI\Analytics\Http\Resources\TopPageResource;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;

class CustomAnalyticsController extends Controller
{
    public function summary( AnalyticsQuery $query ): JsonResponse
    {
        $range = DateRange::last30Days();

        return response()->json( [
            'stats'    => new StatsResource( $query->getStats( $range ) ),
            'topPages' => TopPageResource::collection( $query->getTopPages( $range, 5 ) ),
        ] );
    }
}
```
