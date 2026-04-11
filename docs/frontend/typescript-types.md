---
title: TypeScript Types
---

# TypeScript Types

> **Since 1.1.0**

Shared TypeScript type definitions used across both React and Vue components. Published to `resources/js/vendor/artisanpack-analytics/types/` during frontend installation.

## Importing Types

```typescript
// From React package
import type { StatsData, TopPageItem, DateRange } from '@/vendor/artisanpack-analytics/react';

// From Vue package
import type { StatsData, TopPageItem, DateRange } from '@/vendor/artisanpack-analytics/vue';

// Directly from types
import type { StatsData } from '@/vendor/artisanpack-analytics/types';
```

## Enums

### EventType

Event type identifiers for tracking.

```typescript
enum EventType {
    PAGE_VIEW = 'page_view',
    SESSION_START = 'session_start',
    SESSION_END = 'session_end',
    CLICK = 'click',
    SCROLL = 'scroll',
    SEARCH = 'search',
    DOWNLOAD = 'download',
    OUTBOUND_LINK = 'outbound_link',
    VIDEO_PLAY = 'video_play',
    VIDEO_COMPLETE = 'video_complete',
    FORM_START = 'form_start',
    FORM_SUBMIT = 'form_submit',
    FORM_ABANDON = 'form_abandon',
    PRODUCT_VIEW = 'product_view',
    ADD_TO_CART = 'add_to_cart',
    REMOVE_FROM_CART = 'remove_from_cart',
    BEGIN_CHECKOUT = 'begin_checkout',
    PURCHASE = 'purchase',
    BOOKING_START = 'booking_start',
    BOOKING_COMPLETE = 'booking_complete',
}
```

### ConsentCategory

```typescript
enum ConsentCategory {
    ANALYTICS = 'analytics',
    MARKETING = 'marketing',
    FUNCTIONAL = 'functional',
    PREFERENCES = 'preferences',
}
```

### GoalType

```typescript
enum GoalType {
    EVENT = 'event',
    PAGEVIEW = 'pageview',
    DURATION = 'duration',
    PAGES_PER_SESSION = 'pages_per_session',
}
```

### GoalValueType

```typescript
enum GoalValueType {
    NONE = 'none',
    FIXED = 'fixed',
    DYNAMIC = 'dynamic',
}
```

### AggregatePeriod

```typescript
enum AggregatePeriod {
    HOUR = 'hour',
    DAY = 'day',
    WEEK = 'week',
    MONTH = 'month',
}
```

### AggregateMetric

```typescript
enum AggregateMetric {
    PAGEVIEWS = 'pageviews',
    VISITORS = 'visitors',
    SESSIONS = 'sessions',
    BOUNCE_RATE = 'bounce_rate',
    AVG_DURATION = 'avg_duration',
    AVG_PAGES = 'avg_pages',
    EVENTS = 'events',
    CONVERSIONS = 'conversions',
    CONVERSION_VALUE = 'conversion_value',
}
```

### AggregateDimension

```typescript
enum AggregateDimension {
    PATH = 'path',
    COUNTRY = 'country',
    DEVICE_TYPE = 'device_type',
    BROWSER = 'browser',
    REFERRER_TYPE = 'referrer_type',
    UTM_SOURCE = 'utm_source',
    EVENT_NAME = 'event_name',
    EVENT_CATEGORY = 'event_category',
    GOAL_ID = 'goal_id',
}
```

## Type Aliases

```typescript
type DateRangePreset = '7d' | '30d' | '90d' | 'today' | 'yesterday'
    | 'this_week' | 'last_week' | 'this_month' | 'last_month' | 'this_year';

type DeviceType = 'desktop' | 'mobile' | 'tablet';

type ReferrerType = 'direct' | 'organic' | 'social' | 'referral' | 'email' | 'paid';

type EventCategory = 'forms' | 'ecommerce' | 'booking' | 'engagement' | null;
```

## API Response Types

### StatsData

```typescript
interface StatsData {
    pageviews: number;
    visitors: number;
    sessions: number;
    bounce_rate: number;
    avg_session_duration: number;
    pages_per_session?: number;
    realtime_visitors?: number;
    comparison?: StatsComparison | null;
}
```

### StatsComparison

```typescript
interface StatsComparison {
    pageviews: ComparisonValue;
    visitors: ComparisonValue;
    sessions: ComparisonValue;
    bounce_rate: ComparisonValue;
    avg_session_duration: ComparisonValue;
}

interface ComparisonValue {
    current: number;
    previous: number;
    change: number;
    change_percentage: number;
}
```

### TopPageItem

```typescript
interface TopPageItem {
    path: string;
    title: string;
    views: number;
    unique_views: number;
}
```

### TrafficSourceItem

```typescript
interface TrafficSourceItem {
    source: string;
    medium: string;
    sessions: number;
    visitors: number;
}
```

### Breakdown Types

```typescript
interface BrowserBreakdownItem {
    browser: string;
    version: string;
    sessions: number;
    percentage: number;
}

interface CountryBreakdownItem {
    country: string;
    country_code: string;
    sessions: number;
    percentage: number;
}

interface DeviceBreakdownItem {
    device_type: DeviceType;
    sessions: number;
    percentage: number;
}

interface EventBreakdownItem {
    name: string;
    category: string;
    count: number;
    total_value: number;
    percentage: number;
}
```

### Realtime Types

```typescript
interface RealtimeData {
    active_visitors: number;
    active_pages: RealtimePageView[];
    visitors_over_time: number[];
}

interface RealtimePageView {
    path: string;
    title: string;
    visitors: number;
}
```

### Consent Types

```typescript
interface ConsentStatusItem {
    category: string;
    granted: boolean;
    granted_at: string | null;
    expires_at: string | null;
}

interface ConsentUpdateRequest {
    visitor_id: string;
    categories: Record<string, boolean>;
}

interface ConsentUpdateResponse {
    success: boolean;
    categories: Record<string, boolean>;
}
```

### Query Params

```typescript
interface AnalyticsQueryParams {
    period?: DateRangePreset;
    start_date?: string;
    end_date?: string;
    site_id?: number;
    path?: string;
    category?: string;
    limit?: number;
}

interface RealtimeQueryParams {
    minutes?: number;
    site_id?: number;
}
```

## Data Transfer Objects

### DateRange

```typescript
interface DateRange {
    start_date: string;
    end_date: string;
}
```

### DeviceInfo

```typescript
interface DeviceInfo {
    device_type: DeviceType;
    browser: string;
    browser_version: string;
    os: string;
    os_version: string;
    is_bot: boolean;
}
```

### PageViewData

```typescript
interface PageViewData {
    path: string;
    title: string;
    referrer: string | null;
    device_type: DeviceType;
    browser: string;
    browser_version: string;
    os: string;
    os_version: string;
    viewport_width: number;
    viewport_height: number;
    screen_width: number;
    screen_height: number;
    utm_source: string | null;
    utm_medium: string | null;
    utm_campaign: string | null;
    utm_term: string | null;
    utm_content: string | null;
    load_time: number | null;
    dom_ready_time: number | null;
    custom_data: Record<string, unknown> | null;
}
```

### EventData

```typescript
interface EventData {
    name: string;
    properties: Record<string, unknown>;
    session_id: string | null;
    visitor_id: string | null;
    page_view_id: number | null;
    category: EventCategory;
    action: string | null;
    label: string | null;
    value: number | null;
}
```

## Model Interfaces

Full TypeScript interfaces for all Eloquent models (Visitor, Session, PageView, AnalyticsEvent, Goal, Conversion, Consent, Site, Aggregate) are available in `types/models.ts`. These mirror the PHP model properties and relationships.

## Dashboard Component Props

Props interfaces for all dashboard page components are available in `types/dashboard.ts`:

- `AnalyticsDashboardProps`
- `PageAnalyticsProps`
- `MultiTenantDashboardProps`
- `PlatformDashboardProps`
- `ChartData`, `ChartDataset`
- `DashboardStats`, `DashboardTab`
