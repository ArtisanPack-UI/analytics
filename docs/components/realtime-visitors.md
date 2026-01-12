---
title: Realtime Visitors
---

# Realtime Visitors Component

The Realtime Visitors component displays a live count of currently active visitors on your site.

## Basic Usage

```blade
<livewire:artisanpack-analytics::realtime-visitors />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `siteId` | ?int | `null` | Site ID for multi-tenant |
| `minutes` | int | `5` | Minutes to consider "active" |
| `refreshInterval` | int | `30` | Seconds between refreshes |

## Usage Examples

### Custom Time Window

```blade
<livewire:artisanpack-analytics::realtime-visitors
    :minutes="10"
/>
```

### Faster Refresh Rate

```blade
<livewire:artisanpack-analytics::realtime-visitors
    :refresh-interval="15"
/>
```

### Multi-Tenant

```blade
<livewire:artisanpack-analytics::realtime-visitors
    :site-id="$site->id"
/>
```

## Data Structure

The component provides:

```php
$this->realtimeData = [
    'active_visitors' => 23,      // Currently active visitors
    'active_pages' => [           // Pages being viewed
        [
            'path' => '/products',
            'title' => 'Products',
            'visitors' => 8,
        ],
        [
            'path' => '/',
            'title' => 'Home',
            'visitors' => 5,
        ],
        // ...
    ],
    'sources' => [                // Current traffic sources
        [
            'source' => 'google',
            'visitors' => 12,
        ],
        [
            'source' => 'direct',
            'visitors' => 8,
        ],
    ],
    'devices' => [                // Device breakdown
        'desktop' => 15,
        'mobile' => 6,
        'tablet' => 2,
    ],
];
```

## Methods

### loadData()

Refresh the realtime data:

```php
$this->loadData();
```

### refreshData()

Called automatically based on `refreshInterval`.

## Auto-Refresh

The component automatically refreshes using Livewire's polling:

```blade
<div wire:poll.{{ $refreshInterval }}s>
    {{-- Content --}}
</div>
```

## Configuration

Enable/disable realtime tracking in config:

```php
// config/artisanpack/analytics.php
'dashboard' => [
    'realtime_enabled' => env('ANALYTICS_REALTIME_ENABLED', true),
    'realtime_interval' => env('ANALYTICS_REALTIME_INTERVAL', 30),
],
```

## Customization

### Publishing Views

```bash
php artisan vendor:publish --tag=analytics-views
```

Edit `resources/views/vendor/artisanpack-analytics/livewire/widgets/realtime-visitors.blade.php`.

### Custom Display

Example custom view:

```blade
<div wire:poll.{{ $refreshInterval }}s class="card bg-base-100 shadow">
    <div class="card-body">
        {{-- Main counter --}}
        <div class="text-center mb-6">
            <div class="flex items-center justify-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-success"></span>
                </span>
                <span class="text-4xl font-bold">{{ $realtimeData['active_visitors'] }}</span>
            </div>
            <p class="text-gray-500 mt-1">Active visitors right now</p>
        </div>

        {{-- Active pages --}}
        @if (!empty($realtimeData['active_pages']))
            <div class="mt-4">
                <h4 class="font-medium mb-2">Currently viewing:</h4>
                <ul class="space-y-1">
                    @foreach (array_slice($realtimeData['active_pages'], 0, 5) as $page)
                        <li class="flex justify-between text-sm">
                            <span class="truncate">{{ $page['title'] ?: $page['path'] }}</span>
                            <span class="badge badge-sm">{{ $page['visitors'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
```

### Animated Counter

Add animation to the visitor count:

```blade
<div
    x-data="{ count: {{ $realtimeData['active_visitors'] }} }"
    x-init="$watch('$wire.realtimeData.active_visitors', value => {
        const start = count;
        const end = value;
        const duration = 500;
        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            count = Math.round(start + (end - start) * progress);

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };

        requestAnimationFrame(animate);
    })"
>
    <span class="text-4xl font-bold" x-text="count"></span>
</div>
```

## Integration Examples

### Header Badge

Display active visitors in your site header:

```blade
<nav class="navbar">
    <div class="flex-1">
        <a href="/" class="text-xl">My Site</a>
    </div>
    <div class="flex-none">
        <livewire:artisanpack-analytics::realtime-visitors />
    </div>
</nav>
```

### Dashboard Card

```blade
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    {{-- Realtime visitors --}}
    <div class="md:col-span-1">
        <livewire:artisanpack-analytics::realtime-visitors />
    </div>

    {{-- Stats cards --}}
    <div class="md:col-span-3">
        <livewire:artisanpack-analytics::stats-cards />
    </div>
</div>
```

### Minimal Badge Display

```blade
<div class="indicator">
    <span class="indicator-item badge badge-success">
        {{ $realtimeData['active_visitors'] }}
    </span>
    <span>Active now</span>
</div>
```

## Performance Considerations

1. **Refresh Interval**: Don't set too low (< 10 seconds) to avoid server load
2. **Disable When Hidden**: Consider stopping polling when tab is not visible

```blade
<div
    x-data="{ visible: true }"
    x-init="
        document.addEventListener('visibilitychange', () => {
            visible = !document.hidden;
        })
    "
    x-show="visible"
    wire:poll.{{ $refreshInterval }}s
>
    {{-- Content only refreshes when tab is visible --}}
</div>
```

3. **Caching**: Realtime data is cached briefly to reduce database queries
