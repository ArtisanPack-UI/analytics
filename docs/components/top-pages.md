---
title: Top Pages
---

# Top Pages Component

The Top Pages component displays a table of the most viewed pages on your site.

## Basic Usage

```blade
<livewire:artisanpack-analytics::top-pages />
```

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `dateRangePreset` | ?string | `'last7days'` | Date range preset |
| `siteId` | ?int | `null` | Site ID for multi-tenant |
| `limit` | int | `10` | Number of pages to show |

## Usage Examples

### Show More Pages

```blade
<livewire:artisanpack-analytics::top-pages
    :limit="25"
/>
```

### With Date Range

```blade
<livewire:artisanpack-analytics::top-pages
    date-range-preset="thisMonth"
    :limit="15"
/>
```

### Multi-Tenant

```blade
<livewire:artisanpack-analytics::top-pages
    :site-id="$site->id"
/>
```

## Data Structure

Each page entry contains:

```php
[
    'path' => '/products/widget',
    'title' => 'Widget Product Page',
    'views' => 1234,           // Total page views
    'unique_views' => 890,     // Unique visitors
    'avg_time' => 45,          // Average time on page (seconds)
    'bounce_rate' => 35.5,     // Bounce rate percentage
]
```

## Methods

### loadData()

Refresh the top pages data:

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

Edit `resources/views/vendor/artisanpack-analytics/livewire/widgets/top-pages.blade.php`.

### Custom Table Layout

Example custom view:

```blade
<div class="overflow-x-auto">
    <table class="table table-zebra">
        <thead>
            <tr>
                <th>Page</th>
                <th class="text-right">Views</th>
                <th class="text-right">Unique</th>
                <th class="text-right">Bounce Rate</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pages as $page)
                <tr>
                    <td>
                        <div class="font-medium">{{ $page['title'] ?: $page['path'] }}</div>
                        <div class="text-sm text-gray-500">{{ $page['path'] }}</div>
                    </td>
                    <td class="text-right">{{ number_format($page['views']) }}</td>
                    <td class="text-right">{{ number_format($page['unique_views']) }}</td>
                    <td class="text-right">{{ number_format($page['bounce_rate'], 1) }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-gray-500">
                        No page views recorded
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

### Adding Pagination

For larger datasets, you may want to add pagination:

```php
// In a custom component extending TopPages
public int $perPage = 10;

public function loadData(): void
{
    $this->pages = $this->getAnalyticsQuery()
        ->getTopPages($this->getDateRange(), $this->perPage, $this->getFilters());
}
```

## Integration Examples

### With Search Filter

> **Note:** The TopPages component does not include a built-in search feature. To implement search functionality, you'll need to create a custom Livewire component that extends TopPages and adds a public `$search` property with filtering logic.

```blade
{{-- This requires a custom component that extends TopPages --}}
<div>
    <input
        type="text"
        wire:model.live.debounce.300ms="search"
        placeholder="Filter pages..."
        class="input input-bordered w-full mb-4"
    />

    <livewire:your-custom-top-pages />
</div>
```

### Dashboard Integration

```blade
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h3 class="card-title">Top Pages</h3>
            <livewire:artisanpack-analytics::top-pages :limit="5" />
        </div>
    </div>

    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <h3 class="card-title">Traffic Sources</h3>
            <livewire:artisanpack-analytics::traffic-sources :limit="5" />
        </div>
    </div>
</div>
```

### Clickable Rows

Make rows link to the actual pages (using data attributes to prevent XSS):

```blade
@foreach ($pages as $page)
    <tr class="hover:bg-base-200 cursor-pointer"
        x-data
        data-path="{{ e($page['path']) }}"
        @click="window.open($el.dataset.path, '_blank')">
        <td>{{ $page['title'] }}</td>
        <td>{{ $page['views'] }}</td>
    </tr>
@endforeach
```

## Sorting

The component returns pages sorted by total views in descending order. For custom sorting, create a custom component:

```php
use ArtisanPackUI\Analytics\Http\Livewire\Widgets\TopPages;

class CustomTopPages extends TopPages
{
    public string $sortBy = 'views';
    public string $sortDirection = 'desc';

    public function sortBy(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'desc';
        }

        $this->loadData();
    }
}
```
