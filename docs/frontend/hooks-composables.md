---
title: Hooks & Composables
---

# Hooks & Composables

> **Since 1.1.0**

Shared logic for analytics data fetching and consent management, available as React hooks and Vue composables.

## useAnalyticsApi

A generic data fetching hook/composable for the analytics API with support for polling, AbortController-based cleanup, and typed responses.

### React

```tsx
import { useAnalyticsApi } from '@/vendor/artisanpack-analytics/react';
import type { StatsData, AnalyticsQueryParams } from '@/vendor/artisanpack-analytics/react';

function StatsWidget() {
    const { data, loading, error, refresh } = useAnalyticsApi<StatsData>({
        endpoint: '/api/analytics/stats',
        params: { period: '30d', site_id: 1 },
        pollInterval: 30000, // Refresh every 30 seconds
        fetchOnMount: true,
    });

    return (
        <div>
            {loading && <span>Loading...</span>}
            {error && <span>Error: {error}</span>}
            {data && <span>{data.pageviews} pageviews</span>}
            <button onClick={refresh}>Refresh</button>
        </div>
    );
}
```

### Vue

```vue
<script setup lang="ts">
import { useAnalyticsApi } from '@/vendor/artisanpack-analytics/vue';
import type { StatsData } from '@/vendor/artisanpack-analytics/vue';

const { data, loading, error, refresh } = useAnalyticsApi<StatsData>({
    endpoint: '/api/analytics/stats',
    params: { period: '30d', site_id: 1 },
    pollInterval: 30000,
    fetchOnMount: true,
});
</script>
```

### Options

```typescript
interface UseAnalyticsApiOptions<T> {
    endpoint: string;
    params?: AnalyticsQueryParams | RealtimeQueryParams;
    pollInterval?: number;
    initialData?: T;
    fetchOnMount?: boolean;
}
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `endpoint` | `string` | — | **Required.** API endpoint path (e.g., `/api/analytics/stats`) |
| `params` | `AnalyticsQueryParams \| RealtimeQueryParams` | `{}` | Query parameters sent with each request |
| `pollInterval` | `number` | — | Polling interval in milliseconds. Omit to disable polling. |
| `initialData` | `T` | `undefined` | Initial data before the first fetch completes |
| `fetchOnMount` | `boolean` | `true` | Whether to fetch immediately on mount |

### Return Value

```typescript
interface UseAnalyticsApiResult<T> {
    data: T | undefined;     // Fetched data (React: state, Vue: Ref<T>)
    loading: boolean;        // Whether a request is in progress
    error: string | null;    // Error message, or null
    refresh: () => void;     // Manually trigger a new fetch
}
```

### Features

- **AbortController**: Requests are automatically cancelled on unmount or when a new request starts, preventing stale data from overwriting fresh data.
- **Polling**: Set `pollInterval` to automatically refetch data on a timer. Polling does not abort in-flight requests.
- **Reactive params**: In Vue, changes to reactive `params` automatically trigger a new fetch.
- **Query string building**: Params are automatically serialized as URL query parameters.

## useConsent

A comprehensive consent management hook/composable that handles consent state, persistence, and server synchronization.

### React

```tsx
import { useConsent } from '@/vendor/artisanpack-analytics/react';

function ConsentManager() {
    const {
        loading,
        error,
        consentRequired,
        categories,
        hasConsent,
        grantConsent,
        revokeConsent,
        acceptAll,
        rejectAll,
        updateConsent,
        refresh,
    } = useConsent({
        apiPrefix: '/api/analytics',
        fetchOnMount: true,
    });

    return (
        <div>
            <p>Analytics consent: {hasConsent('analytics') ? 'Granted' : 'Denied'}</p>
            <button onClick={() => grantConsent('analytics')}>Grant Analytics</button>
            <button onClick={() => revokeConsent('analytics')}>Revoke Analytics</button>
            <button onClick={acceptAll}>Accept All</button>
            <button onClick={rejectAll}>Reject All</button>
        </div>
    );
}
```

### Vue

```vue
<script setup lang="ts">
import { useConsent } from '@/vendor/artisanpack-analytics/vue';

const {
    loading,
    error,
    consentRequired,
    categories,
    hasConsent,
    grantConsent,
    revokeConsent,
    acceptAll,
    rejectAll,
    updateConsent,
    refresh,
} = useConsent({
    apiPrefix: '/api/analytics',
    fetchOnMount: true,
});
</script>
```

### Options

```typescript
interface UseConsentOptions {
    apiPrefix?: string;
    fetchOnMount?: boolean;
    initialCategories?: Record<string, boolean>;
    initialConsentRequired?: boolean;
}
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiPrefix` | `string` | `''` | API endpoint prefix (e.g., `/api/analytics`) |
| `fetchOnMount` | `boolean` | `true` | Whether to fetch consent status on mount |
| `initialCategories` | `Record<string, boolean>` | `{}` | Initial consent category state |
| `initialConsentRequired` | `boolean` | `true` | Whether consent is required before tracking |

### Return Value

| Property/Method | Type | Description |
|----------------|------|-------------|
| `loading` | `boolean` | Whether a consent API request is in progress |
| `error` | `string \| null` | Error message, or null |
| `consentRequired` | `boolean` | Whether consent is required by the config |
| `categories` | `Record<string, boolean>` | Current consent state per category |
| `hasConsent(category)` | `(category: string) => boolean` | Check if a specific category is granted |
| `grantConsent(category)` | `(category: string) => Promise<void>` | Grant consent for a category |
| `revokeConsent(category)` | `(category: string) => Promise<void>` | Revoke consent for a category |
| `acceptAll()` | `() => Promise<void>` | Grant all categories |
| `rejectAll()` | `() => Promise<void>` | Revoke all categories |
| `updateConsent(categories)` | `(categories: Record<string, boolean>) => Promise<void>` | Bulk update consent |
| `refresh()` | `() => Promise<void>` | Refetch consent status from server |

### Persistence

Consent state is persisted in multiple locations for reliability:

- **localStorage**: Stores full consent state under the `artisanpack_consent` key
- **Cookies**: Sets a `artisanpack_consent` cookie (365-day expiry) for server-side access
- **Visitor ID**: A unique visitor identifier is stored in both localStorage (`artisanpack_visitor_id`) and a cookie for consent record association
- **Server**: Consent is synced to the server via the consent API endpoints with CSRF token protection

### Optimistic Updates

The hook uses optimistic updates for a responsive UI:

1. Local state is updated immediately when the user makes a consent choice
2. The change is sent to the server in the background
3. If the server request fails, the local state is rolled back to the previous value

### Security

- All consent API requests include a CSRF token from the `XSRF-TOKEN` cookie
- The `Secure` flag is set on cookies when the page is served over HTTPS
- Concurrent update races are guarded against with request tracking
