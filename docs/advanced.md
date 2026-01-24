---
title: Advanced Overview
---

# Advanced Topics

This section covers advanced features and configuration options for ArtisanPack UI Analytics.

## Guides

- [Multi-Tenancy](./advanced/multi-tenancy.md) - Configure analytics for multi-tenant applications
- [Privacy & Consent](./advanced/privacy-consent.md) - GDPR compliance and consent management
- [Multiple Providers](./advanced/multiple-providers.md) - Use multiple analytics providers
- [Caching](./advanced/caching.md) - Optimize performance with caching
- [Artisan Commands](./advanced/artisan-commands.md) - CLI commands for management

## Overview

### Multi-Tenancy

ArtisanPack UI Analytics supports multi-tenant applications with:

- Site-based data isolation
- Multiple resolution strategies (domain, subdomain, API key, header)
- Per-site configuration overrides
- Cross-tenant reporting for platform administrators

### Privacy & Consent

Built-in GDPR compliance features:

- Consent management system
- IP anonymization
- Do Not Track support
- Data export and deletion
- Configurable retention policies

### Multiple Providers

Send analytics data to multiple destinations:

- Local database (default)
- Google Analytics 4
- Plausible Analytics
- Custom providers

### Performance Optimization

Optimize for high-traffic applications:

- Query caching
- Queue processing
- Data aggregation
- Efficient indexing

## Quick Setup Examples

### Multi-Tenant Setup

```php
// .env
ANALYTICS_MULTI_TENANT=true
ANALYTICS_BASE_DOMAIN=myapp.com

// Resolves tenant1.myapp.com, tenant2.myapp.com
```

### GDPR Compliance

```php
// .env
ANALYTICS_CONSENT_REQUIRED=true
ANALYTICS_ANONYMIZE_IP=true
ANALYTICS_RESPECT_DNT=true
ANALYTICS_RETENTION_DAYS=90
```

### Multiple Providers

```php
// .env
ANALYTICS_ACTIVE_PROVIDERS=local,google
ANALYTICS_GOOGLE_ENABLED=true
ANALYTICS_GOOGLE_MEASUREMENT_ID=G-XXXXXXXX
```

### High-Performance Setup

```php
// .env
ANALYTICS_QUEUE_PROCESSING=true
ANALYTICS_QUEUE_NAME=analytics
ANALYTICS_CACHE_DURATION=600
```

## Architecture Decisions

### Why Local-First?

The local provider stores all data in your database, giving you:

1. **Complete control** - Your data, your servers
2. **Privacy** - No third-party data sharing
3. **Flexibility** - Query data however you need
4. **Compliance** - Easier GDPR/CCPA compliance

### When to Use External Providers

Consider external providers when:

- You need advanced analysis features
- You want to consolidate with existing analytics
- You need real-time streaming capabilities
- You want automatic bot filtering

### Hybrid Approach

Many applications benefit from a hybrid approach:

```php
'active_providers' => ['local', 'google'],
```

This gives you:
- Full data ownership (local)
- Advanced features (Google)
- Redundancy

## Performance Considerations

### Database Indexes

The package creates indexes on:
- `analytics_page_views.created_at`
- `analytics_page_views.path`
- `analytics_sessions.started_at`
- `analytics_events.name`

For high-traffic sites, consider additional indexes based on your query patterns.

### Queue Processing

Enable queue processing for better response times:

```bash
# Start a dedicated analytics queue worker
php artisan queue:work --queue=analytics
```

### Data Retention

Configure retention to manage database size:

```php
'retention' => [
    'period' => 90,                    // Keep raw data for 90 days
    'aggregate_before_delete' => true, // Aggregate before deleting
],
```

## Next Steps

Choose the topic most relevant to your needs:

- Building a SaaS? Start with [Multi-Tenancy](./advanced/multi-tenancy.md)
- Launching in EU? Read [Privacy & Consent](./advanced/privacy-consent.md)
- Need integrations? See [Multiple Providers](./advanced/multiple-providers.md)
