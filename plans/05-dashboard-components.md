# Dashboard Components

**Purpose:** Define the Livewire dashboard widgets, full-page analytics, and page-level analytics components
**Last Updated:** January 3, 2026

---

## Overview

The analytics package provides three levels of dashboard components:

1. **Dashboard Widgets** - Embeddable stat cards and charts for the CMS admin dashboard
2. **Full Analytics Page** - Comprehensive analytics dashboard with all metrics
3. **Page-Level Analytics** - Analytics for specific pages, integrated with the visual editor

---

## Component Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Dashboard Components                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Widgets (Embeddable)           Full Dashboard         Page-Level   │
│  ┌──────────────────┐          ┌──────────────┐       ┌──────────┐ │
│  │ StatsCards       │          │ Analytics    │       │ Page     │ │
│  │ VisitorsChart    │          │ Dashboard    │       │ Analytics│ │
│  │ TopPages         │          │              │       │          │ │
│  │ TrafficSources   │          │ Uses all     │       │ Single   │ │
│  │ BounceRate       │          │ widgets +    │       │ page     │ │
│  │ EngagementChart  │          │ date range   │       │ metrics  │ │
│  └──────────────────┘          └──────────────┘       └──────────┘ │
│          │                            │                     │       │
│          └────────────────────────────┴─────────────────────┘       │
│                                 │                                    │
│                                 ▼                                    │
│                    ┌──────────────────────┐                         │
│                    │   AnalyticsQuery     │                         │
│                    │   Service            │                         │
│                    └──────────────────────┘                         │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Analytics Query Service

The shared query service that all components use:

```php
// src/Services/AnalyticsQuery.php

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\Aggregate;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsQuery
{
    protected ?int $siteId = null;
    protected int $cacheDuration;

    public function __construct()
    {
        $this->cacheDuration = config('analytics.dashboard.cache_duration', 300);

        if (config('analytics.multi_tenant.enabled')) {
            $this->siteId = app('analytics.tenant')?->id;
        }
    }

    // ==========================================
    // Core Metrics
    // ==========================================

    public function getPageViews(DateRange $range): int
    {
        return $this->cached("pageviews:{$range->key()}", function () use ($range) {
            return PageView::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('created_at', [$range->start, $range->end])
                ->count();
        });
    }

    public function getVisitors(DateRange $range): int
    {
        return $this->cached("visitors:{$range->key()}", function () use ($range) {
            return Visitor::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('last_seen_at', [$range->start, $range->end])
                ->count();
        });
    }

    public function getNewVisitors(DateRange $range): int
    {
        return $this->cached("new_visitors:{$range->key()}", function () use ($range) {
            return Visitor::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('first_seen_at', [$range->start, $range->end])
                ->count();
        });
    }

    public function getSessions(DateRange $range): int
    {
        return $this->cached("sessions:{$range->key()}", function () use ($range) {
            return Session::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->count();
        });
    }

    public function getBounceRate(DateRange $range): float
    {
        return $this->cached("bounce_rate:{$range->key()}", function () use ($range) {
            $sessions = Session::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->whereNotNull('ended_at');

            $total = $sessions->count();
            if ($total === 0) {
                return 0.0;
            }

            $bounced = (clone $sessions)->where('is_bounce', true)->count();

            return round(($bounced / $total) * 100, 2);
        });
    }

    public function getAverageSessionDuration(DateRange $range): int
    {
        return $this->cached("avg_duration:{$range->key()}", function () use ($range) {
            return (int) Session::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->whereNotNull('ended_at')
                ->avg('duration') ?? 0;
        });
    }

    public function getAveragePagesPerSession(DateRange $range): float
    {
        return $this->cached("avg_pages:{$range->key()}", function () use ($range) {
            return round(Session::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->whereNotNull('ended_at')
                ->avg('page_count') ?? 0, 2);
        });
    }

    public function getAverageTimeOnPage(DateRange $range): int
    {
        return $this->cached("avg_time_on_page:{$range->key()}", function () use ($range) {
            return (int) PageView::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('created_at', [$range->start, $range->end])
                ->whereNotNull('time_on_page')
                ->avg('time_on_page') ?? 0;
        });
    }

    // ==========================================
    // Top Content
    // ==========================================

    public function getTopPages(DateRange $range, int $limit = 10): Collection
    {
        return $this->cached("top_pages:{$range->key()}:{$limit}", function () use ($range, $limit) {
            return PageView::query()
                ->select('path', DB::raw('COUNT(*) as views'), DB::raw('AVG(time_on_page) as avg_time'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('created_at', [$range->start, $range->end])
                ->groupBy('path')
                ->orderByDesc('views')
                ->limit($limit)
                ->get()
                ->map(fn($row) => [
                    'path' => $row->path,
                    'views' => $row->views,
                    'avg_time' => round($row->avg_time ?? 0),
                ]);
        });
    }

    public function getTopEntryPages(DateRange $range, int $limit = 10): Collection
    {
        return $this->cached("entry_pages:{$range->key()}:{$limit}", function () use ($range, $limit) {
            return Session::query()
                ->select('entry_page', DB::raw('COUNT(*) as sessions'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->groupBy('entry_page')
                ->orderByDesc('sessions')
                ->limit($limit)
                ->get();
        });
    }

    public function getTopExitPages(DateRange $range, int $limit = 10): Collection
    {
        return $this->cached("exit_pages:{$range->key()}:{$limit}", function () use ($range, $limit) {
            return Session::query()
                ->select('exit_page', DB::raw('COUNT(*) as sessions'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->whereNotNull('exit_page')
                ->groupBy('exit_page')
                ->orderByDesc('sessions')
                ->limit($limit)
                ->get();
        });
    }

    // ==========================================
    // Traffic Sources
    // ==========================================

    public function getTrafficSources(DateRange $range, int $limit = 10): Collection
    {
        return $this->cached("sources:{$range->key()}:{$limit}", function () use ($range, $limit) {
            return Session::query()
                ->select('referrer_type', DB::raw('COUNT(*) as sessions'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->groupBy('referrer_type')
                ->orderByDesc('sessions')
                ->limit($limit)
                ->get();
        });
    }

    public function getTopReferrers(DateRange $range, int $limit = 10): Collection
    {
        return $this->cached("referrers:{$range->key()}:{$limit}", function () use ($range, $limit) {
            return Session::query()
                ->select('referrer_domain', DB::raw('COUNT(*) as sessions'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->whereNotNull('referrer_domain')
                ->where('referrer_domain', '!=', '')
                ->groupBy('referrer_domain')
                ->orderByDesc('sessions')
                ->limit($limit)
                ->get();
        });
    }

    public function getUTMCampaigns(DateRange $range, int $limit = 10): Collection
    {
        return $this->cached("utm_campaigns:{$range->key()}:{$limit}", function () use ($range, $limit) {
            return Session::query()
                ->select('utm_source', 'utm_medium', 'utm_campaign', DB::raw('COUNT(*) as sessions'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->whereNotNull('utm_source')
                ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
                ->orderByDesc('sessions')
                ->limit($limit)
                ->get();
        });
    }

    // ==========================================
    // Device & Browser
    // ==========================================

    public function getDeviceBreakdown(DateRange $range): Collection
    {
        return $this->cached("devices:{$range->key()}", function () use ($range) {
            return Visitor::query()
                ->select('device_type', DB::raw('COUNT(*) as visitors'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('last_seen_at', [$range->start, $range->end])
                ->groupBy('device_type')
                ->orderByDesc('visitors')
                ->get();
        });
    }

    public function getBrowserBreakdown(DateRange $range, int $limit = 10): Collection
    {
        return $this->cached("browsers:{$range->key()}:{$limit}", function () use ($range, $limit) {
            return Visitor::query()
                ->select('browser', DB::raw('COUNT(*) as visitors'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('last_seen_at', [$range->start, $range->end])
                ->whereNotNull('browser')
                ->groupBy('browser')
                ->orderByDesc('visitors')
                ->limit($limit)
                ->get();
        });
    }

    // ==========================================
    // Time Series Data
    // ==========================================

    public function getPageViewsOverTime(DateRange $range, string $interval = 'day'): Collection
    {
        return $this->cached("pageviews_time:{$range->key()}:{$interval}", function () use ($range, $interval) {
            $format = match($interval) {
                'hour' => '%Y-%m-%d %H:00',
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                default => '%Y-%m-%d',
            };

            return PageView::query()
                ->select(DB::raw("DATE_FORMAT(created_at, '{$format}') as period"), DB::raw('COUNT(*) as count'))
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('created_at', [$range->start, $range->end])
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        });
    }

    public function getVisitorsOverTime(DateRange $range, string $interval = 'day'): Collection
    {
        return $this->cached("visitors_time:{$range->key()}:{$interval}", function () use ($range, $interval) {
            $format = match($interval) {
                'hour' => '%Y-%m-%d %H:00',
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                default => '%Y-%m-%d',
            };

            return Session::query()
                ->select(
                    DB::raw("DATE_FORMAT(started_at, '{$format}') as period"),
                    DB::raw('COUNT(DISTINCT visitor_id) as count')
                )
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->whereBetween('started_at', [$range->start, $range->end])
                ->groupBy('period')
                ->orderBy('period')
                ->get();
        });
    }

    // ==========================================
    // Page-Specific Analytics
    // ==========================================

    public function getPageAnalytics(string $path, DateRange $range): array
    {
        $cacheKey = "page:{$path}:{$range->key()}";

        return $this->cached($cacheKey, function () use ($path, $range) {
            $pageViews = PageView::query()
                ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
                ->where('path', $path)
                ->whereBetween('created_at', [$range->start, $range->end]);

            return [
                'views' => $pageViews->count(),
                'unique_visitors' => $pageViews->distinct('visitor_id')->count(),
                'avg_time_on_page' => round($pageViews->avg('time_on_page') ?? 0),
                'avg_scroll_depth' => round($pageViews->avg('scroll_depth') ?? 0),
                'avg_engaged_time' => round($pageViews->avg('engaged_time') ?? 0),
                'entry_rate' => $this->calculateEntryRate($path, $range),
                'exit_rate' => $this->calculateExitRate($path, $range),
                'views_over_time' => $this->getPageViewsOverTimeForPath($path, $range),
            ];
        });
    }

    protected function calculateEntryRate(string $path, DateRange $range): float
    {
        $sessions = Session::query()
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->whereBetween('started_at', [$range->start, $range->end])
            ->count();

        if ($sessions === 0) {
            return 0;
        }

        $entries = Session::query()
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->whereBetween('started_at', [$range->start, $range->end])
            ->where('entry_page', $path)
            ->count();

        return round(($entries / $sessions) * 100, 2);
    }

    protected function calculateExitRate(string $path, DateRange $range): float
    {
        $views = PageView::query()
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->where('path', $path)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->count();

        if ($views === 0) {
            return 0;
        }

        $exits = Session::query()
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->whereBetween('ended_at', [$range->start, $range->end])
            ->where('exit_page', $path)
            ->count();

        return round(($exits / $views) * 100, 2);
    }

    protected function getPageViewsOverTimeForPath(string $path, DateRange $range): Collection
    {
        return PageView::query()
            ->select(DB::raw("DATE(created_at) as date"), DB::raw('COUNT(*) as views'))
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->where('path', $path)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    // ==========================================
    // Comparison Metrics
    // ==========================================

    public function getComparisonStats(DateRange $current, DateRange $previous): array
    {
        return [
            'pageviews' => $this->calculateChange(
                $this->getPageViews($current),
                $this->getPageViews($previous)
            ),
            'visitors' => $this->calculateChange(
                $this->getVisitors($current),
                $this->getVisitors($previous)
            ),
            'sessions' => $this->calculateChange(
                $this->getSessions($current),
                $this->getSessions($previous)
            ),
            'bounce_rate' => $this->calculateChange(
                $this->getBounceRate($current),
                $this->getBounceRate($previous),
                true // Lower is better
            ),
            'avg_duration' => $this->calculateChange(
                $this->getAverageSessionDuration($current),
                $this->getAverageSessionDuration($previous)
            ),
        ];
    }

    protected function calculateChange(float $current, float $previous, bool $invertTrend = false): array
    {
        if ($previous == 0) {
            $change = $current > 0 ? 100 : 0;
        } else {
            $change = round((($current - $previous) / $previous) * 100, 1);
        }

        $trend = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral');

        // For metrics where lower is better (like bounce rate), invert the trend
        if ($invertTrend) {
            $trend = match($trend) {
                'up' => 'down',
                'down' => 'up',
                default => 'neutral',
            };
        }

        return [
            'current' => $current,
            'previous' => $previous,
            'change' => $change,
            'trend' => $trend,
        ];
    }

    // ==========================================
    // Caching Helper
    // ==========================================

    protected function cached(string $key, \Closure $callback): mixed
    {
        $fullKey = 'analytics:' . ($this->siteId ? "{$this->siteId}:" : '') . $key;

        return Cache::remember($fullKey, $this->cacheDuration, $callback);
    }

    public function clearCache(): void
    {
        Cache::tags(['analytics'])->flush();
    }
}
```

---

## Dashboard Widgets

### Stats Cards Widget

```php
// src/Http/Livewire/Widgets/StatsCards.php

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Livewire\Component;

class StatsCards extends Component
{
    public string $period = '30d';
    public bool $showComparison = true;

    public function mount(string $period = '30d', bool $showComparison = true): void
    {
        $this->period = $period;
        $this->showComparison = $showComparison;
    }

    public function render()
    {
        $query = app(AnalyticsQuery::class);
        $range = DateRange::fromPeriod($this->period);

        $stats = [
            [
                'label' => __('Page Views'),
                'value' => $query->getPageViews($range),
                'icon' => 'o-eye',
            ],
            [
                'label' => __('Visitors'),
                'value' => $query->getVisitors($range),
                'icon' => 'o-users',
            ],
            [
                'label' => __('Sessions'),
                'value' => $query->getSessions($range),
                'icon' => 'o-cursor-arrow-rays',
            ],
            [
                'label' => __('Bounce Rate'),
                'value' => $query->getBounceRate($range) . '%',
                'icon' => 'o-arrow-uturn-left',
            ],
            [
                'label' => __('Avg. Duration'),
                'value' => $this->formatDuration($query->getAverageSessionDuration($range)),
                'icon' => 'o-clock',
            ],
            [
                'label' => __('Pages/Session'),
                'value' => $query->getAveragePagesPerSession($range),
                'icon' => 'o-document-duplicate',
            ],
        ];

        if ($this->showComparison) {
            $previousRange = DateRange::fromPeriod($this->period)->previous();
            $comparison = $query->getComparisonStats($range, $previousRange);

            foreach ($stats as $key => $stat) {
                $metricKey = strtolower(str_replace([' ', '.'], '_', $stat['label']));
                if (isset($comparison[$metricKey])) {
                    $stats[$key]['change'] = $comparison[$metricKey]['change'];
                    $stats[$key]['trend'] = $comparison[$metricKey]['trend'];
                }
            }
        }

        return view('analytics::livewire.widgets.stats-cards', [
            'stats' => $stats,
        ]);
    }

    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        return "{$minutes}m {$secs}s";
    }
}
```

**View:**

```blade
{{-- resources/views/livewire/widgets/stats-cards.blade.php --}}

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
    @foreach($stats as $stat)
        <x-artisanpack-card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-bold mt-1">
                        {{ is_numeric($stat['value']) ? number_format($stat['value']) : $stat['value'] }}
                    </p>
                    @if(isset($stat['change']))
                        <div class="flex items-center mt-1 text-sm {{ $stat['trend'] === 'up' ? 'text-green-600' : ($stat['trend'] === 'down' ? 'text-red-600' : 'text-gray-500') }}">
                            @if($stat['trend'] === 'up')
                                <x-icon-o-arrow-up class="w-4 h-4 mr-1" />
                            @elseif($stat['trend'] === 'down')
                                <x-icon-o-arrow-down class="w-4 h-4 mr-1" />
                            @endif
                            <span>{{ abs($stat['change']) }}%</span>
                        </div>
                    @endif
                </div>
                <div class="p-3 bg-primary-50 dark:bg-primary-900/20 rounded-full">
                    <x-icon :name="$stat['icon']" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                </div>
            </div>
        </x-artisanpack-card>
    @endforeach
</div>
```

### Visitors Chart Widget

```php
// src/Http/Livewire/Widgets/VisitorsChart.php

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Livewire\Component;

class VisitorsChart extends Component
{
    public string $period = '30d';
    public string $metric = 'visitors'; // visitors, pageviews, sessions

    public function mount(string $period = '30d', string $metric = 'visitors'): void
    {
        $this->period = $period;
        $this->metric = $metric;
    }

    public function render()
    {
        $query = app(AnalyticsQuery::class);
        $range = DateRange::fromPeriod($this->period);

        $data = match($this->metric) {
            'pageviews' => $query->getPageViewsOverTime($range, $this->getInterval()),
            'visitors' => $query->getVisitorsOverTime($range, $this->getInterval()),
            default => $query->getVisitorsOverTime($range, $this->getInterval()),
        };

        return view('analytics::livewire.widgets.visitors-chart', [
            'chartData' => $data,
            'labels' => $data->pluck('period')->toArray(),
            'values' => $data->pluck('count')->toArray(),
        ]);
    }

    protected function getInterval(): string
    {
        return match($this->period) {
            '24h', '7d' => 'day',
            '30d', '90d' => 'day',
            '1y' => 'month',
            default => 'day',
        };
    }
}
```

**View:**

```blade
{{-- resources/views/livewire/widgets/visitors-chart.blade.php --}}

<x-artisanpack-card>
    <x-slot:header>
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">{{ __(':metric Over Time', ['metric' => ucfirst($metric)]) }}</h3>
            <select wire:model.live="metric" class="text-sm border-gray-300 rounded-md">
                <option value="visitors">{{ __('Visitors') }}</option>
                <option value="pageviews">{{ __('Page Views') }}</option>
            </select>
        </div>
    </x-slot:header>

    <div class="h-64" wire:ignore>
        <canvas
            x-data="{
                chart: null,
                init() {
                    this.chart = new Chart(this.$el, {
                        type: 'line',
                        data: {
                            labels: @js($labels),
                            datasets: [{
                                label: '{{ ucfirst($metric) }}',
                                data: @js($values),
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                fill: true,
                                tension: 0.4,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                }
            }"
        ></canvas>
    </div>
</x-artisanpack-card>
```

### Top Pages Widget

```php
// src/Http/Livewire/Widgets/TopPages.php

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Livewire\Component;

class TopPages extends Component
{
    public string $period = '30d';
    public int $limit = 10;

    public function render()
    {
        $query = app(AnalyticsQuery::class);
        $range = DateRange::fromPeriod($this->period);

        return view('analytics::livewire.widgets.top-pages', [
            'pages' => $query->getTopPages($range, $this->limit),
        ]);
    }
}
```

**View:**

```blade
{{-- resources/views/livewire/widgets/top-pages.blade.php --}}

<x-artisanpack-card>
    <x-slot:header>
        <h3 class="text-lg font-semibold">{{ __('Top Pages') }}</h3>
    </x-slot:header>

    <div class="divide-y divide-gray-200 dark:divide-gray-700">
        @forelse($pages as $page)
            <div class="py-3 flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                        {{ $page['path'] }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Avg. :seconds s on page', ['seconds' => $page['avg_time']]) }}
                    </p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format($page['views']) }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('views') }}</span>
                </div>
            </div>
        @empty
            <p class="py-4 text-center text-gray-500 dark:text-gray-400">{{ __('No data available') }}</p>
        @endforelse
    </div>
</x-artisanpack-card>
```

### Traffic Sources Widget

```php
// src/Http/Livewire/Widgets/TrafficSources.php

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Livewire\Component;

class TrafficSources extends Component
{
    public string $period = '30d';

    public function render()
    {
        $query = app(AnalyticsQuery::class);
        $range = DateRange::fromPeriod($this->period);

        $sources = $query->getTrafficSources($range);
        $total = $sources->sum('sessions');

        return view('analytics::livewire.widgets.traffic-sources', [
            'sources' => $sources->map(fn($s) => [
                'type' => $s->referrer_type,
                'sessions' => $s->sessions,
                'percentage' => $total > 0 ? round(($s->sessions / $total) * 100, 1) : 0,
            ]),
        ]);
    }
}
```

---

## Full Analytics Dashboard

```php
// src/Http/Livewire/AnalyticsDashboard.php

namespace ArtisanPackUI\Analytics\Http\Livewire;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Livewire\Component;

class AnalyticsDashboard extends Component
{
    public string $period = '30d';
    public string $activeTab = 'overview';

    protected $queryString = ['period', 'activeTab'];

    public function mount(): void
    {
        $this->period = request()->query('period', '30d');
        $this->activeTab = request()->query('activeTab', 'overview');
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        return view('analytics::livewire.dashboard', [
            'periods' => [
                '24h' => __('Last 24 Hours'),
                '7d' => __('Last 7 Days'),
                '30d' => __('Last 30 Days'),
                '90d' => __('Last 90 Days'),
                '1y' => __('Last Year'),
            ],
            'tabs' => [
                'overview' => __('Overview'),
                'pages' => __('Pages'),
                'sources' => __('Traffic Sources'),
                'events' => __('Events'),
                'goals' => __('Goals'),
            ],
        ]);
    }
}
```

**View:**

```blade
{{-- resources/views/livewire/dashboard.blade.php --}}

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Analytics') }}</h1>

        <div class="flex items-center gap-4">
            {{-- Date Range Selector --}}
            <select wire:model.live="period" class="border-gray-300 rounded-md shadow-sm">
                @foreach($periods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            {{-- Export Button --}}
            <x-artisanpack-button wire:click="export" icon="o-arrow-down-tray" color="secondary">
                {{ __('Export') }}
            </x-artisanpack-button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <livewire:analytics-stats-cards :period="$period" :key="'stats-'.$period" />

    {{-- Tabs --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="flex gap-4">
            @foreach($tabs as $tabKey => $tabLabel)
                <button
                    wire:click="setTab('{{ $tabKey }}')"
                    class="px-4 py-2 text-sm font-medium border-b-2 {{ $activeTab === $tabKey ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab Content --}}
    <div>
        @switch($activeTab)
            @case('overview')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <livewire:analytics-visitors-chart :period="$period" :key="'chart-'.$period" />
                    <livewire:analytics-traffic-sources :period="$period" :key="'sources-'.$period" />
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                    <livewire:analytics-top-pages :period="$period" :key="'pages-'.$period" />
                    <livewire:analytics-device-breakdown :period="$period" :key="'devices-'.$period" />
                </div>
                @break

            @case('pages')
                <livewire:analytics-pages-table :period="$period" :key="'pages-table-'.$period" />
                @break

            @case('sources')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <livewire:analytics-referrers :period="$period" :key="'referrers-'.$period" />
                    <livewire:analytics-utm-campaigns :period="$period" :key="'utm-'.$period" />
                </div>
                @break

            @case('events')
                <livewire:analytics-events-table :period="$period" :key="'events-table-'.$period" />
                @break

            @case('goals')
                <livewire:analytics-goals-table :period="$period" :key="'goals-table-'.$period" />
                @break
        @endswitch
    </div>
</div>
```

---

## Page-Level Analytics

Integrated with the visual editor for per-page analytics:

```php
// src/Http/Livewire/PageAnalytics.php

namespace ArtisanPackUI\Analytics\Http\Livewire;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Livewire\Component;

class PageAnalytics extends Component
{
    public string $path;
    public string $period = '30d';

    public function mount(string $path): void
    {
        $this->path = $path;
    }

    public function render()
    {
        $query = app(AnalyticsQuery::class);
        $range = DateRange::fromPeriod($this->period);

        $analytics = $query->getPageAnalytics($this->path, $range);

        return view('analytics::livewire.page-analytics', [
            'analytics' => $analytics,
            'path' => $this->path,
        ]);
    }
}
```

**View:**

```blade
{{-- resources/views/livewire/page-analytics.blade.php --}}

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold">{{ __('Page Analytics') }}</h3>
        <select wire:model.live="period" class="text-sm border-gray-300 rounded-md">
            <option value="7d">{{ __('7 Days') }}</option>
            <option value="30d">{{ __('30 Days') }}</option>
            <option value="90d">{{ __('90 Days') }}</option>
        </select>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-sm text-gray-500">{{ __('Views') }}</p>
            <p class="text-2xl font-bold">{{ number_format($analytics['views']) }}</p>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-sm text-gray-500">{{ __('Unique Visitors') }}</p>
            <p class="text-2xl font-bold">{{ number_format($analytics['unique_visitors']) }}</p>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-sm text-gray-500">{{ __('Avg. Time on Page') }}</p>
            <p class="text-2xl font-bold">{{ $analytics['avg_time_on_page'] }}s</p>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-sm text-gray-500">{{ __('Scroll Depth') }}</p>
            <p class="text-2xl font-bold">{{ $analytics['avg_scroll_depth'] }}%</p>
        </div>
    </div>

    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <p class="text-sm text-gray-500 mb-2">{{ __('Views Over Time') }}</p>
        <div class="h-32" wire:ignore>
            <canvas
                x-data="{
                    chart: null,
                    init() {
                        this.chart = new Chart(this.$el, {
                            type: 'line',
                            data: {
                                labels: @js($analytics['views_over_time']->pluck('date')->toArray()),
                                datasets: [{
                                    data: @js($analytics['views_over_time']->pluck('views')->toArray()),
                                    borderColor: 'rgb(59, 130, 246)',
                                    tension: 0.4,
                                    fill: false,
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: { y: { beginAtZero: true } }
                            }
                        });
                    }
                }"
            ></canvas>
        </div>
    </div>
</div>
```

---

## Related Documents

- [01-architecture.md](./01-architecture.md) - Overall architecture
- [06-event-tracking.md](./06-event-tracking.md) - Event and goal tracking
- [09-api-reference.md](./09-api-reference.md) - API documentation

---

*Last Updated: January 3, 2026*
