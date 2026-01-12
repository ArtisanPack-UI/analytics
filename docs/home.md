---
title: ArtisanPack UI Analytics Documentation Home
---

# ArtisanPack UI Analytics Documentation

Welcome to the ArtisanPack UI Analytics documentation. This package provides comprehensive, privacy-first analytics tracking for Laravel applications with multi-provider support, real-time dashboards, and GDPR-compliant consent management.

## Table of Contents

- **Getting Started**
  - [Quick Start Guide](./getting-started.md)
  - [Installation](./installation.md)
  - [Requirements](./installation/requirements.md)
  - [Configuration](./installation/configuration.md)

- **Usage**
  - [Usage Overview](./usage.md)
  - [Tracking Page Views](./usage/tracking-page-views.md)
  - [Tracking Events](./usage/tracking-events.md)
  - [Goals & Conversions](./usage/goals-conversions.md)
  - [Date Ranges](./usage/date-ranges.md)
  - [Helper Functions](./usage/helper-functions.md)

- **Livewire Components**
  - [Components Overview](./components.md)
  - [Analytics Dashboard](./components/analytics-dashboard.md)
  - [Stats Cards](./components/stats-cards.md)
  - [Visitors Chart](./components/visitors-chart.md)
  - [Top Pages](./components/top-pages.md)
  - [Traffic Sources](./components/traffic-sources.md)
  - [Realtime Visitors](./components/realtime-visitors.md)

- **API Reference**
  - [API Overview](./api.md)
  - [Models](./api/models.md)
  - [Services](./api/services.md)
  - [Data Objects](./api/data-objects.md)
  - [Events](./api/events.md)
  - [Contracts](./api/contracts.md)

- **Advanced Topics**
  - [Advanced Overview](./advanced.md)
  - [Multi-Tenancy](./advanced/multi-tenancy.md)
  - [Privacy & Consent](./advanced/privacy-consent.md)
  - [Multiple Providers](./advanced/multiple-providers.md)
  - [Caching](./advanced/caching.md)
  - [Artisan Commands](./advanced/artisan-commands.md)

- **Help**
  - [FAQ](./faq.md)
  - [Troubleshooting](./troubleshooting.md)

## Features

- **Privacy-First Design**: GDPR-compliant with built-in consent management and IP anonymization
- **Multi-Provider Support**: Chain multiple analytics providers (Local, Google Analytics, Plausible)
- **Real-Time Dashboard**: Live visitor tracking with auto-updating widgets
- **Multi-Tenancy**: Support for multi-site and multi-tenant applications
- **Livewire Components**: Pre-built dashboard widgets with Chart.js integration
- **Goal Tracking**: Define and track conversions with URL, event, and engagement-based goals
- **Session Management**: Automatic session tracking with engagement metrics
- **Blade Directives**: Easy integration with `@analyticsScripts` and `@analyticsConsent`

## Quick Example

```blade
{{-- Include the tracking script --}}
@analyticsScripts

{{-- Show consent banner if required --}}
@analyticsConsentBanner

{{-- Display the analytics dashboard --}}
<livewire:artisanpack-analytics::analytics-dashboard />
```

```php
// Track a page view
trackPageView('/products', 'Products Page');

// Track a custom event
trackEvent('purchase', ['product_id' => 123], 99.99, 'ecommerce');

// Get analytics stats
$stats = analyticsStats(DateRange::last30Days());
```

## Support

For issues and feature requests, please visit the [GitLab repository](https://gitlab.com/jacob-martella-web-design/artisanpack-ui/analytics).

## License

ArtisanPack UI Analytics is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
