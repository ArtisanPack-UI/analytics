---
title: Usage Overview
---

# Usage

This section covers how to use ArtisanPack UI Analytics to track and analyze visitor behavior on your website.

## Core Concepts

### Tracking vs. Querying

ArtisanPack UI Analytics provides two main types of functionality:

1. **Tracking** - Recording page views, events, and user interactions
2. **Querying** - Retrieving and analyzing collected data

### Automatic vs. Manual Tracking

- **Automatic tracking** happens via the JavaScript tracker (page views, sessions, device info)
- **Manual tracking** is done via PHP helper functions for custom events

## Guides

- [Tracking Page Views](./usage/tracking-page-views.md) - Automatic and manual page view tracking
- [Tracking Events](./usage/tracking-events.md) - Custom event tracking
- [Goals & Conversions](./usage/goals-conversions.md) - Setting up and tracking conversion goals
- [Date Ranges](./usage/date-ranges.md) - Working with date ranges for queries
- [Helper Functions](./usage/helper-functions.md) - Complete reference of available helpers

## Quick Reference

### Tracking

```php
// Track a page view
trackPageView('/products', 'Products Page');

// Track an event
trackEvent('button_click', ['button_id' => 'cta-main']);

// Track a form submission
analyticsTrackForm('contact-form', ['source' => 'homepage']);

// Track a purchase
analyticsTrackPurchase(99.99, 'USD', [
    ['name' => 'Widget', 'quantity' => 2],
]);
```

### Querying

```php
use ArtisanPackUI\Analytics\Data\DateRange;

// Get stats for last 7 days
$stats = analyticsStats(DateRange::last7Days());

// Get page views over time
$pageViews = analyticsPageViews(DateRange::last30Days(), 'day');

// Get top pages
$topPages = analyticsTopPages(DateRange::thisMonth(), 10);

// Get traffic sources
$sources = analyticsTrafficSources(DateRange::last7Days());

// Get real-time visitors
$realtime = analyticsRealtime(5); // last 5 minutes
```

### Consent Management

```php
// Check consent status
if (analyticsHasConsent($fingerprint, 'analytics')) {
    // Track data
}

// Grant consent
analyticsGrantConsent($fingerprint, ['analytics', 'marketing']);

// Revoke consent
analyticsRevokeConsent($fingerprint, ['marketing']);
```

## JavaScript API

The JavaScript tracker also provides a global `analytics` object:

```javascript
// Track a page view
analytics.trackPageView('/custom-page', 'Page Title');

// Track an event
analytics.trackEvent('video_play', { video_id: '123' });

// Check consent
analytics.hasConsent('analytics');

// Grant consent
analytics.grantConsent(['analytics']);
```

## Next Steps

- [Configure Privacy Settings](./installation/configuration.md#privacy-configuration)
- [View the Dashboard](./components/analytics-dashboard.md)
- [Set Up Multi-Tenancy](./advanced/multi-tenancy.md)
