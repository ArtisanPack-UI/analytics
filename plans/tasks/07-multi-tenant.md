Feature Name: Multi-Tenant SaaS Support
Requested By: Internal
Owned By: TBD

## What is the Feature

Implement multi-tenant architecture supporting both single-site deployments and SaaS applications with tenant isolation, per-tenant configuration, site management, cross-tenant reporting, and API authentication.

## Tasks

### Site Model & Migration

- [ ] Create `analytics_sites` migration
- [ ] Add UUID, name, domain fields
- [ ] Add polymorphic tenant relationship (tenant_type, tenant_id)
- [ ] Add settings JSON column
- [ ] Add timezone and currency fields
- [ ] Add tracking_enabled and public_dashboard flags
- [ ] Add api_key and api_key_last_used_at fields
- [ ] Add soft deletes
- [ ] Create `Site` Eloquent model
- [ ] Implement relationships (visitors, sessions, pageViews, events, goals, conversions, consents, aggregates)
- [ ] Implement API key management (generate, rotate, revoke, recordUsage)
- [ ] Implement settings helpers (getSetting, setSetting)
- [ ] Implement getTrackingScript() method
- [ ] Create model scopes (forDomain, forTenant, trackingEnabled, publicDashboard)

### Tenant Resolution

- [ ] Create `TenantResolverInterface` contract
- [ ] Implement `DomainResolver` (match by domain)
- [ ] Implement `SubdomainResolver` (extract subdomain from host)
- [ ] Implement `HeaderResolver` (X-Site-ID header)
- [ ] Implement `ApiKeyResolver` (Bearer token, X-API-Key, query param)
- [ ] Create `TenantManager` service
- [ ] Implement resolver registration with priority sorting
- [ ] Implement `resolve()` with fallback to default site
- [ ] Implement `current()` and `setCurrent()` methods
- [ ] Implement `forSite()` context switching
- [ ] Create `ResolveTenant` middleware
- [ ] Share site with views in middleware

### Data Isolation (BelongsToSite Trait)

- [ ] Create `BelongsToSite` trait
- [ ] Implement automatic global scope for site_id filtering
- [ ] Implement automatic site_id assignment on create
- [ ] Add `site()` BelongsTo relationship
- [ ] Create `scopeForSite()` for explicit site queries
- [ ] Create `withoutSiteScope()` for bypassing scope
- [ ] Create `allSites()` static method for admin queries
- [ ] Apply trait to all analytics models (PageView, Session, Visitor, Event, Goal, Conversion, Consent, Aggregate)

### Per-Tenant Configuration

- [ ] Define site settings schema (tracking, dashboard, privacy, features, providers)
- [ ] Create `SiteSettingsService` class
- [ ] Implement `get()` with defaults fallback
- [ ] Implement `set()` for current site
- [ ] Implement `all()` merged with defaults
- [ ] Implement `featureEnabled()` helper
- [ ] Add site_defaults to package configuration

### Site Selector Component

- [ ] Create `SiteSelector` Livewire component
- [ ] Implement site selection dropdown
- [ ] Dispatch `site-changed` event on selection
- [ ] Create `getAccessibleSitesProperty` computed
- [ ] Implement authorization check `canAccessSite()`
- [ ] Create site-selector Blade view
- [ ] Show site name and domain in dropdown
- [ ] Add visual indicator for selected site

### Multi-Tenant Dashboard

- [ ] Create `MultiTenantDashboard` Livewire component
- [ ] Listen for `site-changed` event
- [ ] Update TenantManager context on site change
- [ ] Conditionally show site selector based on multi-tenant mode
- [ ] Ensure all queries use site context

### Cross-Tenant Reporting

- [ ] Create `CrossTenantReporting` service
- [ ] Implement `getPlatformStats()` (total sites, visitors, sessions, page views)
- [ ] Implement `getTopSitesByTraffic()` with counts
- [ ] Implement `getSitesWithGrowth()` with period comparison
- [ ] Implement `getAggregatesBySite()` for specific metrics
- [ ] Implement `exportPlatformReport()`
- [ ] Create `PlatformDashboard` admin Livewire component

### API Authentication

- [ ] Create `ApiKeyGuard` implementing Guard interface
- [ ] Implement `user()` returning Site
- [ ] Implement `validate()` for credentials check
- [ ] Support Bearer token, X-API-Key header, query param
- [ ] Record API key usage on authentication
- [ ] Create `AuthenticateWithApiKey` middleware
- [ ] Set TenantManager context in middleware
- [ ] Return 401 JSON for invalid/missing key
- [ ] Register analytics-api guard in auth config

### API Routes

- [ ] Create tracking endpoints (POST /track/pageview, /track/event)
- [ ] Create query endpoints (GET /stats, /visitors, /pages, /events, /goals)
- [ ] Create site management endpoints (GET /site, PUT /site/settings)
- [ ] Apply AuthenticateWithApiKey middleware

### Configuration

- [ ] Add multi_tenant config section
- [ ] Add enabled toggle (ANALYTICS_MULTI_TENANT env)
- [ ] Add resolvers array configuration
- [ ] Add base_domain for subdomain resolution
- [ ] Add site_header for header-based resolution

## Accessibility Notes

- [ ] Site selector must be keyboard accessible
- [ ] Dropdown must support arrow key navigation
- [ ] Focus trap within dropdown when open
- [ ] ARIA labels for site selector button

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
