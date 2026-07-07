---
title: AI Features
---

# AI Features

> **Since 1.3.0**

ArtisanPack UI Analytics ships four AI-powered features that plug into `artisanpack-ui/ai` to turn raw analytics into narrative insights. Every feature is opt-in, feature-flagged, and available as a Livewire component, React component, Vue component, and JSON API endpoint.

The four features are:

| Feature key | Purpose | Output shape |
|-------------|---------|--------------|
| `analytics.insight_summary` | Streaming plain-language summary of what changed over a date range | `{ summary, highlights[], concerns[] }` |
| `analytics.explain_anomaly` | Ranked hypotheses for a traffic spike or drop | `{ hypotheses:[{cause,confidence,evidence[]}], recommended_next_steps[] }` |
| `analytics.segment_insight` | Non-obvious patterns in a referrer, page, or time segment | `{ patterns:[{observation,significance,suggested_action}] }` |
| `analytics.digest_email` | Narrative body of the opt-in weekly/monthly digest email | `{ summary, key_points[], caveats[] }` |

Each feature ships an Agent class (`InsightSummaryAgent`, `AnomalyExplanationAgent`, `SegmentInsightAgent`, `DigestEmailAgent`), a Livewire trigger + blade, React and Vue components, and is registered via `AnalyticsServiceProvider::aiFeatures()`. All triggers render a disabled state when the feature is toggled off, so it is safe to ship them behind a config flag.

## Enabling AI Features

Every feature is opt-in per-app via `config/artisanpack.php`, which merges the AI package's feature registry. Toggle features on with the `enabled` flag:

```php
'ai' => [
    'features' => [
        'analytics.insight_summary' => [ 'enabled' => true ],
        'analytics.explain_anomaly' => [ 'enabled' => true ],
        'analytics.segment_insight' => [ 'enabled' => true ],
        'analytics.digest_email'    => [ 'enabled' => true ],
    ],
],
```

Features default to disabled. Anything published to the frontend (a Livewire component or React trigger) checks the registry on render and gracefully collapses to a disabled state if the feature is off.

## Authorization

All AI API endpoints are gated behind the `analytics.ai.use` ability. The shipped default allows any authenticated user through so upgrades are non-breaking:

```php
Gate::define( 'analytics.ai.use', fn ( $user ) => $user !== null );
```

Override the gate in your `AuthServiceProvider` to enforce a stricter policy — for example, restricting AI usage to admins or to seats that have purchased AI quota:

```php
Gate::define( 'analytics.ai.use', function ( User $user ) {
    return $user->hasRole( 'admin' ) || $user->team->has_ai_quota;
} );
```

## Insight Summary

Streams a plain-language summary of a date range's metrics, calling out highlights and concerns.

### Livewire

```blade
<livewire:artisanpack-analytics::ai.insight-summary
    :date-from="'2026-06-01'"
    :date-to="'2026-06-07'"
    :metrics="$weeklyMetrics"
/>
```

### React

```tsx
import { InsightSummary } from '@/vendor/artisanpack-analytics/react/components/ai';

<InsightSummary
    api={ { baseUrl: '/api/analytics' } }
    dateRange={ { from: '2026-06-01', to: '2026-06-07' } }
    metrics={ weeklyMetrics }
    compareTo={ { from: '2026-05-25', to: '2026-05-31' } }
/>
```

### Vue

```vue
<InsightSummary
    :api="{ baseUrl: '/api/analytics' }"
    :date-range="{ from: '2026-06-01', to: '2026-06-07' }"
    :metrics="weeklyMetrics"
/>
```

### API

```
POST /api/analytics/ai/insight-summary
```

**Request body**:

```json
{
    "date_range": { "from": "2026-06-01", "to": "2026-06-07" },
    "metrics": { "visitors": 12483, "pageviews": 41230, "bounce_rate": 0.42 },
    "compare_to": { "from": "2026-05-25", "to": "2026-05-31" }
}
```

**Response**: streams a `text/event-stream` with the final structured payload as the last event.

## Anomaly Explanation

Given a detected anomaly and lightweight context, returns ranked hypotheses for the cause.

### Livewire

```blade
<livewire:artisanpack-analytics::ai.anomaly-explanation
    :anomaly="$anomaly"
    :context="$context"
/>
```

`$anomaly` shape:

```php
[
    'metric'    => 'visitors',
    'direction' => 'up',
    'magnitude' => 2.4,      // multiplier vs baseline
    'date'      => '2026-06-05',
]
```

`$context` shape:

```php
[
    'recent_content_changes' => [ /* ... */ ],
    'referrer_deltas'        => [ /* ... */ ],
    'campaign_launches'      => [ /* ... */ ],
]
```

### API

```
POST /api/analytics/ai/explain-anomaly
```

Returns:

```json
{
    "hypotheses": [
        { "cause": "Referrer surge from Hacker News", "confidence": 0.82, "evidence": [ "..." ] }
    ],
    "recommended_next_steps": [ "..." ]
}
```

## Segment Insight

Surfaces patterns in a segment (referrer, page, or time window) relative to a baseline.

### Livewire

```blade
<livewire:artisanpack-analytics::ai.segment-insight
    :segment="[ 'type' => 'referrer', 'value' => 'news.ycombinator.com' ]"
    :metrics="$segmentMetrics"
    :baseline="$overallMetrics"
/>
```

### API

```
POST /api/analytics/ai/segment-insight
```

Response:

```json
{
    "patterns": [
        {
            "observation": "This segment converts 3.1× the site baseline.",
            "significance": "high",
            "suggested_action": "Explore paid amplification of the referring content."
        }
    ]
}
```

## Digest Email

Composes the AI-narrated body of an opt-in weekly or monthly digest email. Users pick their cadence via the `DigestSubscription` component, and a queued `SendDigestEmailJob` composes and sends each digest.

### Subscription component

```blade
<livewire:artisanpack-analytics::ai.digest-subscription />
```

The component persists the current user's preference (`off | weekly | monthly`) to the `analytics_digest_preferences` table. React and Vue equivalents (`DigestSubscription`) ship in the frontend bundle for Inertia apps.

### Scheduling digests

Dispatch digests on your chosen cadence with the shipped artisan command:

```php
// bootstrap/app.php (or routes/console.php)
Schedule::command( 'analytics:digests:dispatch --cadence=weekly' )->weeklyOn( 1, '08:00' );
Schedule::command( 'analytics:digests:dispatch --cadence=monthly' )->monthlyOn( 1, '08:00' );
```

The command queues one `SendDigestEmailJob` per subscribed user. Users with a preference of `off` (or no preference row) are skipped, so it is safe to run the command against a superset.

### Custom mailable

`DigestEmailMailable` renders `resources/views/emails/digest.blade.php`, a text-first template with structured headings for accessibility. Publish the view to customize it:

```bash
php artisan vendor:publish --tag=analytics-views
```

## Feature Registration

The `AnalyticsServiceProvider::aiFeatures()` method is what the AI package's feature registry reads at boot time. If you fork or extend the package and want to add a fifth feature — a custom agent, for example — return an additional entry from that method:

```php
public function aiFeatures(): array
{
    return array_merge( parent::aiFeatures(), [
        'analytics.retention_summary' => [
            'agent'       => RetentionSummaryAgent::class,
            'package'     => 'my-app/analytics-extensions',
            'label'       => __( 'Retention summary' ),
            'description' => __( 'Weekly retention cohort narrative.' ),
        ],
    ] );
}
```

## Cost and Rate Limiting

Every request routes through the AI package's shared client, so any rate limits, budget guards, or provider fallbacks configured there apply automatically. See the [`artisanpack-ui/ai` documentation](https://gitlab.com/jacob-martella-web-design/artisanpack-ui/ai) for provider configuration.

Streaming responses (currently `analytics.insight_summary`) send incremental tokens over `text/event-stream`; the final event is the structured JSON payload. Non-streaming features return a plain JSON response.
