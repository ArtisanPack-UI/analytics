---
title: Installation Overview
---

# Installation

This section covers everything you need to know about installing and configuring ArtisanPack UI Analytics in your Laravel application.

## Quick Install

```bash
composer require artisanpack-ui/analytics
php artisan analytics:install
```

## Detailed Guides

- [Requirements](./installation/requirements.md) - System requirements and prerequisites
- [Installation Guide](./installation/installation.md) - Step-by-step installation instructions
- [Configuration](./installation/configuration.md) - Complete configuration reference

## What Gets Installed

The installation command performs the following:

1. **Publishes Configuration** - Creates `config/artisanpack/analytics.php`
2. **Runs Migrations** - Creates the necessary database tables
3. **Publishes Assets** - Publishes the JavaScript tracker to your public directory

## Database Tables

The following tables are created during installation:

| Table | Purpose |
|-------|---------|
| `analytics_sites` | Multi-tenant site configurations |
| `analytics_visitors` | Unique visitor records |
| `analytics_sessions` | Session tracking data |
| `analytics_page_views` | Page view records |
| `analytics_events` | Custom event tracking |
| `analytics_goals` | Goal definitions |
| `analytics_conversions` | Goal conversion records |
| `analytics_consents` | GDPR consent records |
| `analytics_aggregates` | Aggregated historical data |

## Next Steps

After installation, you should:

1. [Configure](./installation/configuration.md) the package for your needs
2. [Add the tracking script](../usage/tracking-page-views.md) to your layouts
3. [Set up the dashboard](../components/analytics-dashboard.md) to view your data
