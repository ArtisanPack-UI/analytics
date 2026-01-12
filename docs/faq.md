---
title: FAQ
---

# Frequently Asked Questions

## General Questions

### What is ArtisanPack UI Analytics?

ArtisanPack UI Analytics is a privacy-first analytics package for Laravel applications. It provides:

- Local database storage for complete data ownership
- Real-time dashboards with Livewire components
- GDPR-compliant consent management
- Multi-tenant support for SaaS applications
- Optional integration with Google Analytics and Plausible

### How is this different from Google Analytics?

| Feature | ArtisanPack UI Analytics | Google Analytics |
|---------|--------------------------|------------------|
| Data ownership | Your database | Google's servers |
| Privacy | Full control | Limited |
| GDPR compliance | Built-in | Requires configuration |
| Offline access | Yes | No |
| Custom queries | Full SQL access | Limited API |
| Cost | Included | Free tier, paid for more |

### Is it GDPR compliant?

Yes, the package is designed with GDPR compliance in mind:

- IP anonymization by default
- Built-in consent management
- Do Not Track support
- Data export and deletion capabilities
- Configurable data retention

### Can I use it with my existing analytics?

Yes! You can run ArtisanPack UI Analytics alongside other providers:

```php
ANALYTICS_ACTIVE_PROVIDERS=local,google
```

This gives you the benefits of both: local data ownership and Google's analysis tools.

## Installation Questions

### What are the requirements?

- PHP 8.2+
- Laravel 11 or 12
- A database (MySQL, PostgreSQL, SQLite, SQL Server)
- Optional: Queue worker for better performance

### Does it work with Laravel 10?

No, the package requires Laravel 11 or later due to dependency requirements.

### Can I use a separate database?

Yes, configure a separate connection:

```php
'local' => [
    'connection' => 'analytics',
],
```

### How do I update the package?

```bash
composer update artisanpack-ui/analytics
php artisan migrate
php artisan vendor:publish --tag=analytics-assets --force
```

## Tracking Questions

### How do I track page views?

Add the tracking script to your layout:

```blade
@analyticsScripts
```

Page views are tracked automatically. You can also track manually:

```php
trackPageView('/custom-page', 'Page Title');
```

### How do I track events?

```php
trackEvent('button_click', ['button_id' => 'submit']);
```

Or in JavaScript:

```javascript
analytics.trackEvent('button_click', { button_id: 'submit' });
```

### Why aren't my page views being recorded?

Check these common issues:

1. Is analytics enabled? `ANALYTICS_ENABLED=true`
2. Is the tracking script included? `@analyticsScripts`
3. Is the path excluded? Check `excluded_paths` config
4. Is the visitor a bot? Bots are filtered by default
5. Is consent required but not granted?

### How do I track single-page applications?

Enable hash change tracking:

```php
'tracker' => [
    'track_hash_changes' => true,
],
```

Or manually track route changes:

```javascript
analytics.trackPageView(window.location.pathname, document.title);
```

### Can I track logged-in users?

Yes, associate tracking with user IDs:

```php
trackEvent('user_action', [
    'user_id' => auth()->id(),
]);
```

## Dashboard Questions

### How do I access the dashboard?

Navigate to `/analytics/dashboard` (or your configured route).

### Can I embed the dashboard in my admin panel?

Yes:

```blade
<livewire:artisanpack-analytics::analytics-dashboard />
```

### Can I use individual widgets?

Yes, all widgets are available separately:

```blade
<livewire:artisanpack-analytics::stats-cards />
<livewire:artisanpack-analytics::visitors-chart />
<livewire:artisanpack-analytics::top-pages />
```

### How do I customize the dashboard design?

Publish and modify the views:

```bash
php artisan vendor:publish --tag=analytics-views
```

Edit files in `resources/views/vendor/artisanpack-analytics/`.

## Performance Questions

### Will this slow down my application?

With proper configuration, impact is minimal:

1. Enable queue processing for async tracking
2. Use appropriate cache durations
3. Set reasonable data retention

### How do I handle high traffic?

1. Enable queue processing:
```php
'queue_processing' => true,
```

2. Run dedicated queue workers:
```bash
php artisan queue:work --queue=analytics
```

3. Use Redis for caching:
```php
CACHE_DRIVER=redis
```

### How much database storage is needed?

Depends on traffic. Rough estimates per month:

| Daily Page Views | Storage/Month |
|------------------|---------------|
| 1,000 | ~50 MB |
| 10,000 | ~500 MB |
| 100,000 | ~5 GB |

Configure retention to manage size:

```php
'retention' => [
    'period' => 90, // days
],
```

## Multi-Tenant Questions

### How do I set up multi-tenancy?

1. Enable multi-tenant mode:
```php
ANALYTICS_MULTI_TENANT=true
```

2. Create sites for each tenant:
```php
Site::create(['name' => 'Tenant', 'domain' => 'tenant.com']);
```

3. Configure resolvers for your setup.

### Can each tenant have different settings?

Yes, use site-specific settings:

```php
$site->settings = [
    'tracking' => ['anonymize_ip' => true],
    'privacy' => ['consent_required' => true],
];
```

### How do I provide tenant dashboards?

Pass the site ID to components:

```blade
<livewire:artisanpack-analytics::analytics-dashboard
    :site-id="$tenant->site->id"
/>
```

## Privacy Questions

### How do I show a consent banner?

```blade
@analyticsConsentBanner
```

### How do I handle "Do Not Track"?

It's enabled by default:

```php
'privacy' => [
    'respect_dnt' => true,
],
```

### How do I delete a user's data?

```bash
php artisan analytics:delete-visitor {visitor_id}
```

Or programmatically:

```php
app(DataDeletionService::class)->deleteVisitorData($visitorId);
```

### How long is data retained?

Default is 90 days. Configure in config:

```php
'retention' => [
    'period' => 90,
],
```

## Integration Questions

### Does it work with Livewire?

Yes, the package is built with Livewire 3. All dashboard components are Livewire components.

### Does it work with Inertia?

Yes, you can:
- Use the helper functions in your controllers
- Create API endpoints for your frontend
- Embed Livewire components in your Inertia pages

### Can I export data to other tools?

Yes:

```bash
php artisan analytics:export --format=csv --output=data.csv
```

Or use the dashboard export buttons for CSV/JSON.
