# ArtisanPack UI Analytics

ArtisanPack UI Analytics is a privacy-first analytics package for Laravel applications. Built on Livewire 3, it provides local database storage for complete data ownership, GDPR-compliant consent management, real-time dashboards, multi-tenant support, and optional integration with Google Analytics 4 and Plausible.

## Quick Start

### Installation

```bash
# Install the package
composer require artisanpack-ui/analytics

# Run the installer (migrations, config, assets)
php artisan analytics:install

# Or manually publish and migrate
php artisan vendor:publish --provider="ArtisanPackUI\Analytics\AnalyticsServiceProvider"
php artisan migrate
```

### Basic Usage

```blade
<!-- Add tracking scripts to your layout -->
@analyticsScripts

<!-- Add consent banner (if consent required) -->
@analyticsConsentBanner

<!-- Embed the full dashboard -->
<livewire:artisanpack-analytics::analytics-dashboard />
```

```php
// Track page views programmatically
trackPageView('/products', 'Products Page');

// Track custom events
trackEvent('button_click', ['button_id' => 'signup']);

// Get visitor statistics
$stats = analyticsStats(DateRange::last30Days());
```

## Key Features

- **Privacy First**: Local database storage for complete data ownership
- **GDPR Compliant**: Built-in consent management with Do Not Track support
- **Real-Time Dashboard**: Beautiful Livewire dashboard with live visitor counts
- **Session Tracking**: Track visitors, sessions, page views, and bounce rates
- **Event Tracking**: Custom event tracking with categories and values
- **Goal Tracking**: Set up conversion goals with progress tracking
- **Multi-Tenant Support**: Domain, subdomain, API key, and header-based resolution
- **External Providers**: Optional integration with Google Analytics 4 and Plausible
- **Queue Processing**: High-performance async tracking for busy sites
- **Data Retention**: Configurable retention with automatic cleanup and aggregation
- **IP Anonymization**: GDPR-compliant IP address handling
- **Bot Filtering**: Automatic exclusion of known bots and crawlers

## AI features

The Analytics package ships four AI agents that plug into `artisanpack-ui/ai`. Every feature is toggleable via the `artisanpack.ai.features.{key}.enabled` config (or the AI package's runtime registry) — Livewire, React, and Vue triggers all render a "disabled" state when a feature is off.

| Feature key | Input | Output | Default model |
|-------------|-------|--------|---------------|
| `analytics.insight_summary` | `{ date_range, metrics, compare_to? }` | `{ summary, highlights[], concerns[] }` | `claude-sonnet-4-6` (streams) |
| `analytics.explain_anomaly` | `{ anomaly: {metric,direction,magnitude,date}, context: {recent_content_changes, referrer_deltas, campaign_launches} }` | `{ hypotheses:[{cause,confidence,evidence[]}], recommended_next_steps[] }` | `claude-sonnet-4-6` |
| `analytics.segment_insight` | `{ segment:{type,value}, metrics, baseline }` | `{ patterns:[{observation,significance,suggested_action}] }` | `claude-sonnet-4-6` |
| `analytics.digest_email` | `{ items[], focus?, length? }` (inherits `SummarizationAgent`) | `{ summary, key_points[], caveats[] }` | `claude-sonnet-4-6` |

### Livewire triggers

```blade
<livewire:artisanpack-analytics::ai.insight-summary
	:date-from="'2026-05-01'"
	:date-to="'2026-05-07'"
	:metrics="$weeklyMetrics"
/>

<livewire:artisanpack-analytics::ai.anomaly-explanation :anomaly="$anomaly" :context="$context" />
<livewire:artisanpack-analytics::ai.segment-insight ... />
<livewire:artisanpack-analytics::ai.digest-subscription />
```

### React / Vue triggers

Publish the components alongside the analytics dashboards (`analytics-react` / `analytics-vue` publish tags) and mount them anywhere in your Inertia app:

```tsx
import { InsightSummary } from '@/vendor/artisanpack-analytics/react/components/ai';

<InsightSummary
	api={ { baseUrl: '/api/analytics' } }
	dateRange={ { from: '2026-05-01', to: '2026-05-07' } }
	metrics={ weeklyMetrics }
/>
```

### Enabling / disabling features

Every feature is opt-in per-app via `config/artisanpack.php` (merged from the AI package):

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

Requests are gated by the `analytics.ai.use` gate. The ship default allows any authenticated user; override in your `AuthServiceProvider` for stricter policies.

### Digest email opt-in

The `analytics.digest_email` feature is delivered via a queued job (`SendDigestEmailJob`) and honors a per-user preference stored in `analytics_digest_preferences` (`off | weekly | monthly`). Users pick their cadence via the `DigestSubscription` component; you schedule `SendDigestEmailJob` on your cadence of choice (the job is a no-op for users whose preference is `off` or missing).

```php
use ArtisanPackUI\Analytics\Jobs\SendDigestEmailJob;

$schedule->call( function () {
	AnalyticsDigestPreference::where( 'cadence', 'weekly' )->each( function ( $preference ) {
		SendDigestEmailJob::dispatch(
			userId: $preference->user_id,
			email: $preference->user->email,
			items: $weeklyDigestItems( $preference->user ),
			periodLabel: 'Last 7 days',
		);
	} );
} )->weekly();
```

## Components

### Livewire Components

| Component | Description |
|-----------|-------------|
| `AnalyticsDashboard` | Full-featured analytics dashboard with all widgets |
| `StatsCards` | Summary statistics (page views, visitors, sessions, bounce rate) |
| `VisitorsChart` | Interactive line chart of visitors over time |
| `TopPages` | Most visited pages table with view counts |
| `TrafficSources` | Referrer breakdown with percentage charts |
| `RealtimeVisitors` | Live count of active visitors |
| `GoalProgress` | Goal completion tracking with progress bars |
| `ConsentBanner` | GDPR consent collection banner |

### Widget Usage

```blade
<!-- Individual widgets -->
<livewire:artisanpack-analytics::stats-cards />
<livewire:artisanpack-analytics::visitors-chart />
<livewire:artisanpack-analytics::top-pages />
<livewire:artisanpack-analytics::traffic-sources />
<livewire:artisanpack-analytics::realtime-visitors />

<!-- With date range -->
<livewire:artisanpack-analytics::stats-cards
    :start-date="$startDate"
    :end-date="$endDate"
/>

<!-- Multi-tenant filtering -->
<livewire:artisanpack-analytics::analytics-dashboard
    :site-id="$tenant->site_id"
/>
```

## Documentation

Comprehensive documentation is available at our [Documentation Site](https://github.com/ArtisanPack-UI/analytics/wiki):

- **[Getting Started](https://github.com/ArtisanPack-UI/analytics/wiki/Getting-Started)** - Quick start guide
- **[Installation Guide](https://github.com/ArtisanPack-UI/analytics/wiki/Installation)** - Detailed setup instructions
- **[Configuration](https://github.com/ArtisanPack-UI/analytics/wiki/Installation-Configuration)** - All configuration options
- **[Tracking Page Views](https://github.com/ArtisanPack-UI/analytics/wiki/Usage-Tracking-Page-Views)** - Page view tracking
- **[Tracking Events](https://github.com/ArtisanPack-UI/analytics/wiki/Usage-Tracking-Events)** - Custom event tracking
- **[Components](https://github.com/ArtisanPack-UI/analytics/wiki/Components)** - Dashboard and widget components
- **[Multi-Tenancy](https://github.com/ArtisanPack-UI/analytics/wiki/Advanced-Multi-Tenancy)** - Multi-tenant setup
- **[Privacy & Consent](https://github.com/ArtisanPack-UI/analytics/wiki/Advanced-Privacy-Consent)** - GDPR compliance
- **[API Reference](https://github.com/ArtisanPack-UI/analytics/wiki/Api)** - Models, services, and events

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=analytics-config
```

### Environment Variables

The package supports the following environment variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `ANALYTICS_ENABLED` | Enable/disable all tracking | `true` |
| `ANALYTICS_ACTIVE_PROVIDERS` | Comma-separated list of providers | `local` |
| `ANALYTICS_DB_CONNECTION` | Database connection for analytics | `null` (default) |
| `ANALYTICS_TABLE_PREFIX` | Prefix for analytics tables | `analytics_` |
| `ANALYTICS_ANONYMIZE_IP` | Anonymize visitor IP addresses | `true` |
| `ANALYTICS_QUEUE_PROCESSING` | Process tracking via queues | `true` |
| `ANALYTICS_QUEUE_NAME` | Queue name for async processing | `analytics` |
| `ANALYTICS_RETENTION_PERIOD` | Days to retain data (null = forever) | `90` |
| `ANALYTICS_DASHBOARD_PREFIX` | URL prefix for dashboard routes | `analytics` |
| `ANALYTICS_CACHE_DURATION` | Dashboard cache duration in seconds | `300` |
| `ANALYTICS_MULTI_TENANT` | Enable multi-tenant mode | `false` |
| `ANALYTICS_CONSENT_REQUIRED` | Require consent before tracking | `false` |
| `ANALYTICS_RESPECT_DNT` | Honor Do Not Track headers | `true` |
| `ANALYTICS_GOOGLE_ENABLED` | Enable Google Analytics 4 | `false` |
| `ANALYTICS_GOOGLE_MEASUREMENT_ID` | GA4 Measurement ID | `null` |
| `ANALYTICS_GOOGLE_API_SECRET` | GA4 API Secret | `null` |
| `ANALYTICS_PLAUSIBLE_ENABLED` | Enable Plausible Analytics | `false` |
| `ANALYTICS_PLAUSIBLE_DOMAIN` | Plausible domain | `null` |
| `ANALYTICS_PLAUSIBLE_API_URL` | Plausible API URL | `https://plausible.io/api` |

### Configuration Options

Key configuration options in `config/artisanpack/analytics.php`:

```php
return [
    // Enable/disable tracking
    'enabled' => env('ANALYTICS_ENABLED', true),

    // Active providers
    'active_providers' => ['local'], // Options: local, google, plausible

    // Local provider settings
    'local' => [
        'connection' => null,
        'table_prefix' => 'analytics_',
        'anonymize_ip' => true,
        'queue_processing' => true,
        'queue_name' => 'analytics',
    ],

    // Privacy settings
    'privacy' => [
        'consent_required' => false,
        'respect_dnt' => true,
        'cookie_name' => 'analytics_consent',
        'cookie_duration' => 365,
    ],

    // Data retention
    'retention' => [
        'period' => 90, // days, null = keep forever
        'aggregate_before_delete' => true,
    ],

    // Multi-tenant settings. Since 1.5.0, 'enabled' and the resolver list
    // live under artisanpack.core.multi_tenant, shared with every other
    // ArtisanPack UI package; the keys here are deprecated but still honoured.
    'multi_tenant' => [
        'enabled' => false,
    ],

    // Dashboard settings
    'dashboard' => [
        'prefix' => 'analytics',
        'middleware' => ['web', 'auth'],
        'cache_duration' => 300,
    ],
];
```

## Artisan Commands

```bash
# Install the package (migrations, config, assets)
php artisan analytics:install

# Create a new analytics site (multi-tenant)
php artisan analytics:create-site "Site Name" --domain=example.com

# List all configured sites
php artisan analytics:list-sites

# Regenerate API key for a site
php artisan analytics:regenerate-api-key {site_id}

# Clean up old data based on retention settings
php artisan analytics:cleanup

# Clean up data older than specific days
php artisan analytics:cleanup --days=30

# Preview cleanup without deleting
php artisan analytics:cleanup --dry-run

# Aggregate raw data into summary tables
php artisan analytics:aggregate

# Export analytics data
php artisan analytics:export --format=csv --output=analytics.csv

# Delete visitor data (GDPR compliance)
php artisan analytics:delete-visitor {visitor_id}

# Clear analytics cache
php artisan analytics:clear-cache

# Warm dashboard cache
php artisan analytics:warm-cache

# Display quick statistics
php artisan analytics:stats --period=month
```

### Scheduling Commands

Add to your scheduler for automated maintenance:

```php
// routes/console.php or bootstrap/app.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('analytics:cleanup')->daily();
Schedule::command('analytics:aggregate')->hourly();
Schedule::command('analytics:warm-cache')->hourly();
```

## Requirements

- Laravel 11 or 12: PHP 8.2+
- Laravel 13: PHP 8.3+
- Livewire 3.6.4+

## Dependencies

The package has minimal external dependencies:

- [doctrine/dbal](https://github.com/doctrine/dbal) - Database abstraction
- [nesbot/carbon](https://github.com/briannesbitt/Carbon) - Date handling
- [livewire/livewire](https://github.com/livewire/livewire) - Reactive components

Optional integrations with the ArtisanPack UI ecosystem:

- [artisanpack-ui/livewire-ui-components](https://github.com/ArtisanPack-UI/livewire-ui-components) - UI components for dashboard
- [artisanpack-ui/hooks](https://github.com/ArtisanPack-UI/hooks) - WordPress-style hooks for extensibility

## Events

The package dispatches events for key actions:

```php
use ArtisanPackUI\Analytics\Events\PageViewRecorded;
use ArtisanPackUI\Analytics\Events\EventTracked;
use ArtisanPackUI\Analytics\Events\SessionStarted;
use ArtisanPackUI\Analytics\Events\GoalCompleted;
use ArtisanPackUI\Analytics\Events\ConsentGiven;
use ArtisanPackUI\Analytics\Events\ConsentRevoked;

// Listen for page views
Event::listen(PageViewRecorded::class, function ($event) {
    // $event->pageView contains the page view model
});

// Listen for custom events
Event::listen(EventTracked::class, function ($event) {
    // $event->event contains the tracked event
});

// Listen for goal completions
Event::listen(GoalCompleted::class, function ($event) {
    // $event->goal contains the completed goal
    // $event->conversion contains the conversion record
});
```

## Extensibility

### Custom Analytics Providers

Create custom providers by implementing `AnalyticsProviderInterface`:

```php
use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\EventData;

class MixpanelProvider implements AnalyticsProviderInterface
{
    public function trackPageView(PageViewData $data): void
    {
        // Send to Mixpanel
    }

    public function trackEvent(EventData $data): void
    {
        // Send to Mixpanel
    }

    public function isEnabled(): bool
    {
        return config('services.mixpanel.enabled', false);
    }

    public function getName(): string
    {
        return 'mixpanel';
    }
}

// Register in a service provider
Analytics::extend('mixpanel', function ($app) {
    return new MixpanelProvider(config('services.mixpanel'));
});
```

### Custom Site Resolvers (Multi-Tenant)

Which site a request is for is decided once for the whole ArtisanPack UI
ecosystem, by `artisanpack-ui/core`. Implement its contract, which is keyed on
the site identifier so packages need not share models:

```php
use ArtisanPackUI\Core\Contracts\SiteResolver;
use Illuminate\Http\Request;

class TeamResolver implements SiteResolver
{
    public function __construct(private Request $request)
    {
    }

    public function currentSiteId(): int|string|null
    {
        $teamId = $this->request->user()?->current_team_id;

        return Site::where('team_id', $teamId)->value('id');
    }
}

// Register in config/artisanpack.php
'core' => [
    'multi_tenant' => [
        'enabled' => true,
        'resolvers' => [
            App\Analytics\TeamResolver::class,
        ],
    ],
],
```

See [Multi-Tenancy](docs/advanced/multi-tenancy.md) for migrating a resolver
written against the deprecated `SiteResolverInterface`.

### Filter Hooks

Extend functionality using filter hooks:

```php
use function addFilter;

// Modify page view data before recording
addFilter('analytics.pageview.data', function (array $data) {
    $data['custom_dimension'] = 'value';
    return $data;
});

// Add custom excluded paths
addFilter('analytics.excluded_paths', function (array $paths) {
    $paths[] = '/internal/*';
    return $paths;
});

// Modify dashboard query
addFilter('analytics.dashboard.query', function ($query) {
    return $query->where('is_bot', false);
});
```

## Contributing

Contributions are welcome! To contribute:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes (`git commit -m 'Add feature'`)
4. Push to the branch (`git push origin feature/your-feature`)
5. Open a Merge Request

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting merge requests.

## License

ArtisanPack UI Analytics is open-sourced software licensed under the [MIT license](LICENSE).
