---
title: Bot Traffic
---

# Bot Traffic Component

> **Since 1.2.0**

The Bot Traffic component surfaces the automated traffic that is filtered out of the main dashboard by default. It shows total bot visits, the bot share of total traffic, the busiest bot user agents, and a bot-only visit trend.

For an explanation of how traffic is identified as a bot, see [Bot Filtering](Advanced-Bot-Filtering).

## Basic Usage

```blade
<livewire:artisanpack-analytics::widgets.bot-traffic />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `dateRangePreset` | ?string | `'last7days'` | Date range preset |
| `siteId` | ?int | `null` | Site ID for multi-tenant |
| `limit` | int | `10` | Maximum bot user agents to display (1–100) |

## Usage Examples

### With a Custom Agent Limit

```blade
<livewire:artisanpack-analytics::widgets.bot-traffic
    :limit="5"
/>
```

### With Date Range

```blade
<livewire:artisanpack-analytics::widgets.bot-traffic
    date-range-preset="thisMonth"
/>
```

### Scoped to a Site

```blade
<livewire:artisanpack-analytics::widgets.bot-traffic
    :site-id="1"
/>
```

## Exposed Data

The component populates the following public properties from `AnalyticsQuery::getBotStats()`:

| Property | Type | Description |
|----------|------|-------------|
| `botVisits` | int | Total bot visits for the current range |
| `totalVisits` | int | Total visits (human and bot) for the current range |
| `botPercentage` | float | Bot share of total traffic, as a percentage |
| `topAgents` | Collection | Top bot user agents, each `['user_agent' => string, 'visits' => int]` |
| `trend` | array | Bot-only visit trend, each `['date' => string, 'visits' => int]` |

## Methods

### loadBotStats()

Refresh the bot traffic statistics:

```php
$this->loadBotStats();
```

### refreshData()

Called when the `refresh-analytics-widgets` event is dispatched:

```php
// Automatically listens for this event
$this->dispatch('refresh-analytics-widgets');
```

### getTrendMax()

Return the largest visit count across the trend points, used to scale the sparkline bars. Returns at least `1` to avoid division by zero:

```php
$max = $this->getTrendMax();
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

Edit `resources/views/vendor/artisanpack-analytics/livewire/widgets/bot-traffic.blade.php`.

## Integration Examples

### In a Custom Dashboard

```blade
<div class="space-y-6">
    {{-- Human traffic stats --}}
    <livewire:artisanpack-analytics::widgets.stats-cards />

    {{-- Filtered bot traffic for comparison --}}
    <livewire:artisanpack-analytics::widgets.bot-traffic :limit="5" />
</div>
```

### With a Custom Refresh Button

```blade
<div>
    <button
        wire:click="$dispatch('refresh-analytics-widgets')"
        class="btn btn-primary"
    >
        Refresh
    </button>

    <livewire:artisanpack-analytics::widgets.bot-traffic />
</div>
```
