---
title: Visitors Chart
---

# Visitors Chart Component

The Visitors Chart component displays a line chart of page views and visitors over time using Chart.js.

## Prerequisites

Chart.js must be included in your application:

```blade
{{-- Via CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

Or via npm:

```bash
npm install chart.js
```

```javascript
// app.js
import Chart from 'chart.js/auto';
window.Chart = Chart;
```

## Basic Usage

```blade
<livewire:artisanpack-analytics::visitors-chart />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `dateRangePreset` | ?string | `'last7days'` | Date range preset |
| `siteId` | ?int | `null` | Site ID for multi-tenant |
| `granularity` | string | `'day'` | Time grouping |
| `metrics` | array | `['pageviews', 'visitors']` | Metrics to display |
| `height` | int | `300` | Chart height in pixels |

### Granularity Options

- `'hour'` - Hourly data points
- `'day'` - Daily data points (default)
- `'week'` - Weekly data points
- `'month'` - Monthly data points

## Usage Examples

### With Monthly Granularity

```blade
<livewire:artisanpack-analytics::visitors-chart
    granularity="month"
    date-range-preset="thisYear"
/>
```

### Visitors Only

```blade
<livewire:artisanpack-analytics::visitors-chart
    :metrics="['visitors']"
/>
```

### Custom Height

```blade
<livewire:artisanpack-analytics::visitors-chart
    :height="400"
/>
```

### Hourly View for Today

```blade
<livewire:artisanpack-analytics::visitors-chart
    granularity="hour"
    date-range-preset="today"
/>
```

## Chart Data Structure

```php
$this->chartData = [
    'labels' => ['Jan 1', 'Jan 2', 'Jan 3', ...],
    'datasets' => [
        [
            'label' => 'Page Views',
            'data' => [100, 150, 120, ...],
            'borderColor' => 'rgb(59, 130, 246)',
            'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
            'fill' => true,
            'tension' => 0.4,
        ],
        [
            'label' => 'Visitors',
            'data' => [80, 90, 75, ...],
            'borderColor' => 'rgb(16, 185, 129)',
            'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
            'fill' => true,
            'tension' => 0.4,
        ],
    ],
];
```

## Methods

### loadChartData()

Refresh the chart data:

```php
$this->loadChartData();
```

### setGranularity()

Change the time granularity:

```php
$this->setGranularity('week');
```

### refreshData()

Called when `refresh-analytics-widgets` event is dispatched.

### getChartConfig()

Get the full Chart.js configuration:

```php
$config = $this->getChartConfig();
// Returns type, data, and options for Chart.js
```

## Events

### Listens For

| Event | Action |
|-------|--------|
| `refresh-analytics-widgets` | Calls `refreshData()` |

## Customization

### Publishing Views

```bash
php artisan vendor:publish --tag=analytics-views
```

Edit `resources/views/vendor/artisanpack-analytics/livewire/widgets/visitors-chart.blade.php`.

### Custom Chart Colors

Modify the view to use custom colors:

```blade
<div
    x-data="{
        chart: null,
        init() {
            this.chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: @js($chartData),
                options: {
                    // Custom options
                    plugins: {
                        legend: {
                            labels: {
                                color: 'rgb(156, 163, 175)'
                            }
                        }
                    }
                }
            });
        }
    }"
    wire:ignore
>
    <canvas x-ref="canvas" style="height: {{ $height }}px"></canvas>
</div>
```

### Granularity Selector

Add a granularity selector to the chart:

```blade
<div class="mb-4">
    <select wire:model.live="granularity" class="select select-bordered">
        <option value="hour">Hourly</option>
        <option value="day">Daily</option>
        <option value="week">Weekly</option>
        <option value="month">Monthly</option>
    </select>
</div>

<livewire:artisanpack-analytics::visitors-chart
    :granularity="$granularity"
/>
```

## Integration Examples

### Dashboard Layout

```blade
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Chart spans 2 columns --}}
    <div class="lg:col-span-2">
        <livewire:artisanpack-analytics::visitors-chart />
    </div>

    {{-- Stats in sidebar --}}
    <div>
        <livewire:artisanpack-analytics::stats-cards
            :visible-stats="['visitors', 'pageviews']"
        />
    </div>
</div>
```

### Multiple Charts

```blade
{{-- Overview chart --}}
<livewire:artisanpack-analytics::visitors-chart
    :key="'overview-chart'"
    granularity="day"
/>

{{-- Detailed hourly chart --}}
<livewire:artisanpack-analytics::visitors-chart
    :key="'hourly-chart'"
    granularity="hour"
    date-range-preset="today"
    :height="200"
/>
```

## Performance Tips

1. **Use appropriate granularity**: Daily for 30+ days, hourly for single day
2. **Limit date range**: Large ranges with hourly granularity can be slow
3. **Use wire:ignore**: Prevent unnecessary chart re-renders

```blade
<div wire:ignore>
    <canvas id="chart"></canvas>
</div>
```
