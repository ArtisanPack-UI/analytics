---
title: Traffic Sources
---

# Traffic Sources Component

The Traffic Sources component displays a breakdown of where your visitors are coming from.

## Basic Usage

```blade
<livewire:artisanpack-analytics::traffic-sources />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `dateRangePreset` | ?string | `'last7days'` | Date range preset |
| `siteId` | ?int | `null` | Site ID for multi-tenant |
| `limit` | int | `10` | Number of sources to show |

## Usage Examples

### Show More Sources

```blade
<livewire:artisanpack-analytics::traffic-sources
    :limit="20"
/>
```

### With Date Range

```blade
<livewire:artisanpack-analytics::traffic-sources
    date-range-preset="last30days"
/>
```

## Data Structure

Each source entry contains:

```php
[
    'source' => 'google',           // Traffic source
    'medium' => 'organic',          // Traffic medium
    'sessions' => 450,              // Number of sessions
    'visitors' => 380,              // Unique visitors
    'pageviews' => 1200,            // Total page views
    'bounce_rate' => 42.5,          // Bounce rate %
    'avg_session_duration' => 185,  // Seconds
    'percentage' => 25.5,           // % of total traffic
]
```

## Traffic Source Categories

### Source Types

| Source | Description |
|--------|-------------|
| `direct` | Direct visits (no referrer) |
| `google` | Google search |
| `bing` | Bing search |
| `facebook` | Facebook referrals |
| `twitter` | Twitter/X referrals |
| `linkedin` | LinkedIn referrals |
| `(other)` | Other referrers |

### Medium Types

| Medium | Description |
|--------|-------------|
| `(none)` | Direct traffic |
| `organic` | Organic search |
| `referral` | Referral from another site |
| `social` | Social media |
| `email` | Email campaigns |
| `cpc` | Paid search (cost per click) |
| `display` | Display advertising |

## Methods

### loadData()

Refresh the traffic sources data:

```php
$this->loadData();
```

### refreshData()

Called when `refresh-analytics-widgets` event is dispatched.

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

Edit `resources/views/vendor/artisanpack-analytics/livewire/widgets/traffic-sources.blade.php`.

### Custom Table Layout

Example custom view:

```blade
<div class="overflow-x-auto">
    <table class="table">
        <thead>
            <tr>
                <th>Source / Medium</th>
                <th class="text-right">Sessions</th>
                <th class="text-right">Visitors</th>
                <th class="text-right">% of Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sources as $source)
                <tr>
                    <td>
                        <div class="font-medium">{{ $source['source'] }}</div>
                        <div class="text-sm text-gray-500">{{ $source['medium'] }}</div>
                    </td>
                    <td class="text-right">{{ number_format($source['sessions']) }}</td>
                    <td class="text-right">{{ number_format($source['visitors']) }}</td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-2">
                            <div class="w-16 bg-gray-200 rounded-full h-2">
                                <div
                                    class="bg-primary h-2 rounded-full"
                                    style="width: {{ $source['percentage'] }}%"
                                ></div>
                            </div>
                            <span>{{ number_format($source['percentage'], 1) }}%</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-gray-500">
                        No traffic data available
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

### Pie Chart Visualization

Display sources as a pie chart:

```blade
<div x-data="{
    chart: null,
    init() {
        this.chart = new Chart(this.$refs.canvas, {
            type: 'doughnut',
            data: {
                labels: @js($sources->pluck('source')->toArray()),
                datasets: [{
                    data: @js($sources->pluck('sessions')->toArray()),
                    backgroundColor: [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)',
                        'rgb(139, 92, 246)',
                    ]
                }]
            }
        });
    }
}" wire:ignore>
    <canvas x-ref="canvas"></canvas>
</div>
```

## Integration Examples

### Source/Medium Filter

Filter analytics by specific source:

```blade
<div class="mb-4">
    <select wire:model.live="sourceFilter" class="select select-bordered">
        <option value="">All Sources</option>
        @foreach ($sources as $source)
            <option value="{{ $source['source'] }}">{{ $source['source'] }}</option>
        @endforeach
    </select>
</div>
```

### Combined with Other Widgets

```blade
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Traffic sources table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h3 class="card-title">Traffic Sources</h3>
            <livewire:artisanpack-analytics::traffic-sources :limit="5" />
        </div>
    </div>

    {{-- Geographic breakdown --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h3 class="card-title">Top Countries</h3>
            {{-- Country data from dashboard --}}
        </div>
    </div>
</div>
```

## UTM Parameter Tracking

Traffic sources automatically parse UTM parameters:

- `utm_source` → Source
- `utm_medium` → Medium
- `utm_campaign` → Campaign (available in extended data)

Example tracked URL:
```
https://yoursite.com/?utm_source=newsletter&utm_medium=email&utm_campaign=summer_sale
```

Results in:
```php
[
    'source' => 'newsletter',
    'medium' => 'email',
]
```
