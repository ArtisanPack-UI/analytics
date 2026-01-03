# Analytics Package Architecture

**Purpose:** Define the overall architecture, data flow, and component relationships
**Last Updated:** January 3, 2026

---

## Overview

The analytics package follows a **provider-based architecture** with the local database provider as the primary (default) provider. This architecture enables:

1. **Privacy-first tracking** - Data stays in your database by default
2. **Extensibility** - Add external providers (GA4, Plausible) without changing core code
3. **Hybrid deployments** - Use local tracking alongside external services
4. **Multi-tenant isolation** - Support for SaaS deployments with data separation

---

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              Client Layer                                    │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                     analytics.js (Tracker)                              │ │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐   │ │
│  │  │   Page      │  │   Event     │  │  Session    │  │  Consent    │   │ │
│  │  │  Tracker    │  │  Tracker    │  │  Manager    │  │  Checker    │   │ │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘   │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       │ HTTP (POST /api/analytics/*)
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              API Layer                                       │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                    AnalyticsController                                  │ │
│  │  POST /api/analytics/pageview     POST /api/analytics/event            │ │
│  │  POST /api/analytics/session      GET  /api/analytics/consent          │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                       │                                      │
│                                       ▼                                      │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                      Middleware Pipeline                                │ │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐   │ │
│  │  │  Throttle   │  │   Privacy   │  │   Tenant    │  │    CORS     │   │ │
│  │  │   Limiter   │  │   Filter    │  │  Resolver   │  │   Handler   │   │ │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘   │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                             Service Layer                                    │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                      AnalyticsService                                   │ │
│  │  • Validates and normalizes incoming data                              │ │
│  │  • Routes data to configured providers                                 │ │
│  │  • Handles privacy filtering and anonymization                         │ │
│  │  • Manages session and visitor identification                          │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
│                                       │                                      │
│         ┌─────────────────────────────┼─────────────────────────────┐       │
│         ▼                             ▼                             ▼       │
│  ┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐   │
│  │ LocalProvider   │       │   GA4Provider   │       │PlausibleProvider│   │
│  │  (Default)      │       │   (Optional)    │       │   (Optional)    │   │
│  │                 │       │                 │       │                 │   │
│  │ Stores in DB    │       │ Sends to GA4    │       │ Sends to API    │   │
│  └─────────────────┘       └─────────────────┘       └─────────────────┘   │
│         │                                                                    │
│         ▼                                                                    │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                    Data Processing Layer                                │ │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐   │ │
│  │  │   Visitor   │  │   Session   │  │  PageView   │  │   Event     │   │ │
│  │  │  Resolver   │  │  Manager    │  │  Recorder   │  │  Processor  │   │ │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘   │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            Storage Layer                                     │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                         Database Tables                                 │ │
│  │  ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌───────────┐ ┌──────────┐ │ │
│  │  │ visitors  │ │ sessions  │ │page_views │ │  events   │ │  goals   │ │ │
│  │  └───────────┘ └───────────┘ └───────────┘ └───────────┘ └──────────┘ │ │
│  │  ┌───────────┐ ┌───────────┐ ┌───────────┐                            │ │
│  │  │conversions│ │ consents  │ │aggregates │                            │ │
│  │  └───────────┘ └───────────┘ └───────────┘                            │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          Presentation Layer                                  │
│                                                                              │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐              │
│  │ Dashboard       │  │ Widget          │  │ Page-Level      │              │
│  │ (Full Page)     │  │ Components      │  │ Analytics       │              │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘              │
│           │                   │                     │                        │
│           └───────────────────┴─────────────────────┘                        │
│                               │                                              │
│                               ▼                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                      AnalyticsQuery Service                             │ │
│  │  • Builds optimized queries for metrics                                │ │
│  │  • Handles date ranges and filtering                                   │ │
│  │  • Calculates derived metrics (bounce rate, etc.)                      │ │
│  │  • Caches results for performance                                      │ │
│  └────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Component Details

### 1. Client Layer (JavaScript Tracker)

The JavaScript tracker runs in the browser and collects analytics data.

```javascript
// analytics.js structure
const ArtisanPackAnalytics = {
    // Configuration
    config: {
        endpoint: '/api/analytics',
        sessionTimeout: 30 * 60 * 1000, // 30 minutes
        respectDNT: true,
        consentRequired: true,
    },

    // Core modules
    tracker: {
        pageView(),
        event(name, properties),
        identify(userId),
    },

    session: {
        start(),
        end(),
        extend(),
        getId(),
    },

    visitor: {
        getId(),
        getFingerprint(),
    },

    consent: {
        check(),
        grant(),
        revoke(),
    },
};
```

**Key Responsibilities:**
- Track page views automatically on navigation
- Track custom events via API
- Manage session lifecycle (start, extend, end)
- Generate/store visitor fingerprint
- Check consent before tracking
- Respect Do Not Track header
- Capture performance metrics
- Handle offline/retry scenarios

### 2. API Layer

RESTful endpoints for receiving analytics data.

```php
// routes/api.php

Route::prefix('analytics')->group(function () {
    // Data collection endpoints
    Route::post('/pageview', [AnalyticsController::class, 'pageview']);
    Route::post('/event', [AnalyticsController::class, 'event']);
    Route::post('/session/start', [AnalyticsController::class, 'startSession']);
    Route::post('/session/end', [AnalyticsController::class, 'endSession']);

    // Consent endpoints
    Route::get('/consent', [ConsentController::class, 'status']);
    Route::post('/consent', [ConsentController::class, 'update']);

    // Query endpoints (authenticated)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/stats', [AnalyticsQueryController::class, 'stats']);
        Route::get('/pages', [AnalyticsQueryController::class, 'pages']);
        Route::get('/sources', [AnalyticsQueryController::class, 'sources']);
        Route::get('/events', [AnalyticsQueryController::class, 'events']);
    });
});
```

### 3. Service Layer

The core business logic layer.

```php
// Service interfaces

interface AnalyticsServiceInterface
{
    public function trackPageView(PageViewData $data): void;
    public function trackEvent(EventData $data): void;
    public function startSession(SessionData $data): Session;
    public function endSession(string $sessionId): void;
    public function resolveVisitor(VisitorData $data): Visitor;
}

interface AnalyticsProviderInterface
{
    public function trackPageView(PageViewData $data): void;
    public function trackEvent(EventData $data): void;
    public function isEnabled(): bool;
    public function getName(): string;
}

interface AnalyticsQueryInterface
{
    public function getPageViews(DateRange $range, array $filters = []): int;
    public function getVisitors(DateRange $range, array $filters = []): int;
    public function getSessions(DateRange $range, array $filters = []): int;
    public function getTopPages(DateRange $range, int $limit = 10): Collection;
    public function getTrafficSources(DateRange $range, int $limit = 10): Collection;
    public function getBounceRate(DateRange $range): float;
    public function getAverageSessionDuration(DateRange $range): int;
}
```

### 4. Provider System

Extensible provider architecture for multiple analytics backends.

```php
// Provider registration

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AnalyticsManager::class, function ($app) {
            return new AnalyticsManager($app);
        });

        $this->app->singleton('analytics', function ($app) {
            return $app->make(AnalyticsManager::class);
        });
    }

    public function boot(): void
    {
        // Register built-in providers
        $manager = $this->app->make(AnalyticsManager::class);

        $manager->extend('local', function ($app) {
            return new LocalAnalyticsProvider($app['config']['analytics.local']);
        });

        $manager->extend('google', function ($app) {
            return new GoogleAnalyticsProvider($app['config']['analytics.providers.google']);
        });

        $manager->extend('plausible', function ($app) {
            return new PlausibleProvider($app['config']['analytics.providers.plausible']);
        });
    }
}
```

**Provider Interface:**

```php
abstract class AnalyticsProvider implements AnalyticsProviderInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    abstract public function trackPageView(PageViewData $data): void;
    abstract public function trackEvent(EventData $data): void;

    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    public function getName(): string
    {
        return static::class;
    }
}
```

### 5. Data Processing Layer

Handles visitor resolution, session management, and data normalization.

```php
// VisitorResolver - Creates or finds existing visitors

class VisitorResolver
{
    public function resolve(VisitorData $data): Visitor
    {
        // Generate fingerprint from request data
        $fingerprint = $this->generateFingerprint($data);

        // Find or create visitor
        return Visitor::firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'ip_address' => $this->anonymizeIp($data->ipAddress),
                'user_agent' => $data->userAgent,
                'country' => $data->country,
                'device_type' => $data->deviceType,
                'browser' => $data->browser,
                'os' => $data->os,
            ]
        );
    }

    protected function generateFingerprint(VisitorData $data): string
    {
        // Privacy-preserving fingerprint
        return hash('sha256', implode('|', [
            $data->userAgent,
            $data->screenResolution,
            $data->timezone,
            $data->language,
            // NOT including IP for privacy
        ]));
    }

    protected function anonymizeIp(string $ip): string
    {
        if (!config('analytics.local.anonymize_ip')) {
            return $ip;
        }

        // Zero out last octet for IPv4, last 80 bits for IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        return preg_replace('/:[^:]+:[^:]+:[^:]+:[^:]+:[^:]+$/', ':0:0:0:0:0', $ip);
    }
}
```

```php
// SessionManager - Handles session lifecycle

class SessionManager
{
    public function start(Visitor $visitor, array $data): Session
    {
        return Session::create([
            'visitor_id' => $visitor->id,
            'session_id' => Str::uuid(),
            'started_at' => now(),
            'last_activity_at' => now(),
            'entry_page' => $data['path'],
            'referrer' => $data['referrer'],
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
        ]);
    }

    public function extend(string $sessionId): void
    {
        Session::where('session_id', $sessionId)
            ->update(['last_activity_at' => now()]);
    }

    public function end(string $sessionId): void
    {
        $session = Session::where('session_id', $sessionId)->first();

        if ($session) {
            $session->update([
                'ended_at' => now(),
                'duration' => now()->diffInSeconds($session->started_at),
                'page_count' => $session->pageViews()->count(),
                'is_bounce' => $session->pageViews()->count() === 1,
            ]);
        }
    }
}
```

---

## Data Flow

### Page View Tracking Flow

```
1. User navigates to page
         │
         ▼
2. analytics.js triggers pageview
         │
         ▼
3. Check consent status
         │
    ┌────┴────┐
    ▼         ▼
No consent  Has consent
    │         │
    ▼         ▼
  Skip      Continue
              │
              ▼
4. Collect page data:
   - URL, title, referrer
   - Viewport, screen size
   - Performance metrics
   - Session ID, visitor ID
         │
         ▼
5. POST /api/analytics/pageview
         │
         ▼
6. Middleware pipeline:
   - Rate limiting
   - Privacy filter
   - Tenant resolution
         │
         ▼
7. AnalyticsService.trackPageView()
         │
         ▼
8. For each enabled provider:
   - LocalProvider → Database
   - GA4Provider → Google API
   - PlausibleProvider → Plausible API
         │
         ▼
9. Response (204 No Content)
```

### Event Tracking Flow

```
1. User action (click, form submit, etc.)
         │
         ▼
2. analytics.event('event_name', {properties})
         │
         ▼
3. Check consent for event category
         │
         ▼
4. POST /api/analytics/event
   - event_name
   - properties (JSON)
   - session_id
   - page context
         │
         ▼
5. EventProcessor validates:
   - Event schema
   - Property types
   - Required fields
         │
         ▼
6. Check if event matches any goals
         │
    ┌────┴────┐
    ▼         ▼
No match   Matches goal
    │         │
    ▼         ▼
 Store     Store + Create
 Event     Conversion
         │
         ▼
7. Route to providers
```

### Session Lifecycle

```
1. First page view (no session cookie)
         │
         ▼
2. Create new session
   - Generate session_id
   - Set session cookie
   - Record entry page
   - Capture referrer/UTM
         │
         ▼
3. Subsequent page views
   - Extend session (update last_activity_at)
   - Increment page count
         │
         ▼
4. Session timeout check (30 min inactivity)
         │
    ┌────┴────┐
    ▼         ▼
 Active    Expired
    │         │
    ▼         ▼
Continue   End session
              │
              ▼
          Calculate:
          - Duration
          - Page count
          - Bounce status
         │
         ▼
5. User leaves (beforeunload)
   - Send session end beacon
```

---

## Directory Structure

```
analytics/
├── config/
│   └── analytics.php
├── database/
│   └── migrations/
│       ├── 2026_01_01_000001_create_analytics_visitors_table.php
│       ├── 2026_01_01_000002_create_analytics_sessions_table.php
│       ├── 2026_01_01_000003_create_analytics_page_views_table.php
│       ├── 2026_01_01_000004_create_analytics_events_table.php
│       ├── 2026_01_01_000005_create_analytics_goals_table.php
│       ├── 2026_01_01_000006_create_analytics_conversions_table.php
│       ├── 2026_01_01_000007_create_analytics_consents_table.php
│       └── 2026_01_01_000008_create_analytics_aggregates_table.php
├── resources/
│   ├── js/
│   │   ├── analytics.js
│   │   └── analytics.min.js
│   └── views/
│       ├── components/
│       │   ├── tracker.blade.php
│       │   ├── consent-banner.blade.php
│       │   └── stats-card.blade.php
│       └── livewire/
│           ├── dashboard.blade.php
│           ├── widgets/
│           │   ├── visitors-chart.blade.php
│           │   ├── top-pages.blade.php
│           │   ├── traffic-sources.blade.php
│           │   └── real-time.blade.php
│           └── page-analytics.blade.php
├── routes/
│   ├── api.php
│   └── web.php
├── src/
│   ├── AnalyticsServiceProvider.php
│   ├── Analytics.php
│   ├── Contracts/
│   │   ├── AnalyticsProviderInterface.php
│   │   ├── AnalyticsQueryInterface.php
│   │   └── AnalyticsServiceInterface.php
│   ├── Data/
│   │   ├── PageViewData.php
│   │   ├── EventData.php
│   │   ├── SessionData.php
│   │   ├── VisitorData.php
│   │   └── DateRange.php
│   ├── Facades/
│   │   └── Analytics.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AnalyticsController.php
│   │   │   ├── ConsentController.php
│   │   │   └── AnalyticsQueryController.php
│   │   ├── Livewire/
│   │   │   ├── AnalyticsDashboard.php
│   │   │   ├── PageAnalytics.php
│   │   │   └── Widgets/
│   │   │       ├── VisitorsChart.php
│   │   │       ├── TopPages.php
│   │   │       ├── TrafficSources.php
│   │   │       ├── StatsCards.php
│   │   │       └── RealTimeVisitors.php
│   │   └── Middleware/
│   │       ├── AnalyticsThrottle.php
│   │       ├── PrivacyFilter.php
│   │       └── TenantResolver.php
│   ├── Jobs/
│   │   ├── ProcessPageView.php
│   │   ├── ProcessEvent.php
│   │   ├── AggregateAnalytics.php
│   │   └── CleanupOldData.php
│   ├── Models/
│   │   ├── Visitor.php
│   │   ├── Session.php
│   │   ├── PageView.php
│   │   ├── Event.php
│   │   ├── Goal.php
│   │   ├── Conversion.php
│   │   ├── Consent.php
│   │   └── Aggregate.php
│   ├── Providers/
│   │   ├── LocalAnalyticsProvider.php
│   │   ├── GoogleAnalyticsProvider.php
│   │   └── PlausibleProvider.php
│   ├── Services/
│   │   ├── AnalyticsManager.php
│   │   ├── AnalyticsService.php
│   │   ├── AnalyticsQuery.php
│   │   ├── VisitorResolver.php
│   │   ├── SessionManager.php
│   │   ├── EventProcessor.php
│   │   ├── GoalMatcher.php
│   │   └── DataRetention.php
│   └── Traits/
│       ├── HasAnalytics.php
│       └── TracksEvents.php
├── tests/
│   ├── Feature/
│   │   ├── PageViewTrackingTest.php
│   │   ├── EventTrackingTest.php
│   │   ├── SessionManagementTest.php
│   │   ├── GoalConversionTest.php
│   │   └── DashboardTest.php
│   └── Unit/
│       ├── VisitorResolverTest.php
│       ├── SessionManagerTest.php
│       ├── AnalyticsQueryTest.php
│       └── DataRetentionTest.php
├── composer.json
├── README.md
└── CHANGELOG.md
```

---

## Integration Points

### CMS Framework Integration

```php
// In cms-framework, register analytics dashboard widget

class CmsFrameworkServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (class_exists(AnalyticsDashboard::class)) {
            Dashboard::registerWidget(
                'analytics',
                AnalyticsDashboardWidget::class,
                ['position' => 'top', 'size' => 'full']
            );
        }
    }
}
```

### Visual Editor Integration (Page-Level Analytics)

```php
// In visual-editor, add analytics tab to page editor

class PageEditor extends Component
{
    public function render()
    {
        return view('visual-editor::page-editor', [
            'showAnalytics' => class_exists(PageAnalytics::class),
        ]);
    }
}
```

### Forms Package Integration

```php
// In forms package, fire analytics event on submission

class FormRenderer extends Component
{
    public function submit()
    {
        // ... form processing ...

        // Track form submission event
        if (class_exists(\ArtisanPackUI\Analytics\Facades\Analytics::class)) {
            Analytics::event('form_submitted', [
                'form_id' => $this->form->id,
                'form_name' => $this->form->name,
            ]);
        }
    }
}
```

### Ecommerce Package Integration

```php
// In ecommerce package, track purchase events

class CheckoutService
{
    public function completeOrder(string $paymentIntentId, array $customerData): Order
    {
        $order = // ... create order ...

        // Track purchase event
        if (class_exists(\ArtisanPackUI\Analytics\Facades\Analytics::class)) {
            Analytics::event('purchase', [
                'order_id' => $order->order_number,
                'total' => $order->total,
                'currency' => $order->currency,
                'items' => $order->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                ])->toArray(),
            ]);
        }

        return $order;
    }
}
```

### Future Privacy Package Integration

```php
// Hook points for privacy package integration

// In AnalyticsService
public function trackPageView(PageViewData $data): void
{
    // Check with privacy package if available
    if (app()->bound('privacy') && !app('privacy')->canTrack('analytics')) {
        return;
    }

    // ... tracking logic ...
}

// Consent delegation
public function hasConsent(string $category = 'analytics'): bool
{
    // Delegate to privacy package if available
    if (app()->bound('privacy')) {
        return app('privacy')->hasConsent($category);
    }

    // Fallback to local consent check
    return $this->checkLocalConsent($category);
}
```

---

## Performance Considerations

### Async Processing

Page views and events are queued for async processing to avoid blocking the user:

```php
// Queue job for processing
class ProcessPageView implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public PageViewData $data
    ) {}

    public function handle(AnalyticsService $analytics): void
    {
        $analytics->processPageView($this->data);
    }
}
```

### Caching Strategy

```php
// Cache dashboard queries
public function getStats(DateRange $range): array
{
    $cacheKey = "analytics:stats:{$range->toKey()}";

    return Cache::remember($cacheKey, config('analytics.dashboard.cache_duration'), function () use ($range) {
        return [
            'pageviews' => $this->getPageViews($range),
            'visitors' => $this->getVisitors($range),
            'sessions' => $this->getSessions($range),
            'bounce_rate' => $this->getBounceRate($range),
            'avg_duration' => $this->getAverageSessionDuration($range),
        ];
    });
}
```

### Aggregation Strategy

Pre-aggregate data for common queries:

```php
// Daily aggregation job
class AggregateAnalytics implements ShouldQueue
{
    public function handle(): void
    {
        $yesterday = Carbon::yesterday();

        Aggregate::updateOrCreate(
            [
                'date' => $yesterday,
                'site_id' => $this->siteId,
            ],
            [
                'pageviews' => PageView::whereDate('created_at', $yesterday)->count(),
                'visitors' => Visitor::whereDate('last_seen_at', $yesterday)->count(),
                'sessions' => Session::whereDate('started_at', $yesterday)->count(),
                'bounce_rate' => $this->calculateBounceRate($yesterday),
                'avg_duration' => $this->calculateAvgDuration($yesterday),
            ]
        );
    }
}
```

---

## Security Considerations

1. **Rate Limiting** - Prevent abuse of tracking endpoints
2. **Input Validation** - Validate all incoming data strictly
3. **CSRF Protection** - Tracking endpoints exempt but validated by session
4. **Data Sanitization** - Escape all output, sanitize inputs
5. **Access Control** - Dashboard requires authentication
6. **Data Encryption** - Sensitive data encrypted at rest
7. **Audit Logging** - Log access to analytics data

---

## Related Documents

- [02-database-schema.md](./02-database-schema.md) - Complete database schema
- [03-local-analytics-engine.md](./03-local-analytics-engine.md) - JavaScript tracker details
- [04-provider-interface.md](./04-provider-interface.md) - External provider integration

---

*Last Updated: January 3, 2026*
