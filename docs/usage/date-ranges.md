---
title: Date Ranges
---

# Date Ranges

The `DateRange` class provides a convenient way to work with date ranges in analytics queries.

## Creating Date Ranges

### Using Static Methods

```php
use ArtisanPackUI\Analytics\Data\DateRange;

// Preset ranges
$today = DateRange::today();
$yesterday = DateRange::yesterday();
$thisWeek = DateRange::thisWeek();
$lastWeek = DateRange::lastWeek();
$thisMonth = DateRange::thisMonth();
$lastMonth = DateRange::lastMonth();
$thisYear = DateRange::thisYear();

// Last N days
$last7Days = DateRange::last7Days();
$last30Days = DateRange::last30Days();
$last90Days = DateRange::last90Days();
$lastNDays = DateRange::lastDays(14);
```

### Using Helper Functions

```php
use function dateRangeLastDays;
use function dateRangeToday;
use function dateRangeThisMonth;

$range = dateRangeLastDays(7);
$today = dateRangeToday();
$month = dateRangeThisMonth();
```

### From Strings

```php
$range = DateRange::fromStrings('2024-01-01', '2024-01-31');
```

### From Carbon Instances

```php
use Carbon\Carbon;

$range = DateRange::fromCarbon(
    Carbon::parse('2024-01-01'),
    Carbon::parse('2024-01-31')
);
```

### Manual Construction

```php
use Carbon\Carbon;

$range = new DateRange(
    startDate: Carbon::parse('2024-01-01')->startOfDay(),
    endDate: Carbon::parse('2024-01-31')->endOfDay(),
);
```

## DateRange Properties

| Property | Type | Description |
|----------|------|-------------|
| `startDate` | CarbonInterface | Start of the range |
| `endDate` | CarbonInterface | End of the range |

## DateRange Methods

### getDays()

Get the number of days in the range:

```php
$range = DateRange::last30Days();
echo $range->getDays(); // 31 (inclusive)
```

### getPreviousPeriod() / previousPeriod()

Get the previous period of the same length for comparisons:

```php
$current = DateRange::last7Days();
$previous = $current->getPreviousPeriod();

// If current is Jan 8-14, previous is Jan 1-7
```

### toKey()

Get a cache-safe key for the range:

```php
$range = DateRange::last7Days();
$cacheKey = 'analytics_stats_' . $range->toKey();
// e.g., "analytics_stats_2024-01-08_2024-01-14"
```

### toArray()

Convert to array:

```php
$range = DateRange::today();
$array = $range->toArray();
// ['start_date' => '2024-01-14 00:00:00', 'end_date' => '2024-01-14 23:59:59']
```

## Using with Query Functions

All analytics query functions accept a `DateRange`:

```php
use ArtisanPackUI\Analytics\Data\DateRange;

$range = DateRange::last30Days();

// Stats
$stats = analyticsStats($range);

// Page views
$pageViews = analyticsPageViews($range, 'day');

// Top pages
$topPages = analyticsTopPages($range, 10);

// Visitors
$visitors = analyticsVisitors($range);

// Traffic sources
$sources = analyticsTrafficSources($range);
```

## Period Comparisons

Compare current period with previous:

```php
$current = DateRange::last7Days();
$previous = $current->previousPeriod();

$currentStats = analyticsStats($current, withCompare: false);
$previousStats = analyticsStats($previous, withCompare: false);

$change = $currentStats['visitors'] - $previousStats['visitors'];
$percentChange = ($previousStats['visitors'] > 0)
    ? (($change / $previousStats['visitors']) * 100)
    : 0;
```

Or use built-in comparison:

```php
$stats = analyticsStats(DateRange::last7Days(), withCompare: true);
// Returns current period stats with comparison percentages
```

## Using with Eloquent

```php
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Data\DateRange;

$range = DateRange::thisMonth();

$pageViews = PageView::whereBetween('created_at', [
    $range->startDate,
    $range->endDate,
])->count();
```

## Custom Date Picker Integration

When building a date picker UI:

```php
// In your Livewire component
public string $startDate;
public string $endDate;

public function getDateRange(): DateRange
{
    if ($this->startDate && $this->endDate) {
        return DateRange::fromStrings($this->startDate, $this->endDate);
    }

    return DateRange::last30Days();
}

public function getStats(): array
{
    return analyticsStats($this->getDateRange());
}
```

## Dashboard Integration

The analytics dashboard components accept date ranges:

```blade
<livewire:artisanpack-analytics::stats-cards
    :date-range="$dateRange"
/>

<livewire:artisanpack-analytics::visitors-chart
    :date-range="$dateRange"
    granularity="day"
/>
```

## Available Static Methods

| Method | Description |
|--------|-------------|
| `today()` | Today only |
| `yesterday()` | Yesterday only |
| `thisWeek()` | Current week (Mon-Sun) |
| `lastWeek()` | Previous week |
| `thisMonth()` | Current month |
| `lastMonth()` | Previous month |
| `thisYear()` | Current year |
| `last7Days()` | Last 7 days |
| `last30Days()` | Last 30 days |
| `last90Days()` | Last 90 days |
| `lastDays($n)` | Last N days |
| `fromStrings($start, $end)` | From date strings |
| `fromCarbon($start, $end)` | From Carbon instances |

## Best Practices

1. **Use preset methods** - They handle timezone and edge cases correctly
2. **Cache with toKey()** - Use the key method for cache keys
3. **Compare periods** - Use `previousPeriod()` for meaningful comparisons
4. **Be consistent** - Use the same granularity when comparing periods
5. **Consider timezones** - DateRange uses Carbon which respects app timezone
