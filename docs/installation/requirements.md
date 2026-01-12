---
title: Requirements
---

# Requirements

Before installing ArtisanPack UI Analytics, ensure your environment meets the following requirements.

## PHP Version

- **PHP 8.2** or higher

## Laravel Version

- **Laravel 11.x** or **Laravel 12.x**

## Required PHP Extensions

- `pdo` - For database connectivity
- `json` - For JSON encoding/decoding
- `mbstring` - For multi-byte string handling

## Required Packages

The following packages are automatically installed as dependencies:

| Package | Version | Purpose |
|---------|---------|---------|
| `doctrine/dbal` | ^4.0 | Database schema modifications |
| `livewire/livewire` | ^3.6.4 | Dashboard components |
| `nesbot/carbon` | ^2.0 or ^3.0 | Date/time handling |

## Database Support

ArtisanPack UI Analytics works with any database supported by Laravel:

- **MySQL** 5.7+
- **PostgreSQL** 9.6+
- **SQLite** 3.8.8+
- **SQL Server** 2017+

## Optional Requirements

### Queue Worker

For optimal performance, a queue worker is recommended when `queue_processing` is enabled (default):

```bash
php artisan queue:work --queue=analytics
```

### Scheduler

If using automated data retention and aggregation, ensure the Laravel scheduler is running:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Browser Support

The JavaScript tracker supports all modern browsers:

- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 79+

## External Provider Requirements

If using external analytics providers:

### Google Analytics 4

- Google Analytics 4 property
- Measurement ID (starts with `G-`)
- Optional: API Secret for server-side tracking

### Plausible Analytics

- Plausible account (self-hosted or cloud)
- Site domain configured in Plausible
- API key for data retrieval

## Verification

After installation, verify your setup by visiting the analytics dashboard at `/analytics/dashboard` (or your configured route).
