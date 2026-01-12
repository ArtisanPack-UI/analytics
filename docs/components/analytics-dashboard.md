---
title: Analytics Dashboard
---

# Analytics Dashboard Component

The Analytics Dashboard is a full-featured component that combines all analytics widgets with period selection, tab navigation, and export functionality.

## Basic Usage

```blade
<livewire:artisanpack-analytics::analytics-dashboard />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `dateRangePreset` | ?string | `'last7days'` | Initial date range |
| `siteId` | ?int | `null` | Site ID for multi-tenant |
| `activeTab` | string | `'overview'` | Initial active tab |

## Usage Examples

### With Custom Date Range

```blade
<livewire:artisanpack-analytics::analytics-dashboard
    date-range-preset="last30days"
/>
```

### With Site ID (Multi-Tenant)

```blade
<livewire:artisanpack-analytics::analytics-dashboard
    :site-id="$site->id"
/>
```

### Starting on Different Tab

```blade
<livewire:artisanpack-analytics::analytics-dashboard
    active-tab="traffic"
/>
```

## Dashboard Tabs

The dashboard includes four tabs:

| Tab | Description |
|-----|-------------|
| `overview` | Stats cards, visitors chart, quick summaries |
| `pages` | Top pages table with detailed metrics |
| `traffic` | Traffic sources and referrers |
| `audience` | Device, browser, and geographic breakdowns |

## Available Data

The dashboard component provides these data properties:

```php
// Statistics with comparison
$this->stats = [
    'pageviews' => 1234,
    'visitors' => 567,
    'sessions' => 890,
    'bounce_rate' => 45.5,
    'avg_session_duration' => 180,
    'comparison' => [
        'pageviews' => ['value' => 1100, 'change' => 12.2],
        // ...
    ],
];

// Chart data for Chart.js
$this->chartData = [
    'labels' => ['Jan 1', 'Jan 2', ...],
    'datasets' => [...],
];

// Collections
$this->topPages;         // Top viewed pages
$this->trafficSources;   // Traffic sources
$this->deviceBreakdown;  // Device types
$this->browserBreakdown; // Browsers
$this->countryBreakdown; // Countries
```

## Methods

### switchTab()

Switch to a different tab:

```php
$this->switchTab('traffic');
```

### refreshData()

Refresh all dashboard data:

```php
$this->refreshData();
```

Also dispatches `refresh-analytics-widgets` event to update child widgets.

### exportCsv()

Export dashboard data as CSV:

```blade
<button wire:click="exportCsv">Export CSV</button>
```

### exportJson()

Export dashboard data as JSON:

```blade
<button wire:click="exportJson">Export JSON</button>
```

## Events

### Dispatched Events

| Event | When | Payload |
|-------|------|---------|
| `refresh-analytics-widgets` | After `refreshData()` | None |

### Listening for Events

```blade
<div x-data @analytics-data-loaded.window="console.log('Data loaded')">
    <livewire:artisanpack-analytics::analytics-dashboard />
</div>
```

## Customization

### Publishing Views

```bash
php artisan vendor:publish --tag=analytics-views
```

Edit `resources/views/vendor/artisanpack-analytics/livewire/analytics-dashboard.blade.php`.

### Custom Layout

Wrap the dashboard in your layout:

```blade
<x-app-layout>
    <div class="max-w-7xl mx-auto py-6 px-4">
        <h1 class="text-2xl font-bold mb-6">Analytics</h1>
        <livewire:artisanpack-analytics::analytics-dashboard />
    </div>
</x-app-layout>
```

## Configuration

Dashboard behavior is configured in `config/artisanpack/analytics.php`:

```php
'dashboard' => [
    'default_date_range' => 30,     // Default days to show
    'cache_duration' => 300,         // Cache in seconds
    'realtime_enabled' => true,      // Show realtime widget
    'realtime_interval' => 30,       // Refresh interval
],
```

## Route Configuration

The dashboard route is configured by:

```php
'dashboard_route' => env('ANALYTICS_DASHBOARD_ROUTE', 'analytics'),
'dashboard_middleware' => ['web', 'auth'],
```

Access at: `https://yoursite.com/analytics`

To disable the built-in route:

```php
'dashboard_route' => null,
```

Then create your own route:

```php
// routes/web.php
Route::get('/my-analytics', function () {
    return view('analytics', [
        'site' => auth()->user()->site,
    ]);
})->middleware(['auth']);
```

## Embedding in Other Pages

Add the dashboard to any page:

```blade
{{-- resources/views/admin/analytics.blade.php --}}
@extends('layouts.admin')

@section('content')
    <div class="p-6">
        <livewire:artisanpack-analytics::analytics-dashboard
            :site-id="$siteId"
        />
    </div>
@endsection
```

## Performance Considerations

1. **Caching**: Dashboard queries are cached based on `cache_duration`
2. **Date Range**: Longer date ranges require more processing
3. **Real-time**: Disable if not needed to reduce server load

```php
// Disable realtime for better performance
'dashboard' => [
    'realtime_enabled' => false,
],
```
