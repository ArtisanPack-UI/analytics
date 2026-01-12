---
title: Components Overview
---

# Livewire Components

ArtisanPack UI Analytics provides pre-built Livewire components for displaying analytics data in your application.

## Available Components

| Component | Description |
|-----------|-------------|
| [Analytics Dashboard](./components/analytics-dashboard.md) | Full-featured dashboard with all widgets |
| [Stats Cards](./components/stats-cards.md) | Key metrics in card format |
| [Visitors Chart](./components/visitors-chart.md) | Line chart of visitors over time |
| [Top Pages](./components/top-pages.md) | Table of most viewed pages |
| [Traffic Sources](./components/traffic-sources.md) | Breakdown of traffic sources |
| [Realtime Visitors](./components/realtime-visitors.md) | Live visitor count widget |

## Quick Usage

### Full Dashboard

```blade
<livewire:artisanpack-analytics::analytics-dashboard />
```

### Individual Widgets

```blade
{{-- Stats cards --}}
<livewire:artisanpack-analytics::stats-cards />

{{-- Visitors chart --}}
<livewire:artisanpack-analytics::visitors-chart />

{{-- Top pages table --}}
<livewire:artisanpack-analytics::top-pages />

{{-- Traffic sources --}}
<livewire:artisanpack-analytics::traffic-sources />

{{-- Real-time visitors --}}
<livewire:artisanpack-analytics::realtime-visitors />
```

## Common Properties

All analytics components share these common properties:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `dateRangePreset` | ?string | `'last7days'` | Date range preset |
| `siteId` | ?int | `null` | Site ID for multi-tenant |

### Date Range Presets

Available presets:
- `'today'`
- `'yesterday'`
- `'last7days'`
- `'last30days'`
- `'last90days'`
- `'thisWeek'`
- `'lastWeek'`
- `'thisMonth'`
- `'lastMonth'`
- `'thisYear'`

## Customization

### Using Props

```blade
<livewire:artisanpack-analytics::stats-cards
    date-range-preset="last30days"
    :site-id="$siteId"
    :show-comparison="true"
/>
```

### Listening for Events

Components dispatch events you can listen for:

```blade
<div x-data @analytics-data-loaded.window="handleDataLoaded($event.detail)">
    <livewire:artisanpack-analytics::stats-cards />
</div>
```

### Refreshing Data

Dispatch the refresh event to update all widgets:

```php
// From a Livewire component
$this->dispatch('refresh-analytics-widgets');
```

```javascript
// From JavaScript
Livewire.dispatch('refresh-analytics-widgets');
```

## Styling

Components use Tailwind CSS and daisyUI for styling. They respect your application's theme and dark mode settings.

### Custom Styling

Override styles using Tailwind's `@apply` or by publishing and modifying the views:

```bash
php artisan vendor:publish --tag=analytics-views
```

Views will be published to `resources/views/vendor/artisanpack-analytics/`.

## Chart.js Integration

The Visitors Chart component requires Chart.js. Include it in your layout:

```blade
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

Or install via npm:

```bash
npm install chart.js
```

```javascript
// In your app.js
import Chart from 'chart.js/auto';
window.Chart = Chart;
```

## Multi-Tenant Usage

For multi-tenant applications, pass the site ID:

```blade
<livewire:artisanpack-analytics::analytics-dashboard
    :site-id="$currentSite->id"
/>
```

Or use the dedicated multi-tenant dashboard:

```blade
<livewire:artisanpack-analytics::multi-tenant-dashboard />
```

## Building Custom Widgets

Create custom widgets using the `WithAnalyticsWidget` trait:

```php
use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Livewire\Component;

class CustomWidget extends Component
{
    use WithAnalyticsWidget;

    public function mount(?string $dateRangePreset = null, ?int $siteId = null): void
    {
        $this->initializeWidget($dateRangePreset, $siteId);
        $this->loadData();
    }

    public function loadData(): void
    {
        $query = $this->getAnalyticsQuery();
        $range = $this->getDateRange();
        $filters = $this->getFilters();

        // Use query methods...
        $this->data = $query->getStats($range, true, $filters);
    }

    public function render()
    {
        return view('livewire.custom-widget');
    }
}
```

## Next Steps

- [Analytics Dashboard](./components/analytics-dashboard.md) - Explore the full dashboard
- [Configuration](./installation/configuration.md) - Configure dashboard settings
- [Multi-Tenancy](./advanced/multi-tenancy.md) - Set up multi-tenant analytics
