Feature Name: API, Facades, Helpers & Commands
Requested By: Internal
Owned By: TBD

## What is the Feature

Implement all public APIs including Facades, helper functions, Blade directives, Artisan commands, event hooks integration, JavaScript API, and REST API endpoints.

## Tasks

### Analytics Facade

- [ ] Create `Analytics` facade
- [ ] Implement `trackPageView()` method
- [ ] Implement `trackEvent()` method
- [ ] Implement `trackFormSubmission()` method
- [ ] Implement `trackPurchase()` method
- [ ] Implement `trackConversion()` method
- [ ] Implement `getStats()` method
- [ ] Implement `getPageViews()` method
- [ ] Implement `getTopPages()` method
- [ ] Implement `getTrafficSources()` method
- [ ] Implement `getVisitors()` method
- [ ] Implement `getEvents()` method
- [ ] Implement `getConversions()` method
- [ ] Implement `getRealtime()` method
- [ ] Implement `isEnabled()`, `enable()`, `disable()` methods
- [ ] Implement `getProvider()`, `setProvider()` methods
- [ ] Implement `hasConsent()` method

### AnalyticsQuery Facade

- [ ] Create `AnalyticsQuery` facade
- [ ] Implement fluent `stats()` builder
- [ ] Implement fluent `pageViews()` builder with filtering
- [ ] Implement fluent `visitors()` builder with segments
- [ ] Implement fluent `events()` builder
- [ ] Implement fluent `sessions()` builder
- [ ] Implement fluent `conversions()` builder with funnel
- [ ] Implement fluent `aggregate()` builder
- [ ] Implement `compare()` for period comparison
- [ ] Implement `toQuery()` for raw query access
- [ ] Implement `raw()` for custom SQL

### Goal Facade

- [ ] Create `Goal` facade
- [ ] Implement `create()` method
- [ ] Implement `update()` method
- [ ] Implement `delete()` method
- [ ] Implement `all()` method
- [ ] Implement `find()` method
- [ ] Implement `matches()` for event matching
- [ ] Implement `convert()` for manual conversions

### Consent Facade

- [ ] Create `Consent` facade
- [ ] Implement `isRequired()` method
- [ ] Implement `hasConsent()` method (with optional type)
- [ ] Implement `grant()` method
- [ ] Implement `revoke()` and `revokeAll()` methods
- [ ] Implement `getConsent()` method
- [ ] Implement `checkFromRequest()` method
- [ ] Implement `setCookie()` and `clearCookie()` methods

### Helper Functions

- [ ] Create tracking helpers (analyticsTrackPageView, analyticsTrackEvent, analyticsTrackForm, analyticsTrackPurchase, analyticsTrackConversion)
- [ ] Create query helpers (analyticsStats, analyticsPageViews, analyticsVisitors, analyticsTopPages, analyticsTrafficSources)
- [ ] Create utility helpers (analyticsEnabled, analyticsSite)
- [ ] Create consent helpers (analyticsHasConsent, analyticsGrantConsent, analyticsRevokeConsent)
- [ ] Register helpers in service provider

### Blade Directives

- [ ] Create `@analyticsScripts` directive
- [ ] Create `@analyticsScripts` with custom config support
- [ ] Create `@analyticsConsentBanner` directive
- [ ] Create `@analyticsConsent` / `@endanalyticsConsent` conditional
- [ ] Create `@analyticsConsent('type')` with specific type
- [ ] Create `@analyticsPageView` directive
- [ ] Create `@analyticsEvent` directive
- [ ] Create `@analyticsDashboard` directive
- [ ] Create `@analyticsWidget` directive
- [ ] Create `@analyticsPageStats` directive

### Artisan Commands - Installation

- [ ] Create `analytics:install` command
- [ ] Create config publishing command
- [ ] Create views publishing command
- [ ] Create assets publishing command

### Artisan Commands - Data Management

- [ ] Create `analytics:aggregate` command
- [ ] Add --from and --to date range options
- [ ] Add --metrics filter option
- [ ] Create `analytics:cleanup` command
- [ ] Add --days custom retention option
- [ ] Add --force flag for no confirmation
- [ ] Create `analytics:export` command
- [ ] Add --period, --format, --output options
- [ ] Add --site option for multi-tenant

### Artisan Commands - Site Management

- [ ] Create `analytics:sites` list command
- [ ] Create `analytics:site:create` command
- [ ] Create `analytics:site:api-key` command with --rotate
- [ ] Create `analytics:site:delete` command
- [ ] Create `analytics:site:show` command

### Artisan Commands - Goal Management

- [ ] Create `analytics:goals` list command
- [ ] Create `analytics:goal:create` interactive command
- [ ] Add --type, --event, --category, --value options
- [ ] Create `analytics:goal:delete` command
- [ ] Create `analytics:goal:stats` command

### Artisan Commands - Reporting & Debugging

- [ ] Create `analytics:report` command with --email
- [ ] Create `analytics:report:platform` for multi-tenant
- [ ] Create `analytics:realtime` command
- [ ] Create `analytics:stats` command
- [ ] Create `analytics:test` command
- [ ] Add --provider option for provider testing
- [ ] Create `analytics:cache:clear` command
- [ ] Create `analytics:queue:status` command
- [ ] Create `analytics:queue:process` command

### Event Hooks Integration

- [ ] Implement `ap.analytics.before_track` filter
- [ ] Implement `ap.analytics.after_track` action
- [ ] Implement `ap.analytics.page_view_data` filter
- [ ] Implement `ap.analytics.event_data` filter
- [ ] Implement `ap.analytics.visitor_fingerprint` filter
- [ ] Implement `ap.analytics.before_query` filter
- [ ] Implement `ap.analytics.after_query` filter
- [ ] Implement `ap.analytics.dashboard_stats` filter
- [ ] Implement `ap.analytics.consent_changed` action
- [ ] Implement `ap.analytics.consent_banner` filter
- [ ] Implement `ap.analytics.before_goal_match` filter
- [ ] Implement `ap.analytics.goal_converted` action
- [ ] Implement `ap.analytics.conversion_value` filter
- [ ] Implement `ap.analytics.register_providers` filter
- [ ] Implement `ap.analytics.provider_data` filter
- [ ] Implement `ap.analytics.site_changed` action
- [ ] Implement `ap.analytics.site_resolvers` filter
- [ ] Implement `ap.analytics.site_created` action

### JavaScript API

- [ ] Create global `APAnalytics` object
- [ ] Implement `trackPageView()` method
- [ ] Implement `trackEvent()` method
- [ ] Implement `trackForm()` method
- [ ] Implement `trackPurchase()` method
- [ ] Implement `trackConversion()` method
- [ ] Implement `hasConsent()` method
- [ ] Implement `setConsent()` method
- [ ] Implement `revokeConsent()` method
- [ ] Implement `on()` event listener (consentChanged, beforeTrack, afterTrack, error)
- [ ] Implement `configure()` method
- [ ] Implement `enable()` / `disable()` methods
- [ ] Implement `isEnabled()` method

### REST API Endpoints

- [ ] Create tracking endpoint POST /track/pageview
- [ ] Create tracking endpoint POST /track/event
- [ ] Create query endpoint GET /stats
- [ ] Create query endpoint GET /visitors
- [ ] Create query endpoint GET /pages
- [ ] Create query endpoint GET /events
- [ ] Create query endpoint GET /goals
- [ ] Create site endpoint GET /site
- [ ] Create site endpoint PUT /site/settings
- [ ] Add rate limiting to API endpoints
- [ ] Return rate limit headers (X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset)

### Configuration

- [ ] Document all configuration options
- [ ] Create comprehensive config/analytics.php
- [ ] Document all environment variables
- [ ] Add hooks configuration section

## Accessibility Notes

N/A - API layer only

## UX Notes

- Facades should mirror Laravel conventions
- Helper function names should be intuitive
- Blade directives should be simple to use
- Artisan commands should have helpful descriptions

## Testing Notes

- [ ] Test Analytics facade tracking methods
- [ ] Test Analytics facade query methods
- [ ] Test AnalyticsQuery fluent builder
- [ ] Test Goal facade CRUD
- [ ] Test Consent facade methods
- [ ] Test all helper functions
- [ ] Test Blade directives rendering
- [ ] Test Artisan commands execution
- [ ] Test event hooks firing
- [ ] Test REST API endpoints
- [ ] Test API rate limiting

## Documentation Notes

- [ ] Document all facades with examples
- [ ] Document all helper functions
- [ ] Document all Blade directives
- [ ] Document configuration options
- [ ] Document Artisan commands
- [ ] Document event hooks
- [ ] Document JavaScript API
- [ ] Document REST API with examples

## Related Planning Documents

- [09-api-reference.md](../09-api-reference.md)
