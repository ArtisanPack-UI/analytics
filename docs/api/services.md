---
title: Services
---

# Services

ArtisanPack UI Analytics provides several service classes for analytics operations.

## AnalyticsQuery

The main query service for retrieving analytics data.

### Usage

```php
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use ArtisanPackUI\Analytics\Data\DateRange;

$query = app(AnalyticsQuery::class);
$range = DateRange::last30Days();
```

### Methods

#### getStats()

Get comprehensive statistics:

```php
$stats = $query->getStats($range, $withCompare = true, $filters = []);

// Returns:
[
    'pageviews' => 1234,
    'visitors' => 567,
    'sessions' => 890,
    'bounce_rate' => 45.5,
    'avg_session_duration' => 180,
    'pages_per_session' => 2.3,
    'comparison' => [
        'pageviews' => ['value' => 1100, 'change' => 12.2],
        // ...
    ],
]
```

#### getPageViews()

Get page views over time:

```php
$pageViews = $query->getPageViews($range, $granularity = 'day', $filters = []);

// Returns Collection:
[
    ['date' => '2024-01-01', 'pageviews' => 100, 'visitors' => 80],
    ['date' => '2024-01-02', 'pageviews' => 150, 'visitors' => 90],
    // ...
]
```

#### getVisitors()

Get unique visitor count:

```php
$count = $query->getVisitors($range, $filters = []);
// Returns: int
```

#### getTopPages()

Get most viewed pages:

```php
$topPages = $query->getTopPages($range, $limit = 10, $filters = []);

// Returns Collection:
[
    ['path' => '/products', 'title' => 'Products', 'views' => 500, 'unique_views' => 350],
    // ...
]
```

#### getTrafficSources()

Get traffic source breakdown:

```php
$sources = $query->getTrafficSources($range, $limit = 10, $filters = []);

// Returns Collection:
[
    ['source' => 'google', 'medium' => 'organic', 'sessions' => 450, 'percentage' => 25.5],
    // ...
]
```

#### getDeviceBreakdown()

Get device type distribution:

```php
$devices = $query->getDeviceBreakdown($range, $filters = []);

// Returns Collection:
[
    ['device' => 'desktop', 'sessions' => 600, 'percentage' => 60],
    ['device' => 'mobile', 'sessions' => 350, 'percentage' => 35],
    ['device' => 'tablet', 'sessions' => 50, 'percentage' => 5],
]
```

#### getBrowserBreakdown()

Get browser distribution:

```php
$browsers = $query->getBrowserBreakdown($range, $limit = 10, $filters = []);
```

#### getCountryBreakdown()

Get geographic distribution:

```php
$countries = $query->getCountryBreakdown($range, $limit = 10, $filters = []);
```

#### getRealtime()

Get real-time visitor data:

```php
$realtime = $query->getRealtime($minutes = 5);

// Returns:
[
    'active_visitors' => 23,
    'active_pages' => [...],
    'sources' => [...],
    'devices' => [...],
]
```

#### excludeBots() / includeBots() / onlyBots()

> **Since 1.2.0**

Scope the next query's bot filtering. Queries exclude bots by default. Each modifier is fluent and applies to a single query. `withBots()` is an alias of `includeBots()`. See [Bot Filtering](Advanced-Bot-Filtering).

```php
// Default: human traffic only
$query->getVisitors($range);

// Include bot traffic
$query->includeBots()->getVisitors($range);

// Bot traffic only
$query->onlyBots()->getVisitors($range);

// Explicitly exclude bots (the default)
$query->excludeBots()->getVisitors($range);
```

#### getTopBotAgents()

> **Since 1.2.0**

Get the top bot user agents by visit count:

```php
$agents = $query->getTopBotAgents($range, $limit = 10, $filters = []);

// Returns Collection:
[
    ['user_agent' => 'GPTBot/1.0', 'visits' => 412],
    // ...
]
```

#### getBotStats()

> **Since 1.2.0**

Get a summary of filtered bot traffic:

```php
$stats = $query->getBotStats($range, $agentLimit = 10, $granularity = 'day', $filters = []);

// Returns:
[
    'bot_visits' => 1240,
    'total_visits' => 9810,
    'bot_percentage' => 12.6,
    'top_agents' => [['user_agent' => 'GPTBot/1.0', 'visits' => 412], ...],
    'trend' => [['date' => '2026-05-01', 'visits' => 40], ...],
]
```

---

## TrackingService

Handles page view and event tracking.

### Usage

```php
use ArtisanPackUI\Analytics\Services\TrackingService;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\EventData;

$service = app(TrackingService::class);
```

### Methods

#### trackPageView()

```php
$pageViewData = new PageViewData(
    path: '/products',
    title: 'Products',
);

$service->trackPageView($pageViewData);
```

#### trackEvent()

```php
$eventData = new EventData(
    name: 'purchase',
    properties: ['product_id' => 123],
    value: 99.99,
    category: 'ecommerce',
);

$service->trackEvent($eventData);
```

#### canTrack()

Check if tracking is allowed:

```php
if ($service->canTrack()) {
    // Proceed with tracking
}
```

---

## SessionManager

Manages visitor sessions.

### Usage

```php
use ArtisanPackUI\Analytics\Services\SessionManager;

$sessionManager = app(SessionManager::class);
```

### Methods

#### startSession()

```php
$session = $sessionManager->startSession($sessionData);
```

#### getSession()

```php
$session = $sessionManager->getSession($sessionId);
```

#### endSession()

```php
$sessionManager->endSession($sessionId);
```

#### extendSession()

```php
$sessionManager->extendSession($sessionId);
```

#### isSessionActive()

```php
$isActive = $sessionManager->isSessionActive($sessionId);
```

---

## ConsentService

Handles GDPR consent management.

### Usage

```php
use ArtisanPackUI\Analytics\Services\ConsentService;

$consentService = app(ConsentService::class);
```

### Methods

#### hasConsent()

Check if visitor has consent:

```php
$hasConsent = $consentService->hasConsent($fingerprint, 'analytics');
```

#### grantConsent()

Grant consent for categories:

```php
$consentService->grantConsent($fingerprint, ['analytics', 'marketing']);
```

#### revokeConsent()

Revoke consent:

```php
$consentService->revokeConsent($fingerprint, ['marketing']);
```

#### getConsentStatus()

Get full consent status:

```php
$status = $consentService->getConsentStatus($fingerprint);

// Returns:
[
    'analytics' => ['granted' => true, 'granted_at' => '2024-01-01 12:00:00'],
    'marketing' => ['granted' => false, 'revoked_at' => '2024-01-02 10:00:00'],
]
```

---

## TenantManager

Manages multi-tenant site resolution.

### Usage

```php
use ArtisanPackUI\Analytics\Services\TenantManager;

$tenantManager = app(TenantManager::class);
```

### Methods

#### currentSite()

Get the current site:

```php
$site = $tenantManager->currentSite();
```

#### currentTenantId()

Get current tenant ID:

```php
$tenantId = $tenantManager->currentTenantId();
```

#### setSite()

Set the current site:

```php
$tenantManager->setSite($site);
```

#### resolveSite()

Resolve site from request:

```php
$site = $tenantManager->resolveSite($request);
```

---

## GoalService

Manages conversion goals.

### Usage

```php
use ArtisanPackUI\Analytics\Services\GoalService;

$goalService = app(GoalService::class);
```

### Methods

#### checkGoals()

Check if a page view triggers any goals:

```php
$goalService->checkGoals($pageView);
```

#### recordConversion()

Record a goal conversion:

```php
$goalService->recordConversion($goal, $session, $value);
```

---

## DataExportService

Handles data export for GDPR compliance.

### Methods

#### exportVisitorData()

```php
$data = $exportService->exportVisitorData($visitorId);
```

---

## DataDeletionService

Handles data deletion for GDPR compliance.

### Methods

#### deleteVisitorData()

```php
$deletionService->deleteVisitorData($visitorId);
```

#### deleteOldData()

Delete data older than retention period:

```php
$deletionService->deleteOldData($days = 90);
```

---

## BotDetector

> **Since 1.2.0**

Implements the multi-signal confidence scoring system used to identify bot traffic. See [Bot Filtering](Advanced-Bot-Filtering) for the full feature guide.

### Usage

```php
use ArtisanPackUI\Analytics\Services\BotDetector;

$detector = app(BotDetector::class);
```

### Methods

#### score()

Calculate the bot confidence score (0–100) for a visitor:

```php
$score = $detector->score($visitor);
// Returns: int
```

#### isBot()

Determine whether a visitor should be considered a bot. Returns `false` when detection is disabled or the visitor is whitelisted; otherwise compares the score against the threshold:

```php
$isBot = $detector->isBot($visitor);
// Returns: bool
```

#### isWhitelisted()

Determine whether a visitor is whitelisted from scoring, checking both the config and database whitelists:

```php
$whitelisted = $detector->isWhitelisted($visitor);
// Returns: bool
```

#### threshold()

Get the configured confidence threshold (default `70`):

```php
$threshold = $detector->threshold();
// Returns: int
```

#### enabled()

Determine whether bot detection is enabled:

```php
$enabled = $detector->enabled();
// Returns: bool
```

Scoring is applied to recent visitors in the background by the `AnalyzeBotTraffic` job, which persists the result to the visitor's `is_bot`, `bot_score`, and `bot_detected_at` columns.
