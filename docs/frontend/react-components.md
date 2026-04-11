---
title: React Components
---

# React Components

> **Since 1.1.0**

Pre-built React dashboard components for displaying analytics data via Inertia.js. All components are written in TypeScript and designed to work with the `InertiaDashboardController` page props.

## Installation

```bash
php artisan analytics:install-frontend --stack=react
npm install && npm run dev
```

Components are published to `resources/js/vendor/artisanpack-analytics/react/`.

## Importing Components

```tsx
// Individual imports
import { StatsCards, VisitorsChart, TopPages } from '@/vendor/artisanpack-analytics/react';

// Or import specific components
import { AnalyticsDashboard } from '@/vendor/artisanpack-analytics/react';
```

## Dashboard Pages

### AnalyticsDashboard

The main dashboard page component. Receives data as Inertia page props from the `InertiaDashboardController::index()` method.

```tsx
import { AnalyticsDashboard } from '@/vendor/artisanpack-analytics/react';

export default function Dashboard() {
    return <AnalyticsDashboard {...usePage().props} />;
}
```

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `stats` | `StatsCardsProps['stats']` | Key metrics object |
| `chartData` | `ChartDataPoint[]` | Time series data for the visitors chart |
| `topPages` | `TopPageItem[]` | Most viewed pages |
| `trafficSources` | `TrafficSourceItem[]` | Traffic source breakdown |
| `dateRange` | `DateRange` | Current date range |
| `dateRangePreset` | `string` | Active preset (e.g., `'30d'`) |
| `dateRangePresets` | `Record<string, string>` | Available presets |
| `filters` | `Record<string, unknown>` | Active filters |

### PageAnalytics

Per-page analytics detail view.

```tsx
import { PageAnalytics } from '@/vendor/artisanpack-analytics/react';
```

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `path` | `string` | The page path being analyzed |
| `analytics` | `PageAnalyticsData` | Pageviews, visitors, bounce rate |
| `viewsOverTime` | `PageViewOverTimeItem[]` | Time series for this page |
| `showChart` | `boolean` | Whether to show the chart |
| `compact` | `boolean` | Compact display mode |

### MultiTenantDashboard

Dashboard view for multi-site management.

```tsx
import { MultiTenantDashboard } from '@/vendor/artisanpack-analytics/react';
```

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `siteId` | `number` | Current site ID |
| `dateRangePreset` | `string` | Active date range preset |
| `multiTenantEnabled` | `boolean` | Whether multi-tenancy is enabled |
| `currentSite` | `Site` | Current site model |
| `showSiteSelector` | `boolean` | Whether to show the site selector |

## Widget Components

### StatsCards

Displays key analytics metrics in a card grid.

```tsx
import { StatsCards } from '@/vendor/artisanpack-analytics/react';

<StatsCards
    stats={{
        pageviews: 12500,
        visitors: 3200,
        sessions: 4100,
        bounce_rate: 45.2,
        avg_session_duration: 185,
        pages_per_session: 3.2,
        realtime_visitors: 42,
        comparison: null,
    }}
    className="my-4"
/>
```

**Props:**

| Prop | Type | Description |
|------|------|-------------|
| `stats.pageviews` | `number` | Total page views |
| `stats.visitors` | `number` | Unique visitors |
| `stats.sessions` | `number` | Total sessions |
| `stats.bounce_rate` | `number` | Bounce rate percentage |
| `stats.avg_session_duration` | `number` | Average duration in seconds |
| `stats.pages_per_session` | `number` | Average pages per session (optional) |
| `stats.realtime_visitors` | `number` | Current live visitors (optional) |
| `stats.comparison` | `StatsComparison \| null` | Period comparison data |
| `className` | `string` | Additional CSS classes |

### VisitorsChart

Line chart displaying visitors and pageviews over time.

```tsx
import { VisitorsChart } from '@/vendor/artisanpack-analytics/react';

<VisitorsChart data={chartData} />
```

### TopPages

Table of most viewed pages with view counts.

```tsx
import { TopPages } from '@/vendor/artisanpack-analytics/react';

<TopPages pages={topPages} />
```

### TrafficSources

Traffic source breakdown showing source, medium, sessions, and visitors.

```tsx
import { TrafficSources } from '@/vendor/artisanpack-analytics/react';

<TrafficSources sources={trafficSources} />
```

### RealtimeVisitors

Live visitor count with automatic polling.

```tsx
import { RealtimeVisitors } from '@/vendor/artisanpack-analytics/react';

<RealtimeVisitors />
```

### SiteSelector

Site picker dropdown for multi-tenant setups.

```tsx
import { SiteSelector } from '@/vendor/artisanpack-analytics/react';

<SiteSelector />
```

## Customization

All components are published to your application's `resources/js/` directory, so you can modify them directly. The components use standard React patterns and can be styled with Tailwind CSS classes.

### Overriding a Component

Since components are published as source files, you can:

1. Edit the published component directly
2. Create a wrapper component that adds custom behavior
3. Build your own components using the `useAnalyticsApi` hook for data fetching

### Using the Hook Directly

Build custom dashboards using the `useAnalyticsApi` hook:

```tsx
import { useAnalyticsApi } from '@/vendor/artisanpack-analytics/react';

function CustomWidget() {
    const { data, loading, error, refresh } = useAnalyticsApi({
        endpoint: '/api/analytics/stats',
        params: { period: '30d' },
        pollInterval: 30000,
    });

    if (loading) return <div>Loading...</div>;
    if (error) return <div>Error: {error}</div>;

    return <div>{data?.pageviews} pageviews</div>;
}
```

See [Hooks & Composables](Frontend-Hooks-Composables) for the full API reference.
