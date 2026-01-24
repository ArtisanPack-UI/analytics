---
title: Installation Guide
---

# Installation Guide

This guide walks you through the complete installation process for ArtisanPack UI Analytics.

## Step 1: Install via Composer

```bash
composer require artisanpack-ui/analytics
```

## Step 2: Run the Installation Command

The package includes an installation command that handles all setup:

```bash
php artisan analytics:install
```

This command will:
- Publish the configuration file to `config/artisanpack/analytics.php`
- Run database migrations
- Publish the JavaScript tracker

### Installation Options

```bash
# Skip migrations (if you want to run them manually)
php artisan analytics:install --skip-migrations

# Skip publishing assets
php artisan analytics:install --skip-assets

# Force overwrite existing files
php artisan analytics:install --force
```

## Step 3: Configure Environment Variables

Add the following to your `.env` file:

```dotenv
# Enable/disable analytics
ANALYTICS_ENABLED=true

# Default provider (local, google, plausible)
ANALYTICS_PROVIDER=local

# Privacy settings
ANALYTICS_ANONYMIZE_IP=true
ANALYTICS_CONSENT_REQUIRED=false
ANALYTICS_RESPECT_DNT=true

# Queue settings (recommended for performance)
ANALYTICS_QUEUE_PROCESSING=true
ANALYTICS_QUEUE_NAME=analytics
```

## Step 4: Add the Tracking Script

Add the analytics tracking script to your main layout file:

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

## Step 5: Start the Queue Worker (Optional but Recommended)

If you have `queue_processing` enabled:

```bash
php artisan queue:work --queue=analytics
```

For production, configure a process manager like Supervisor.

## Step 6: Verify Installation

Visit `/analytics/dashboard` in your browser. If you see the analytics dashboard, the installation was successful.

## Manual Installation

If you prefer manual control over the installation process:

### Publish Configuration

```bash
php artisan vendor:publish --tag=analytics-config
```

### Run Migrations

```bash
php artisan migrate
```

### Publish Assets

```bash
php artisan vendor:publish --tag=analytics-assets
```

## Updating

When updating the package:

```bash
composer update artisanpack-ui/analytics

# Run any new migrations
php artisan migrate

# Optionally republish assets
php artisan vendor:publish --tag=analytics-assets --force
```

## Uninstalling

To remove the package:

1. Remove the package:
```bash
composer remove artisanpack-ui/analytics
```

2. Remove the configuration file:
```bash
rm config/artisanpack/analytics.php
```

3. Roll back migrations (optional):
```bash
php artisan migrate:rollback --path=vendor/artisanpack-ui/analytics/database/migrations
```

## Troubleshooting

### Dashboard Not Loading

- Ensure you're authenticated (default middleware requires `auth`)
- Check that the route is registered: `php artisan route:list | grep analytics`

### No Data Being Tracked

- Verify the tracking script is included in your layout
- Check browser console for JavaScript errors
- Ensure the API routes are accessible
- Check if bots/crawlers are being filtered

### Queue Jobs Not Processing

- Ensure the queue worker is running
- Check the `analytics` queue specifically
- Review failed jobs: `php artisan queue:failed`

For more help, see the [Troubleshooting](../troubleshooting.md) guide.
