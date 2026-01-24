---
title: Caching
---

# Caching

ArtisanPack UI Analytics uses caching to optimize performance for dashboard queries and real-time data.

## Configuration

### Dashboard Cache

```php
// config/artisanpack/analytics.php
'dashboard' => [
    'cache_duration' => env('ANALYTICS_CACHE_DURATION', 300), // 5 minutes
],
```

### Goals Cache

```php
'goals' => [
    'cache_duration' => env('ANALYTICS_GOALS_CACHE', 300),
],
```

## Cache Keys

The package uses structured cache keys:

```
analytics:{site_id}:{query_type}:{date_range}:{hash}
```

Examples:
- `analytics:1:stats:2024-01-01_2024-01-31:abc123`
- `analytics:1:top_pages:2024-01-01_2024-01-31:def456`

## Using the DateRange for Cache Keys

```php
use ArtisanPackUI\Analytics\Data\DateRange;

$range = DateRange::last30Days();
$cacheKey = 'my_query_' . $range->toKey();
// my_query_2024-01-01_2024-01-31
```

## Manual Caching

### In Custom Queries

```php
use Illuminate\Support\Facades\Cache;
use ArtisanPackUI\Analytics\Data\DateRange;

$range = DateRange::last7Days();
$cacheKey = "custom_stats_{$range->toKey()}";
$cacheDuration = config('artisanpack.analytics.dashboard.cache_duration', 300);

$stats = Cache::remember($cacheKey, $cacheDuration, function () use ($range) {
    return PageView::whereBetween('created_at', [
        $range->startDate,
        $range->endDate,
    ])->count();
});
```

### In Livewire Components

```php
use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;

class CustomWidget extends Component
{
    use WithAnalyticsWidget;

    public function loadData(): void
    {
        $range = $this->getDateRange();
        $cacheKey = $this->getCacheKey('custom_data');

        $this->data = Cache::remember($cacheKey, 300, function () use ($range) {
            return $this->getAnalyticsQuery()->getStats($range);
        });
    }

    protected function getCacheKey(string $suffix): string
    {
        $range = $this->getDateRange();
        $siteId = $this->siteId ?? 'global';

        return "analytics:{$siteId}:{$suffix}:{$range->toKey()}";
    }
}
```

## Cache Invalidation

### Clear All Analytics Cache

```bash
php artisan analytics:clear-cache
```

### Programmatic Clearing

```php
use Illuminate\Support\Facades\Cache;

// Clear specific key
Cache::forget('analytics:1:stats:2024-01-01_2024-01-31:abc123');

// Clear by tag (if using tagged cache)
Cache::tags(['analytics', 'site_1'])->flush();
```

### After Data Changes

Events can trigger cache invalidation:

```php
use ArtisanPackUI\Analytics\Events\PageViewRecorded;

Event::listen(PageViewRecorded::class, function ($event) {
    // Clear real-time cache
    Cache::forget("analytics:{$event->pageView->site_id}:realtime");
});
```

## Cache Drivers

### Recommended Drivers

For production, use:
- **Redis** - Best performance, supports tags
- **Memcached** - Good performance
- **Database** - When Redis/Memcached unavailable

```php
// .env
CACHE_DRIVER=redis
```

### Driver-Specific Configuration

```php
// config/cache.php
'stores' => [
    'analytics' => [
        'driver' => 'redis',
        'connection' => 'analytics',
    ],
],
```

Use a dedicated cache store:

```php
Cache::store('analytics')->remember($key, $duration, $callback);
```

## Cache Tags

If your cache driver supports tags:

```php
use Illuminate\Support\Facades\Cache;

// Store with tags
Cache::tags(['analytics', 'site_1', 'stats'])
    ->remember($key, 300, $callback);

// Clear by tag
Cache::tags(['site_1'])->flush();
Cache::tags(['stats'])->flush();
```

## Real-Time Data Caching

Real-time data uses shorter cache durations:

```php
'dashboard' => [
    'realtime_interval' => 30, // Refresh every 30 seconds
],
```

The real-time widget polls at this interval, and data is cached for the same duration.

## Query Caching in AnalyticsQuery

The `AnalyticsQuery` service automatically caches results:

```php
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;

$query = app(AnalyticsQuery::class);

// This result is automatically cached
$stats = $query->getStats($range);

// Force fresh data (bypass cache)
$query->withoutCache();
$freshStats = $query->getStats($range);
```

## Cache Warming

Pre-populate cache for better user experience:

```php
// In a scheduled command
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\Site;

class WarmAnalyticsCache extends Command
{
    protected $signature = 'analytics:warm-cache';

    public function handle(AnalyticsQuery $query): void
    {
        $ranges = [
            DateRange::today(),
            DateRange::last7Days(),
            DateRange::last30Days(),
        ];

        Site::active()->each(function ($site) use ($query, $ranges) {
            foreach ($ranges as $range) {
                $query->forSite($site->id)->getStats($range);
                $query->forSite($site->id)->getTopPages($range);
            }
        });

        $this->info('Cache warmed successfully');
    }
}
```

Schedule it:

```php
// routes/console.php
Schedule::command('analytics:warm-cache')->hourly();
```

## Performance Tips

### 1. Use Appropriate Cache Duration

```php
// Frequently changing (real-time): 30 seconds
'realtime_interval' => 30,

// Dashboard stats: 5 minutes
'cache_duration' => 300,

// Historical reports: 1 hour
'historical_cache' => 3600,
```

### 2. Cache at the Right Level

```php
// Bad: Caching raw database results
$rows = Cache::remember('all_pageviews', 300, function () {
    return PageView::all(); // Too much data
});

// Good: Caching computed results
$stats = Cache::remember('pageview_count', 300, function () {
    return PageView::count(); // Just the number
});
```

### 3. Use Cache Tags for Invalidation

```php
// Store with meaningful tags
Cache::tags(['analytics', "site_{$siteId}", 'daily_stats'])
    ->put($key, $data, 300);

// Invalidate all site data on significant changes
Cache::tags(["site_{$siteId}"])->flush();
```

### 4. Consider Cache Stampede

Use locks for expensive queries:

```php
$stats = Cache::lock("analytics_lock_{$key}", 10)->block(5, function () use ($key) {
    return Cache::remember($key, 300, function () {
        return $this->expensiveQuery();
    });
});
```

## Monitoring Cache Performance

```php
// Log cache hits/misses
Cache::macro('rememberWithLogging', function ($key, $ttl, $callback) {
    $start = microtime(true);
    $hit = Cache::has($key);

    $result = Cache::remember($key, $ttl, $callback);

    Log::debug('Cache access', [
        'key' => $key,
        'hit' => $hit,
        'duration' => microtime(true) - $start,
    ]);

    return $result;
});
```
