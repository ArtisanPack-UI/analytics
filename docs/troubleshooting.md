---
title: Troubleshooting
---

# Troubleshooting

This guide covers common issues and their solutions.

## Installation Issues

### Migration fails with "table already exists"

The analytics tables may already exist from a previous installation.

**Solution:**

```bash
# Check existing tables
php artisan tinker
>>> Schema::hasTable('analytics_page_views')

# If tables exist, you can:
# 1. Skip migrations during install
php artisan analytics:install --skip-migrations

# 2. Or manually drop tables and reinstall
php artisan migrate:rollback --path=vendor/artisanpack-ui/analytics/database/migrations
php artisan migrate
```

### Config file not publishing

**Solution:**

```bash
# Force publish
php artisan vendor:publish --tag=analytics-config --force

# Or manually check
ls config/artisanpack/
```

### Assets not found (404 on analytics.js)

**Solution:**

```bash
# Publish assets
php artisan vendor:publish --tag=analytics-assets --force

# Verify file exists
ls public/js/analytics.js
```

## Tracking Issues

### No page views being recorded

**Check these in order:**

1. **Is analytics enabled?**
```php
// .env
ANALYTICS_ENABLED=true
```

2. **Is the tracking script included?**
```blade
{{-- Check your layout file --}}
@analyticsScripts
```

3. **Is the path excluded?**
```php
// Check config
'excluded_paths' => ['/admin/*', '/api/*'],
```

4. **Is the visitor a bot?**
Check if the user agent matches excluded patterns.

5. **Is consent required but not given?**
```php
'privacy' => ['consent_required' => false], // or check consent
```

6. **Check browser console for JavaScript errors**

7. **Check Laravel logs:**
```bash
tail -f storage/logs/laravel.log
```

### Events not tracking

**Solution:**

```php
// Verify tracking works
trackEvent('test_event', ['test' => true]);

// Check events table
Event::latest()->first();

// Check if event name is allowed (if restricted)
'events' => ['allowed_names' => []], // Empty = allow all
```

### Queue jobs not processing

**Solution:**

```bash
# Check if queue worker is running
php artisan queue:work --queue=analytics

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Check job is dispatched to correct queue
'local' => ['queue_name' => 'analytics'],
```

### Realtime visitors always shows 0

**Possible causes:**

1. No recent page views (within last 5 minutes)
2. Cache issue - clear analytics cache
3. Database connection issue

**Solution:**

```bash
# Clear cache
php artisan analytics:clear-cache

# Check recent page views
php artisan tinker
>>> PageView::where('created_at', '>=', now()->subMinutes(5))->count()
```

## Dashboard Issues

### Dashboard returns 404

**Solution:**

```bash
# Check route is registered
php artisan route:list | grep analytics

# Check middleware (must be authenticated by default)
'dashboard_middleware' => ['web', 'auth'],

# Try accessing while logged in
```

### Dashboard shows no data

**Solution:**

1. Check date range - default is last 7 days
2. Verify data exists:
```php
PageView::count();
Session::count();
```

3. Check site filter (multi-tenant):
```blade
<livewire:artisanpack-analytics::analytics-dashboard :site-id="null" />
```

4. Clear cache:
```bash
php artisan analytics:clear-cache
```

### Charts not rendering

**Solution:**

1. Ensure Chart.js is loaded:
```blade
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

2. Check for JavaScript errors in browser console

3. Verify chart data:
```php
// In component
dd($this->chartData);
```

### Livewire errors

**Common issues:**

1. **Component not found:**
```bash
# Clear view cache
php artisan view:clear
php artisan cache:clear
```

2. **Alpine.js conflicts:**
Livewire 3 includes Alpine.js. Don't load it separately.

3. **Component class not found:**
```bash
composer dump-autoload
```

## Performance Issues

### Slow dashboard loading

**Solutions:**

1. **Increase cache duration:**
```php
'dashboard' => ['cache_duration' => 600], // 10 minutes
```

2. **Reduce date range:**
Default to shorter periods for initial load.

3. **Check database indexes:**
```sql
SHOW INDEX FROM analytics_page_views;
```

4. **Enable query logging to find slow queries:**
```php
DB::enableQueryLog();
// ... load dashboard
dd(DB::getQueryLog());
```

### High memory usage

**Solutions:**

1. **Use queue processing:**
```php
'queue_processing' => true,
```

2. **Reduce retention period:**
```php
'retention' => ['period' => 30],
```

3. **Run cleanup:**
```bash
php artisan analytics:cleanup
```

### Database growing too fast

**Solutions:**

1. **Reduce retention:**
```php
'retention' => ['period' => 30],
```

2. **Enable aggregation:**
```php
'retention' => ['aggregate_before_delete' => true],
```

3. **Schedule cleanup:**
```php
Schedule::command('analytics:cleanup')->daily();
```

## Multi-Tenant Issues

### Site not resolving

**Debug steps:**

```php
use ArtisanPackUI\Analytics\Services\TenantManager;

$manager = app(TenantManager::class);
$site = $manager->resolveSite(request());
dd($site);
```

**Check:**

1. Is multi-tenant enabled?
```php
'multi_tenant' => ['enabled' => true],
```

2. Does the site exist?
```php
Site::where('domain', 'example.com')->first();
```

3. Is the resolver configured correctly?

### Data mixing between tenants

**Solution:**

Ensure all queries use site scope:

```php
PageView::forSite($siteId)->get();
// Not: PageView::all();
```

### API key not working

**Check:**

1. Is the API key correct?
```php
Site::where('api_key', 'your_key')->first();
```

2. Is the header correct?
```
X-API-Key: your_key
```

3. Is query parameter enabled (if using)?
```php
'allow_query_api_key' => true,
```

## Privacy/Consent Issues

### Consent banner not showing

**Solution:**

```blade
{{-- Ensure directive is in layout --}}
@analyticsConsentBanner

{{-- Or use component directly --}}
<livewire:artisanpack-analytics::consent-banner />
```

### Tracking despite no consent

**Check:**

```php
// Is consent required?
'privacy' => ['consent_required' => true],

// Check consent status
analyticsHasConsent($fingerprint, 'analytics');
```

### IP not being anonymized

**Solution:**

```php
'privacy' => [
    'anonymization' => [
        'ip_address' => true,
    ],
],

// Also check local provider setting
'local' => ['anonymize_ip' => true],
```

## Error Messages

### "Tracking is disabled"

Analytics is turned off. Enable it:

```php
ANALYTICS_ENABLED=true
```

### "Site not found"

In multi-tenant mode, no site could be resolved. Check:
- Site exists in database
- Resolver is correctly configured
- Request has correct identifiers (API key, domain, etc.)

### "Provider not found: xyz"

The provider isn't registered:

```php
// Check active providers
'active_providers' => ['local'], // 'xyz' not included
```

### "Queue connection not configured"

Queue driver isn't set up:

```bash
# Check queue config
php artisan queue:work
# If fails, configure queue driver
QUEUE_CONNECTION=database
php artisan queue:table
php artisan migrate
```

## Getting Help

If you can't resolve an issue:

1. Check the [FAQ](./faq.md) for common questions
2. Search existing issues on GitLab
3. Create a new issue with:
   - Laravel version
   - Package version
   - Error message/logs
   - Steps to reproduce
   - Configuration (sanitized)

Repository: [GitLab](https://gitlab.com/jacob-martella-web-design/artisanpack-ui/analytics)
