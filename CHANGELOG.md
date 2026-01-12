# ArtisanPack UI Analytics Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
