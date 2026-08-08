# ArtisanPack UI Analytics Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **Site resolution now happens once for the whole ArtisanPack UI ecosystem, in `artisanpack-ui/core`.** `TenantManager` no longer owns a resolver chain: it reads `ArtisanPackUI\Core\MultiTenancy\SiteContext` and looks the `Site` model up from the identifier that contract hands back. Before this, every package that scoped data by site kept its own resolver shape and its own configuration — this package resolved a `Site` from a `Request`, `artisanpack-ui/bookings` resolved an `int` from a single configured class — so an application installing both configured tenancy twice, in shapes that could not share a source of truth, and one request could resolve to site 2 here while resolving to site 1 there, silently. A site pinned through `TenantManager` is now pinned for every package, and a site pinned by another package is seen here. ([#94](https://github.com/ArtisanPack-UI/analytics/issues/94))
- Resolvers are configured at `artisanpack.core.multi_tenant.resolvers` and asked in the order they are listed. `priority()` no longer orders anything: a chain assembled from several packages' resolvers cannot be ordered by a number only one of those packages knows about.
- `TenantManager::forSite()` accepts a bare site identifier as well as a `Site`, so a console command or job holding only an ID need not load the model to scope its work.
- `TenantManager` is bound `scoped` rather than `singleton`, matching the context it wraps, so a site pinned by one queue job cannot scope the next job in the same worker.
- The middleware, the `BelongsToSite` trait, and the `analyticsSite()` / `analyticsTenantId()` helpers all read the shared context. The trait's undocumented `artisanpack.analytics.multi_site.resolver` fallback is gone — it was a second resolver reachable only from that trait, able to disagree with the one every other query used.
- Requires `artisanpack-ui/core` `^1.3`, which owns the shared contract.
- **Resolvers now decide the site for every package, so their trust assumptions travel.** `HeaderResolver` believes whatever `X-Site-ID` the caller sends, and `ApiKeyResolver` with `allow_query_api_key` reads a credential from the query string; both previously only affected analytics. List them only where that is intended — authenticated ingest and API routes — and prefer resolvers keyed on something the caller cannot choose for routes serving site-scoped data. This applies to the deprecated analytics resolver list too, which the bridge carries onto the shared one.
- Applying the `analytics.site` middleware matters more than it did. Scoped queries in a request that never runs it re-run the resolver chain per query, because resolvers are asked afresh by design; the middleware resolves once and pins the result. The upside of the same change: a request without that middleware is now scoped correctly rather than silently unscoped, which is what 1.4 did when nothing had called `resolve()`.

### Fixed

- `analyticsSite()` and `analyticsTenantId()` called `TenantManager::currentSite()` and `currentTenantId()`, neither of which has ever existed, so both helpers raised `BadMethodCallException` whenever multi-tenancy was enabled.
- Site-scoped queries in a request that never ran the `analytics.site` middleware were not scoped at all: the global scope only applied when something had already called `TenantManager::resolve()`, so one site's dashboard could show another's rows. Scoping now follows the shared context whether or not the middleware ran.
- A site identifier from another package that is not an analytics site ID (a slug, say) leaves analytics queries unscoped and logs once, rather than falling through to `default_site_id` and filing the work under the wrong site.
- The site-scoping global scope no longer issues a `sites` lookup per query. `TenantManager` caches the loaded `Site` against the identifier it was loaded for, so the cache invalidates itself the moment the context's answer changes.
- `multi_tenant.default_site_id` no longer stands in for an explicitly requested "no site": `withoutSite()`, `allSites()`, and `setCurrent( null )` mean no scoping, and a report meant to run across every site can no longer be quietly confined to one.

### Deprecated

- `artisanpack.analytics.multi_tenant.enabled` and `.resolvers`, in favour of `artisanpack.core.multi_tenant.enabled` and `.resolvers`. Both are still honoured: while the analytics flag is on, it switches the shared flag on and its resolver list is prepended to the shared one at boot, so an upgrade keeps resolving the site it always did rather than silently pooling every site's visits into one dashboard. A notice is logged when this happens. Removed in 2.0.
- `Contracts\SiteResolverInterface`, which now extends `ArtisanPackUI\Core\Contracts\SiteResolver`, along with its `resolve( Request ): ?Site` and `priority()` methods. Extend the new `Resolvers\AbstractSiteResolver` to keep a request-shaped resolver working — it implements `currentSiteId()` in terms of `resolve()`. Removed in 2.0.
- `Contracts\TenantResolverInterface` and `artisanpack.analytics.multi_tenant.resolver`. Kept because a tenant is not always a site — the interface carries its own column name — but it is consulted only by the `TenantResolver` middleware, and only when nothing has put a site in the shared context, so it cannot decide what queries are scoped to. Where a tenant is a site, implement core's `SiteResolver`.
- `TenantManager::resolve( Request )`, now an alias for `current()` that ignores its argument. Resolvers read the request from the container themselves, because a resolver that requires one is unusable from the console commands and queue workers where per-site iteration happens.

### Removed

- `TenantManager::registerResolver()` and `registerResolversFromConfig()`. Registering a resolver only this package could see is what the shared context exists to prevent; list resolvers under `artisanpack.core.multi_tenant.resolvers` instead. `getResolvers()` remains and now reports the shared chain.

### Added

- **Anonymous mode: page views from visitors who have not granted consent.** Off by default; enable with `privacy.anonymous_mode` (`ANALYTICS_ANONYMOUS_MODE`) plus `anonymousMode: true` in the client config. Previously a visitor who ignored the consent banner produced nothing at all, so on a site where most visitors ignore it most traffic was invisible. Anonymous hits are written to a new `analytics_anonymous_page_views` table holding path, title, referring host, device class and timestamp — and no visitor ID, session ID, fingerprint, IP or user agent. The table has no column able to hold an identifier, so two rows cannot be correlated to one person even by mistake. In the browser nothing is written either: the visitor, session and fingerprint setup is never called, so no cookie or `localStorage` key is created before consent. Granting consent mid-visit upgrades to normal tracking; rows already recorded stay anonymous and are never back-filled. An explicit `DNT: 1` / `Sec-GPC: 1` opt-out still suppresses everything, anonymous mode included. ([#87](https://github.com/ArtisanPack-UI/analytics/issues/87))
- `AnonymousPageView` model, a `POST /api/analytics/anonymous/pageview` ingest endpoint, and inclusion of the new table in the retention cleanup job.
- **Anonymous traffic is now readable in the dashboard.** Anonymous mode collected pre-consent page views but nothing displayed them, so enabling the flag filled a table without moving a single reported number. The dashboards gain an **Include anonymous traffic** toggle and an **Anonymous** panel across the Livewire, React and Vue surfaces. Both appear only once anonymous rows exist: with the feature off, or on with nothing collected for the range, neither is offered, because an empty anonymous panel reads like a fault rather than an absence. Anonymous rows stay excluded by default, so identified-only figures are unchanged from before anonymous mode existed. ([#93](https://github.com/ArtisanPack-UI/analytics/issues/93))
- The split is labelled rather than left to be inferred. Only page views, top pages, referring hosts and the page-view time series can include anonymous rows; unique visitors, sessions, bounce rate, session duration, pages per session, active-now and traffic sources cannot, because each is derived from a visitor or session identifier that anonymous rows deliberately do not have. While the toggle is on, every metric in the second group says so on the card, and toggling updates a live region so a screen reader user is not left on stale figures. The device split reports anonymous views as their own column beside the identified session count rather than summing two different units.
- `AnalyticsQuery` gains an `anonymous` filter mode (`exclude` — the default — `include`, `only`), mirroring the existing bot filter, along with `includeAnonymous()` / `onlyAnonymous()` / `excludeAnonymous()` modifiers and `getAnonymousStats()`, `getReferringHosts()`, `hasAnonymousData()` and per-source anonymous queries. These live on the concrete service, not on `AnalyticsQueryInterface`, which is a published contract that cannot gain methods without breaking implementors.
- `GET /api/analytics/anonymous` returns the anonymous summary and `GET /api/analytics/referrers` returns referring hosts counted in page views; the existing query endpoints accept `?anonymous=include|only`. A mode arriving while anonymous mode is switched off collapses back to `exclude`.
- Combined figures for an older period will shrink as anonymous rows age out under the retention schedule, while the consented figures for the same period remain. Record the consented-only number where a stable historical figure matters.
- **`@analyticsScripts` now emits the client-side snippets of active providers.** Providers whose tracking half runs in the browser can expose it through the new `ProvidesTrackerScript` contract, and the directive renders it alongside the package's own tracker. Previously nothing in this package ever called a provider's `trackerScript()`, so such a provider could be registered, listed in `active_providers`, and report success while contributing nothing in either direction — its server-side track methods are no-ops by design, and its snippet never reached the page. Providers exposing a `trackerScript()` method without implementing the contract are also honoured, so `artisanpack-ui/analytics-google` 1.0 works without a matching release. A provider that throws while producing its snippet is logged and skipped rather than taking down the page. ([#88](https://github.com/ArtisanPack-UI/analytics/issues/88))
- `Analytics::trackerScripts()` returns the collected snippets for callers rendering their own markup.
- **SPA navigation tracking.** The JS tracker now detects History API navigation (`pushState`, `replaceState`, `popstate`) — the mechanism Inertia, React Router, Vue Router and `wire:navigate` all navigate through — and records a page view whenever the path or query string changes. Previously page views were bound to the window `load` event and `hashchange` only, so in any SPA the tracker recorded the initial document load and nothing else; hash routing is not how current routers work. Controlled by the new `trackHistoryChanges` option, default `true`. ([#86](https://github.com/ArtisanPack-UI/analytics/issues/86))

### Fixed

- **The `analytics_anonymous_page_views` migration could not run on MySQL.** It indexed `(site_id, path)` where `path` is `varchar(2048)` — 8192 bytes under utf8mb4, against a 3072-byte limit for an InnoDB index key — so `php artisan migrate` failed with "Specified key was too long", leaving the table created but incomplete and the migration unrecorded, which then reported "table already exists" on every retry. The index is now built over a 191-character prefix on MySQL and MariaDB, and over the whole column on drivers without the limit. Anyone who hit the failed migration should drop the empty `analytics_anonymous_page_views` table and re-run `migrate`. The bug reached a release candidate because the test suite runs on SQLite, which has no key length limit; migrations are now also compiled against the MySQL grammar in CI so index keys over the limit fail a test rather than a deployment. ([#93](https://github.com/ArtisanPack-UI/analytics/issues/93))
- Engagement metrics are now attributed to the page they were measured on and reset per navigation. `_sendEngagementData()` read `window.location.pathname` at flush time, which in an SPA is the *incoming* path — and the server matches the row to update by path, so the update landed on the wrong page view or none at all. Scroll depth, time on page, engaged time and scroll milestones also accumulated across the whole session rather than per page, so scroll depth could only ratchet upward and milestone events stopped firing after the first page.
- Batched beacons are no longer silently discarded. `PrivacyFilter` resolved the excluded path with `$request->input( 'path', $request->path() )`; a batch payload carries its paths in `items[].data.path` and has no top-level `path`, so the check fell through to the ingest route's own URI (`api/analytics/batch`). That matches the `/api/*` pattern shipped in the default `excluded_paths`, so every batch was rejected with a 204 before reaching the controller — no error, no log, and the same status a successful beacon returns. Since the JS tracker batches whenever two or more items are queued within `batchInterval`, this dropped a large share of real traffic while single-item flushes kept working, which made it present as under-counting rather than as a broken endpoint. ([#85](https://github.com/ArtisanPack-UI/analytics/issues/85))

### Changed

- Excluded-path filtering moved from `PrivacyFilter` to `TrackingService`, so the decision is made once per tracked page view or event rather than once per HTTP request. A batch containing an excluded path now drops only that item instead of discarding the items alongside it. `PrivacyFilter` keeps the request-level checks it can actually evaluate (enabled flag, DNT/GPC, IP, user agent).
- Exclusion matching now compares the path component only, so a tracked path arriving with a query string or fragment is matched on its path. A `null` or empty tracked path is treated as not excluded rather than being dropped.
- **SPA page-view counts will rise.** `trackHistoryChanges` defaults to `true`, so applications that were silently recording only the initial document load will start recording every navigation. That is the intent, but expect a step change in reported page views rather than a regression. Applications that already bridge their router's navigation events by hand must set `trackHistoryChanges: false` or they will count each page view twice.

### Deprecated

- `PrivacyFilter::isExcludedPath()` and `PrivacyFilter::pathMatches()` are deprecated in favour of `TrackingService::isExcludedPath()`. Both are retained and still called by the middleware, so subclasses that call or override them keep working; they are scheduled for removal in 2.0. `isExcludedPath()` no longer falls back to `$request->path()` — it returns `false` when the request carries no single top-level tracked path, deferring to the per-item filtering in `TrackingService`. One consequence worth knowing if you override it: the override governs only requests carrying a single tracked path, since batched items are filtered by `TrackingService` and are not routed through the middleware hook.

## [1.4.0] - 2026-07-21

### Changed

- Renamed hook `ap.analytics.site_selector.query` → `ap.analytics.siteSelector.query` to align with cross-package hooks convention. Old name registered as a deprecation alias. Alias removal deferred to next major.
- Bumped `artisanpack-ui/hooks` to `^1.3`.

### Notes

- `PrivacyIntegration` still subscribes to legacy `privacy.*` hook names. Those will be updated to `ap.privacy.*` after the privacy package's Wave 1 rename ships; aliases in that package keep the current subscriptions working in the meantime.

## [1.3.0] - 2026-07-07

### Added

#### AI-Powered Analytics Agents

Four opt-in AI features that plug into `artisanpack-ui/ai` to turn raw analytics into narrative insights. Every feature ships an Agent, Livewire component, React and Vue components, and API endpoint, and honors the shared `FeatureRegistry` toggle.

- **Insight summary agent** (`analytics.insight_summary`): Streaming plain-language summary of what changed over a date range, returning `{summary, highlights[], concerns[]}`. Backed by `InsightSummaryAgent`.
- **Anomaly explanation agent** (`analytics.explain_anomaly`): Generates ranked hypotheses for a traffic spike or drop with confidence scores, evidence, and recommended next steps. Backed by `AnomalyExplanationAgent`.
- **Segment insight agent** (`analytics.segment_insight`): Surfaces non-obvious patterns in a referrer, page, or time segment relative to a baseline, returning observations with significance and suggested actions. Backed by `SegmentInsightAgent`.
- **Digest email agent** (`analytics.digest_email`): Composes opt-in weekly or monthly narrative digest emails. Extends the shared `SummarizationAgent` and ships with `SendDigestEmailJob`, `DigestEmailMailable`, a text-focused email template, an `AnalyticsDigestPreference` model, and per-user cadence preferences (`off | weekly | monthly`).
- **Livewire triggers**: `<livewire:artisanpack-analytics::ai.insight-summary />`, `ai.anomaly-explanation`, `ai.segment-insight`, and `ai.digest-subscription` components with disabled states when the corresponding feature is toggled off.
- **React and Vue components**: `InsightSummary`, `AnomalyExplanation`, `SegmentInsight`, and `DigestSubscription` shipped for both frameworks, together with `useAiAgent` and `useApi` hooks/composables.
- **API endpoints**: New routes under `/api/analytics/ai/*` for each agent, gated behind the `analytics.ai.use` ability. Ships with a permissive default gate (any authenticated user); override in your `AuthServiceProvider` for stricter policies.
- **Digest dispatch command**: `analytics:digests:dispatch` artisan command queues `SendDigestEmailJob` for every subscribed user; wire it into the scheduler on your preferred cadence.
- **`aiFeatures()` registration**: `AnalyticsServiceProvider::aiFeatures()` registers all four features with the AI package's feature registry so admin UIs can list them.
- **Migration**: `analytics_digest_preferences` table for per-user digest cadence.
- **Documentation**: New AI features guide covering feature keys, Livewire and React/Vue usage, gate customization, and digest scheduling.



### Added

- Laravel 13 support — widened `illuminate/*` constraints to `^11.0|^12.0|^13.0` and `orchestra/testbench` to `^9.0|^10.0|^11.0`. No PHP-floor change for users staying on Laravel 11 or 12.

## [1.2.0] - 2026-05-20

### Added

#### AI Bot Traffic Filtering

A multi-layered system that keeps automated traffic — AI crawlers, SEO tools, scrapers, and headless browsers — out of reported analytics. Bot-flagged data is stored but excluded from dashboard and query results by default. Enabled by default.

- **Expanded bot pattern list**: `DeviceDetector` now matches 60+ known bots, including AI crawlers (GPTBot, ClaudeBot, CCBot, PerplexityBot), regional crawlers (ByteSpider, Baidu, Sogou, Yandex), SEO tools (SEMrush, Ahrefs, Majestic), and scraper/automation tooling
- **BotDetector service**: Multi-signal confidence scoring (0–100) with a configurable threshold (default 70), combining user agent, engagement, request-pattern, and JavaScript fingerprint signals
- **Bot detection columns**: `is_bot`, `bot_score`, and `bot_detected_at` added to the `analytics_visitors` table
- **JS fingerprint signals**: The tracker payload now collects privacy-preserving WebDriver, headless, and missing-API signals
- **AnalyzeBotTraffic job**: Post-hoc behavioral analysis that scores recent visitors on a configurable schedule
- **Bot-aware query scoping**: `excludeBots()`, `includeBots()`/`withBots()`, and `onlyBots()` modifiers on `AnalyticsQuery`, plus `getTopBotAgents()` and `getBotStats()`, with a dashboard toggle to view bot traffic
- **Bot Traffic widget**: New dashboard widget for Livewire, React, and Vue showing filtered bot traffic, the bot share of total traffic, top bot agents, and a bot-only trend
- **Artisan commands**: `analytics:bots` to list flagged visitors (with CSV export) and `analytics:whitelist` to manage runtime whitelist entries
- **Runtime whitelist**: `BotWhitelistEntry` model and `analytics_bot_whitelist` table supplementing the static config whitelist
- **Configuration**: New `bot_detection` config section for the threshold, whitelist, per-signal toggles, and analysis schedule
- **Documentation**: New Bot Filtering guide and Bot Traffic widget reference, plus updated configuration, artisan command, and API reference docs

### Security

- Updated vulnerable transitive development dependencies to patched versions, clearing all reported advisories (10 advisories across 6 packages, including `symfony/yaml` CVE-2026-45133)

## [1.1.1] - 2026-05-20

### Fixed

- **MySQL migration foreign key constraint name collision**: Gave the analytics foreign keys explicit names so migrations no longer fail on MySQL with duplicate auto-generated constraint names.

## [1.1.0] - 2026-04-10

### Added

#### Inertia.js Dashboard Support

- **InertiaDashboardController**: New controller that returns Inertia responses with analytics data as typed page props for React/Vue dashboards
- **Inertia routes**: Dedicated routes for dashboard, pages, traffic, audience, events, and realtime views when `dashboard_driver` is set to `'inertia'`
- **Dashboard driver config**: New `dashboard_driver` option (`'livewire'` or `'inertia'`) to select dashboard rendering strategy

#### React Dashboard Components

- **AnalyticsDashboard**: Main dashboard page component
- **PageAnalytics**: Per-page analytics detail view
- **MultiTenantDashboard**: Multi-site management dashboard
- **StatsCards**: Key metrics card grid widget
- **VisitorsChart**: Line chart of visitors/pageviews over time
- **TopPages**: Most viewed pages table
- **TrafficSources**: Traffic source breakdown
- **RealtimeVisitors**: Live visitor count with polling
- **SiteSelector**: Site picker for multi-tenant setups
- **useAnalyticsApi hook**: Generic data fetching with polling and AbortController support

#### Vue Dashboard Components

- **AnalyticsDashboard, PageAnalytics, MultiTenantDashboard**: Vue 3 SFC equivalents of React page components
- **StatsCards, VisitorsChart, TopPages, TrafficSources, RealtimeVisitors, SiteSelector**: Vue 3 SFC equivalents of React widget components
- **useAnalyticsApi composable**: Vue 3 composable equivalent of the React hook

#### Consent Management Components (React & Vue)

- **ConsentBanner**: Cookie consent bar with accept/reject/customize actions
- **ConsentPreferences**: Category-level consent toggles panel
- **ConsentStatus**: Compact consent status indicator with manage button
- **useConsent hook/composable**: Consent state management with localStorage persistence, cookie sync, and server API synchronization

#### TypeScript Type Definitions

- Shared type definitions for API responses, data models, enums, query params, and component props
- Types cover: `StatsData`, `TopPageItem`, `TrafficSourceItem`, breakdown types, realtime types, consent types, and all Eloquent model interfaces

#### API Resources

- **StatsResource**: Overall analytics statistics
- **PageViewTimeSeriesResource**: Time series chart data
- **TopPageResource**: Most viewed pages
- **TrafficSourceResource**: Traffic source breakdown
- **BrowserBreakdownResource**: Browser usage breakdown
- **CountryBreakdownResource**: Geographic breakdown
- **DeviceBreakdownResource**: Device type breakdown
- **EventBreakdownResource**: Custom event breakdown

#### Frontend Installation Command

- **analytics:install-frontend**: New Artisan command to publish React or Vue components and add npm dependencies (`--stack=react|vue`, `--force`)

#### Documentation

- New `docs/frontend/` section with 7 pages covering overview, installation, React/Vue components, consent components, hooks/composables, and TypeScript types
- New `docs/api/resources.md` documenting all API resources
- Updated index pages, artisan commands docs, and mkdocs.yml navigation

### Changed

- **Livewire 4 support**: Updated `registerLivewireComponents()` to use `addNamespace` API for Livewire 4, with fallback to individual `component()` calls for Livewire 3
- **Route registration**: `registerRoutes()` now delegates dashboard routing to `registerDashboardRoutes()` based on the configured driver
- **Service provider**: Added `publishReactComponents()`, `publishVueComponents()`, and `registerInertiaSharedData()` methods
- **CI workflows**: Added `release/*` branches to CI workflow triggers so PRs targeting release branches run lint and tests
- **GitHub Actions**: Separated release workflow into dedicated `release.yml` with Packagist update step; added Livewire 3.6/4.0 test matrix

### Infrastructure

- Migrated from GitLab to GitHub
- Added GitHub Actions CI workflow, issue templates, and PR templates
- Updated PHP version requirement to 8.4

---

## [1.0.0] - 2026-01-23

### First Stable Release

This is the first stable release of ArtisanPack UI Analytics.

### Changes from Beta

- No functional changes from 1.0.0-beta1
- Production ready release

---

## [1.0.0-beta1] - 2026-01-12

### First Beta Release

This is the first beta release of ArtisanPack UI Analytics, a privacy-first analytics package for Laravel applications built on Livewire 3.

### Highlights

- **Privacy First**: Local database storage for complete data ownership
- **GDPR Compliant**: Built-in consent management with Do Not Track support
- **Real-Time Dashboard**: Beautiful Livewire dashboard with live visitor counts
- **Multi-Tenant Support**: Domain, subdomain, API key, and header-based resolution
- **External Providers**: Optional integration with Google Analytics 4 and Plausible
- **Laravel 11 and 12 Support**: Compatible with current Laravel versions
- **Livewire 3.6+ Compatible**: Built for the latest Livewire features

### Added

#### Core Features

- **Analytics Dashboard Component**: Full-featured Livewire dashboard with all widgets
- **Page View Tracking**: Track visitors, sessions, and page views with device/browser detection
- **Event Tracking**: Custom event tracking with categories, actions, and values
- **Session Management**: Track session duration, page depth, and bounce rates
- **Visitor Resolution**: Fingerprint-based visitor identification with IP anonymization

#### Livewire Components

- **AnalyticsDashboard**: Full-featured analytics dashboard with all widgets
- **StatsCards**: Summary statistics (page views, visitors, sessions, bounce rate)
- **VisitorsChart**: Interactive line chart of visitors over time
- **TopPages**: Most visited pages table with view counts
- **TrafficSources**: Referrer breakdown with percentage charts
- **RealtimeVisitors**: Live count of active visitors
- **GoalProgress**: Goal completion tracking with progress bars
- **ConsentBanner**: GDPR consent collection banner
- **SiteSelector**: Multi-tenant site switcher
- **PageAnalytics**: Per-page analytics view

#### Goal Tracking

- **Goal Creation**: Define conversion goals with URL patterns, events, or page views
- **Conversion Tracking**: Automatic goal matching and conversion recording
- **Goal Progress**: Track completion rates and target progress
- **Funnel Analysis**: Analyze user paths through conversion funnels

#### Multi-Tenant Support

- **Site Model**: Multi-site support with domain configuration
- **Domain Resolver**: Match sites by full domain
- **Subdomain Resolver**: Match sites by subdomain prefix
- **API Key Resolver**: Match sites by API key authentication
- **Header Resolver**: Match sites by custom HTTP headers
- **Cross-Tenant Reporting**: Aggregate data across multiple sites

#### Privacy & Compliance

- **Consent Management**: Configurable consent requirements before tracking
- **Do Not Track Support**: Honor browser DNT headers
- **IP Anonymization**: GDPR-compliant IP address handling
- **Data Deletion Service**: Remove visitor data on request
- **Cookie Configuration**: Customizable consent cookie settings

#### External Providers

- **Local Provider**: Built-in database storage provider
- **Google Analytics 4 Provider**: Send data to GA4 via Measurement Protocol
- **Plausible Provider**: Send data to Plausible Analytics
- **Provider Interface**: Create custom providers for any analytics service

#### Artisan Commands

- **analytics:install**: Install the package with migrations, config, and assets
- **analytics:create-site**: Create a new analytics site for multi-tenant setups
- **analytics:list-sites**: List all configured analytics sites
- **analytics:regenerate-api-key**: Regenerate API key for a site
- **analytics:cleanup**: Clean up old data based on retention settings
- **analytics:stats**: Display quick statistics from the command line
- **analytics:realtime**: Show real-time visitor count
- **analytics:goals-list**: List all configured goals
- **analytics:clear-cache**: Clear analytics cache

#### Data Management

- **Data Retention**: Configurable retention period with automatic cleanup
- **Data Aggregation**: Aggregate raw data into summary tables
- **Data Export Service**: Export analytics data to various formats
- **Queue Processing**: High-performance async tracking for busy sites

#### Events

- **PageViewRecorded**: Dispatched when a page view is recorded
- **EventTracked**: Dispatched when a custom event is tracked
- **SessionStarted**: Dispatched when a new session begins
- **GoalConverted**: Dispatched when a goal is completed
- **ConsentGiven**: Dispatched when user grants consent
- **ConsentRevoked**: Dispatched when user revokes consent

#### Services

- **TrackingService**: Record page views and events
- **SessionManager**: Manage visitor sessions
- **VisitorResolver**: Resolve and track unique visitors
- **DeviceDetector**: Detect device type, browser, and OS
- **IpAnonymizer**: Anonymize IP addresses for privacy
- **GoalService**: Manage and evaluate goals
- **GoalMatcher**: Match events and page views to goals
- **ConsentService**: Manage user consent
- **FunnelAnalyzer**: Analyze conversion funnels
- **EventProcessor**: Process and store events
- **TenantManager**: Manage multi-tenant site resolution
- **DataDeletionService**: Handle GDPR data deletion requests
- **DataExportService**: Export analytics data
- **CrossTenantReporting**: Generate cross-site reports

#### API & Controllers

- **TrackerController**: Handle tracking API requests
- **ConsentController**: Handle consent API requests
- **SiteApiController**: Site management API for multi-tenant
- **AnalyticsQueryController**: Query API for dashboard data
- **API Key Authentication**: Guard for API key-based access

#### Middleware

- **PrivacyFilter**: Apply privacy settings and consent checks
- **TenantResolver**: Resolve current site in multi-tenant mode
- **AnalyticsThrottle**: Rate limiting for tracking endpoints
- **AuthenticateWithApiKey**: API key authentication

#### Extensibility

- **Filter Hooks**: Modify tracking data before recording
- **Custom Providers**: Add new analytics provider integrations
- **Custom Resolvers**: Create custom site resolution strategies
- **Publishable Views**: Customize all Blade views
- **Publishable Config**: Full configuration customization

### Infrastructure

- **Comprehensive Test Suite**: Full test coverage with Pest PHP
- **Code Style**: PHP-CS-Fixer and PHPCS with ArtisanPackUI standards
- **Static Analysis**: PHPStan analysis
- **GitLab CI/CD**: Multi-PHP version testing (8.2, 8.3, 8.4)
- **Code Coverage**: Coverage reporting with Cobertura format
- **Security Scanning**: SAST, Secret Detection, Dependency Scanning
- **WordPress-Style Documentation**: Full PHPDoc blocks on all classes and methods
