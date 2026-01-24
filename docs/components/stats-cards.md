---
title: Stats Cards
---

# Stats Cards Component

The Stats Cards component displays key analytics metrics in a card grid format with optional comparison to the previous period.

## Basic Usage

```blade
<livewire:artisanpack-analytics::stats-cards />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `dateRangePreset` | ?string | `'last7days'` | Date range preset |
| `siteId` | ?int | `null` | Site ID for multi-tenant |
| `showComparison` | bool | `true` | Show comparison percentages |
| `visibleStats` | array | See below | Which stats to display |

### Default Visible Stats

```php
['pageviews', 'visitors', 'sessions', 'bounce_rate']
```

## Usage Examples

### With Comparison Disabled

```blade
<livewire:artisanpack-analytics::stats-cards
    :show-comparison="false"
/>
```

### Custom Stats Selection

```blade
<livewire:artisanpack-analytics::stats-cards
    :visible-stats="['visitors', 'sessions', 'avg_session_duration']"
/>
```

### With Date Range

```blade
<livewire:artisanpack-analytics::stats-cards
    date-range-preset="thisMonth"
/>
```

## Available Stats

| Key | Label | Format | Description |
|-----|-------|--------|-------------|
| `pageviews` | Page Views | Number | Total page views |
| `visitors` | Visitors | Number | Unique visitors |
| `sessions` | Sessions | Number | Total sessions |
| `bounce_rate` | Bounce Rate | Percentage | Single-page sessions |
| `avg_session_duration` | Avg. Session Duration | Duration | Average time on site |
| `pages_per_session` | Pages / Session | Decimal | Average pages viewed |
| `realtime_visitors` | Active Now | Number | Current active visitors |

## Stats Data Structure

```php
$this->stats = [
    'pageviews' => 1234,
    'visitors' => 567,
    'sessions' => 890,
    'bounce_rate' => 45.5,
    'avg_session_duration' => 180, // seconds
    'pages_per_session' => 2.3,
    'comparison' => [
        'pageviews' => [
            'value' => 1100,    // Previous period value
            'change' => 12.2,   // Percentage change
        ],
        'visitors' => [
            'value' => 500,
            'change' => 13.4,
        ],
        // ...
    ],
];
```

## Methods

### loadStats()

Refresh the statistics data:

```php
$this->loadStats();
```

### refreshData()

Called when `refresh-analytics-widgets` event is dispatched:

```php
// Automatically listens for this event
$this->dispatch('refresh-analytics-widgets');
```

### formatStatValue()

Format a stat value for display:

```php
$this->formatStatValue(1234, 'number');     // "1,234"
$this->formatStatValue(45.5, 'percentage'); // "45.5%"
$this->formatStatValue(180, 'duration');    // "3m 0s"
$this->formatStatValue(2.3, 'decimal');     // "2.3"
```

## Customization

### Publishing Views

```bash
php artisan vendor:publish --tag=analytics-views
```

Edit `resources/views/vendor/artisanpack-analytics/livewire/widgets/stats-cards.blade.php`.

### Custom Card Layout

Example custom view structure:

```blade
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    @foreach ($visibleStats as $statKey)
        @php
            $config = $this->getStatCardsConfig()[$statKey] ?? null;
            if (!$config) continue;
            $value = $stats[$config['key']] ?? 0;
            $comparison = $stats['comparison'][$config['key']] ?? null;
        @endphp

        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h3 class="text-sm text-gray-500">{{ $config['label'] }}</h3>
                <p class="text-2xl font-bold">
                    {{ $this->formatStatValue($value, $config['format']) }}
                </p>
                @if ($showComparison && $comparison)
                    <span class="{{ $comparison['change'] >= 0 ? 'text-success' : 'text-error' }}">
                        {{ $comparison['change'] > 0 ? '+' : '' }}{{ $comparison['change'] }}%
                    </span>
                @endif
            </div>
        </div>
    @endforeach
</div>
```

## Events

### Listens For

| Event | Action |
|-------|--------|
| `refresh-analytics-widgets` | Calls `refreshData()` |

## Integration Examples

### In a Custom Dashboard

```blade
<div class="space-y-6">
    {{-- Stats at the top --}}
    <livewire:artisanpack-analytics::stats-cards
        :visible-stats="['visitors', 'pageviews', 'bounce_rate']"
    />

    {{-- Chart below --}}
    <livewire:artisanpack-analytics::visitors-chart />
</div>
```

### With Custom Refresh Button

```blade
<div>
    <button
        wire:click="$dispatch('refresh-analytics-widgets')"
        class="btn btn-primary"
    >
        Refresh Stats
    </button>

    <livewire:artisanpack-analytics::stats-cards />
</div>
```
