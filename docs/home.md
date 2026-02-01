---
title: ArtisanPack UI Analytics Documentation Home
---

# ArtisanPack UI Analytics Documentation

Welcome to the ArtisanPack UI Analytics documentation. This package provides comprehensive, privacy-first analytics tracking for Laravel applications with multi-provider support, real-time dashboards, and GDPR-compliant consent management.

## Table of Contents

- **Getting Started**
  - [Quick Start Guide](Getting-Started)
  - [Installation](Installation)
  - [Requirements](Installation-Requirements)
  - [Configuration](Installation-Configuration)

- **Usage**
  - [Usage Overview](Usage)
  - [Tracking Page Views](Usage-Tracking-Page-Views)
  - [Tracking Events](Usage-Tracking-Events)
  - [Goals & Conversions](Usage-Goals-Conversions)
  - [Date Ranges](Usage-Date-Ranges)
  - [Helper Functions](Usage-Helper-Functions)

- **Livewire Components**
  - [Components Overview](Components)
  - [Analytics Dashboard](Components-Analytics-Dashboard)
  - [Stats Cards](Components-Stats-Cards)
  - [Visitors Chart](Components-Visitors-Chart)
  - [Top Pages](Components-Top-Pages)
  - [Traffic Sources](Components-Traffic-Sources)
  - [Realtime Visitors](Components-Realtime-Visitors)

- **API Reference**
  - [API Overview](Api)
  - [Models](Api-Models)
  - [Services](Api-Services)
  - [Data Objects](Api-Data-Objects)
  - [Events](Api-Events)
  - [Contracts](Api-Contracts)

- **Advanced Topics**
  - [Advanced Overview](Advanced)
  - [Multi-Tenancy](Advanced-Multi-Tenancy)
  - [Privacy & Consent](Advanced-Privacy-Consent)
  - [Multiple Providers](Advanced-Multiple-Providers)
  - [Caching](Advanced-Caching)
  - [Artisan Commands](Advanced-Artisan-Commands)

- **Help**
  - [FAQ](Faq)
  - [Troubleshooting](Troubleshooting)

## Features

- **Privacy-First Design**: GDPR-compliant with built-in consent management and IP anonymization
- **Multi-Provider Support**: Chain multiple analytics providers (Local, Google Analytics, Plausible)
- **Real-Time Dashboard**: Live visitor tracking with auto-updating widgets
- **Multi-Tenancy**: Support for multi-site and multi-tenant applications
- **Livewire Components**: Pre-built dashboard widgets with Chart.js integration
- **Goal Tracking**: Define and track conversions with URL, event, and engagement-based goals
- **Session Management**: Automatic session tracking with engagement metrics
- **Blade Directives**: Easy integration with `@analyticsScripts` and `@analyticsConsentBanner`

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
