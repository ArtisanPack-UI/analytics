Feature Name: Multi-Tenant SaaS Support
Requested By: Internal
Owned By: TBD

## What is the Feature

Implement multi-tenant architecture supporting both single-site deployments and SaaS applications with tenant isolation, per-tenant configuration, site management, cross-tenant reporting, and API authentication.

## Tasks

### Site Model & Migration

- [x] Create `analytics_sites` migration
- [x] Add UUID, name, domain fields
- [x] Add polymorphic tenant relationship (tenant_type, tenant_id)
- [x] Add settings JSON column
- [x] Add timezone and currency fields
- [x] Add tracking_enabled and public_dashboard flags
- [x] Add api_key and api_key_last_used_at fields
- [x] Add soft deletes
- [x] Create `Site` Eloquent model
- [x] Implement relationships (visitors, sessions, pageViews, events, goals, conversions, consents, aggregates)
- [x] Implement API key management (generate, rotate, revoke, recordUsage)
- [x] Implement settings helpers (getSetting, setSetting)
- [x] Implement getTrackingScript() method
- [x] Create model scopes (forDomain, forTenant, trackingEnabled, publicDashboard)

### Tenant Resolution

- [x] Create `TenantResolverInterface` contract
- [x] Implement `DomainResolver` (match by domain)
- [x] Implement `SubdomainResolver` (extract subdomain from host)
- [x] Implement `HeaderResolver` (X-Site-ID header)
- [x] Implement `ApiKeyResolver` (Bearer token, X-API-Key, query param)
- [x] Create `TenantManager` service
- [x] Implement resolver registration with priority sorting
- [x] Implement `resolve()` with fallback to default site
- [x] Implement `current()` and `setCurrent()` methods
- [x] Implement `forSite()` context switching
- [x] Create `ResolveTenant` middleware
- [x] Share site with views in middleware

### Data Isolation (BelongsToSite Trait)

- [x] Create `BelongsToSite` trait
- [x] Implement automatic global scope for site_id filtering
- [x] Implement automatic site_id assignment on create
- [x] Add `site()` BelongsTo relationship
- [x] Create `scopeForSite()` for explicit site queries
- [x] Create `withoutSiteScope()` for bypassing scope
- [x] Create `allSites()` static method for admin queries
- [x] Apply trait to all analytics models (PageView, Session, Visitor, Event, Goal, Conversion, Consent, Aggregate)

### Per-Tenant Configuration

- [x] Define site settings schema (tracking, dashboard, privacy, features, providers)
- [x] Create `SiteSettingsService` class
- [x] Implement `get()` with defaults fallback
- [x] Implement `set()` for current site
- [x] Implement `all()` merged with defaults
- [x] Implement `featureEnabled()` helper
- [x] Add site_defaults to package configuration

### Site Selector Component

- [x] Create `SiteSelector` Livewire component
- [x] Implement site selection dropdown
- [x] Dispatch `site-changed` event on selection
- [x] Create `getAccessibleSitesProperty` computed
- [x] Implement authorization check `canAccessSite()`
- [x] Create site-selector Blade view
- [x] Show site name and domain in dropdown
- [x] Add visual indicator for selected site

### Multi-Tenant Dashboard

- [x] Create `MultiTenantDashboard` Livewire component
- [x] Listen for `site-changed` event
- [x] Update TenantManager context on site change
- [x] Conditionally show site selector based on multi-tenant mode
- [x] Ensure all queries use site context

### Cross-Tenant Reporting

- [x] Create `CrossTenantReporting` service
- [x] Implement `getPlatformStats()` (total sites, visitors, sessions, page views)
- [x] Implement `getTopSitesByTraffic()` with counts
- [x] Implement `getSitesWithGrowth()` with period comparison
- [x] Implement `getAggregatesBySite()` for specific metrics
- [x] Implement `exportPlatformReport()`
- [x] Create `PlatformDashboard` admin Livewire component

### API Authentication

- [x] Create `ApiKeyGuard` implementing Guard interface
- [x] Implement `user()` returning Site
- [x] Implement `validate()` for credentials check
- [x] Support Bearer token, X-API-Key header, query param
- [x] Record API key usage on authentication
- [x] Create `AuthenticateWithApiKey` middleware
- [x] Set TenantManager context in middleware
- [x] Return 401 JSON for invalid/missing key
- [x] Register analytics-api guard in auth config

### API Routes

- [x] Create tracking endpoints (POST /track/pageview, /track/event)
- [x] Create query endpoints (GET /stats, /visitors, /pages, /events, /goals)
- [x] Create site management endpoints (GET /site, PUT /site/settings)
- [x] Apply AuthenticateWithApiKey middleware

### Configuration

- [x] Add multi_tenant config section
- [x] Add enabled toggle (ANALYTICS_MULTI_TENANT env)
- [x] Add resolvers array configuration
- [x] Add base_domain for subdomain resolution
- [x] Add site_header for header-based resolution

## Accessibility Notes

- [x] Site selector must be keyboard accessible
- [x] Dropdown must support arrow key navigation
- [x] Focus trap within dropdown when open
- [x] ARIA labels for site selector button

## UX Notes

- Single-site mode should work without any configuration
- Site selector should only appear in multi-tenant mode
- Clear visual feedback when switching sites
- Loading indicator during site context change

## Testing Notes

- [ ] Test DomainResolver
- [ ] Test SubdomainResolver
- [ ] Test HeaderResolver
- [ ] Test ApiKeyResolver
- [ ] Test TenantManager resolution chain
- [ ] Test BelongsToSite global scope
- [ ] Test BelongsToSite automatic assignment
- [ ] Test forSite() explicit queries
- [ ] Test withoutSiteScope() bypass
- [ ] Test allSites() admin queries
- [ ] Test SiteSettingsService
- [ ] Test ApiKeyGuard authentication
- [ ] Test CrossTenantReporting queries
- [ ] Test API endpoints with authentication

## Documentation Notes

- [ ] Document single-site vs multi-tenant modes
- [ ] Document tenant resolution strategies
- [ ] Document site settings schema
- [ ] Document API authentication methods
- [ ] Document cross-tenant reporting
- [ ] Document site management commands

## Related Planning Documents

- [08-multi-tenant.md](../08-multi-tenant.md)
