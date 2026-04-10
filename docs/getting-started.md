---
title: Getting Started
---

# Getting Started

This guide will help you get up and running with ArtisanPack UI Analytics in just a few minutes.

## Prerequisites

Before you begin, ensure you have:

- PHP 8.2 or higher
- Laravel 10, 11, or 12
- Composer installed
- A database (MySQL, PostgreSQL, or SQLite)

## Installation

Install the package via Composer:

```bash
composer require artisanpack-ui/analytics
```

Run the installation command:

```bash
php artisan analytics:install
```

This command will:
- Publish the configuration file
- Run database migrations
- Publish the JavaScript tracker

## Basic Setup

### 1. Add the Tracking Script

Add the analytics tracking script to your main layout file, typically `resources/views/layouts/app.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <!-- Your head content -->
</head>
<body>
    {{ $slot }}

    {{-- Add before closing body tag --}}
    @analyticsScripts
</body>
</html>
```

### 2. Add the Consent Banner (Optional)

If you need GDPR-compliant consent management:

```blade
@analyticsConsentBanner
```

### 3. View the Dashboard

Navigate to `/analytics/dashboard` in your browser to view your analytics dashboard. Make sure you're authenticated if using the default middleware.

## Tracking Your First Page View

Page views are tracked automatically by the JavaScript tracker. You can also track them manually:

```php
use function trackPageView;

trackPageView('/custom-page', 'Custom Page Title');
```

## Tracking Events

Track custom events in your application:

```php
use function trackEvent;

// Basic event
trackEvent('button_click');

// Event with properties and value
trackEvent('purchase', [
    'product_id' => 123,
    'product_name' => 'Widget',
], 49.99, 'ecommerce');
```

## Viewing Analytics Data

### Using Helper Functions

```php
use function analyticsStats;
use function analyticsPageViews;
use ArtisanPackUI\Analytics\Data\DateRange;

// Get comprehensive stats
$stats = analyticsStats(DateRange::last7Days());

// Get page views over time
$pageViews = analyticsPageViews(DateRange::last30Days(), 'day');
```

### Using the Dashboard Component

Add the dashboard to any Blade view:

```blade
<livewire:artisanpack-analytics::analytics-dashboard />
```

Or use individual widgets:

```blade
<livewire:artisanpack-analytics::stats-cards />
<livewire:artisanpack-analytics::visitors-chart />
<livewire:artisanpack-analytics::top-pages />
```

## Next Steps

- [Installation Details](Installation-Installation) - Detailed installation guide
- [Configuration](Installation-Configuration) - Configure the package for your needs
- [Tracking Page Views](Usage-Tracking-Page-Views) - Learn about page view tracking
- [Tracking Events](Usage-Tracking-Events) - Learn about event tracking
- [Dashboard Components](Components) - Explore available Livewire components
