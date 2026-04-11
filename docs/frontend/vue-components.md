---
title: Vue Components
---

# Vue Components

> **Since 1.1.0**

Pre-built Vue 3 dashboard components for displaying analytics data via Inertia.js. All components use the Composition API with TypeScript and are designed to work with the `InertiaDashboardController` page props.

## Installation

```bash
php artisan analytics:install-frontend --stack=vue
npm install && npm run dev
```

Components are published to `resources/js/vendor/artisanpack-analytics/vue/`.

## Importing Components

```vue
<script setup lang="ts">
// Individual imports
import { StatsCards, VisitorsChart, TopPages } from '@/vendor/artisanpack-analytics/vue';

// Or import specific components
import { AnalyticsDashboard } from '@/vendor/artisanpack-analytics/vue';
</script>
```

## Dashboard Pages

### AnalyticsDashboard

The main dashboard page component. Receives data as Inertia page props from the `InertiaDashboardController::index()` method.

```vue
<script setup lang="ts">
import { AnalyticsDashboard } from '@/vendor/artisanpack-analytics/vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <AnalyticsDashboard v-bind="page.props" />
</template>
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

```vue
<script setup lang="ts">
import { PageAnalytics } from '@/vendor/artisanpack-analytics/vue';
</script>
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

```vue
<script setup lang="ts">
import { MultiTenantDashboard } from '@/vendor/artisanpack-analytics/vue';
</script>
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

```vue
<template>
    <StatsCards
        :stats="{
            pageviews: 12500,
            visitors: 3200,
            sessions: 4100,
            bounce_rate: 45.2,
            avg_session_duration: 185,
            pages_per_session: 3.2,
            realtime_visitors: 42,
            comparison: null,
        }"
        class="my-4"
    />
</template>
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
| `class` | `string` | Additional CSS classes |

### VisitorsChart

Line chart displaying visitors and pageviews over time.

```vue
<template>
    <VisitorsChart :data="chartData" />
</template>
```

### TopPages

Table of most viewed pages with view counts.

```vue
<template>
    <TopPages :pages="topPages" />
</template>
```

### TrafficSources

Traffic source breakdown showing source, medium, sessions, and visitors.

```vue
<template>
    <TrafficSources :sources="trafficSources" />
</template>
```

### RealtimeVisitors

Live visitor count with automatic polling.

```vue
<template>
    <RealtimeVisitors />
</template>
```

### SiteSelector

Site picker dropdown for multi-tenant setups.

```vue
<template>
    <SiteSelector />
</template>
```

## Customization

All components are published to your application's `resources/js/` directory as Vue SFCs, so you can modify them directly. The components use the Composition API with `<script setup>` and can be styled with Tailwind CSS classes.

### Overriding a Component

Since components are published as source files, you can:

1. Edit the published component directly
2. Create a wrapper component that adds custom behavior
3. Build your own components using the `useAnalyticsApi` composable for data fetching

### Using the Composable Directly

Build custom dashboards using the `useAnalyticsApi` composable:

```vue
<script setup lang="ts">
import { useAnalyticsApi } from '@/vendor/artisanpack-analytics/vue';

const { data, loading, error, refresh } = useAnalyticsApi({
    endpoint: '/api/analytics/stats',
    params: { period: '30d' },
    pollInterval: 30000,
});
</script>

<template>
    <div v-if="loading">Loading...</div>
    <div v-else-if="error">Error: {{ error }}</div>
    <div v-else>{{ data?.pageviews }} pageviews</div>
</template>
```

See [Hooks & Composables](Frontend-Hooks-Composables) for the full API reference.
