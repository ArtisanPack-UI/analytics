---
title: Artisan Commands
---

# Artisan Commands

ArtisanPack UI Analytics provides several Artisan commands for management and maintenance.

## Installation Commands

### analytics:install

Install the package with all required setup:

```bash
php artisan analytics:install
```

Options:
- `--skip-migrations` - Don't run migrations
- `--skip-assets` - Don't publish assets
- `--force` - Overwrite existing files

```bash
# Fresh install
php artisan analytics:install

# Skip migrations (run manually later)
php artisan analytics:install --skip-migrations

# Force overwrite existing config
php artisan analytics:install --force
```

## Site Management

### analytics:create-site

Create a new analytics site:

```bash
php artisan analytics:create-site "Site Name" --domain=example.com
```

Options:
- `--domain=` - Site domain
- `--api-key=` - Custom API key (auto-generated if not provided)
- `--tenant-id=` - Tenant ID for multi-tenant setups

```bash
# Basic site
php artisan analytics:create-site "My Website" --domain=mywebsite.com

# With custom API key
php artisan analytics:create-site "My Website" --domain=mywebsite.com --api-key=custom_key_123

# For specific tenant
php artisan analytics:create-site "Client Site" --domain=client.com --tenant-id=1
```

### analytics:list-sites

List all configured sites:

```bash
php artisan analytics:list-sites
```

Output:
```text
+----+-------------+------------------+------------+---------+
| ID | Name        | Domain           | API Key    | Active  |
+----+-------------+------------------+------------+---------+
| 1  | Main Site   | example.com      | abc123...  | Yes     |
| 2  | Client Site | client.com       | def456...  | Yes     |
| 3  | Test Site   | test.example.com | ghi789...  | No      |
+----+-------------+------------------+------------+---------+
```

### analytics:regenerate-api-key

Generate a new API key for a site:

```bash
php artisan analytics:regenerate-api-key 1
```

Or by domain:

```bash
php artisan analytics:regenerate-api-key --domain=example.com
```

## Data Management

### analytics:cleanup

Clean up old analytics data based on retention settings:

```bash
php artisan analytics:cleanup
```

Options:
- `--days=` - Override retention period
- `--dry-run` - Show what would be deleted without deleting
- `--force` - Skip confirmation

```bash
# Preview cleanup
php artisan analytics:cleanup --dry-run

# Clean data older than 30 days
php artisan analytics:cleanup --days=30

# Force cleanup without confirmation
php artisan analytics:cleanup --force
```

### analytics:aggregate

Aggregate raw data into summary tables:

```bash
php artisan analytics:aggregate
```

Options:
- `--date=` - Aggregate for specific date
- `--period=` - Aggregation period (day, week, month)

```bash
# Aggregate yesterday's data
php artisan analytics:aggregate --date=yesterday

# Monthly aggregation
php artisan analytics:aggregate --period=month
```

### analytics:export

Export analytics data:

```bash
php artisan analytics:export --format=csv --output=analytics.csv
```

Options:
- `--site-id=` - Export for specific site
- `--start-date=` - Start date
- `--end-date=` - End date
- `--format=` - Output format (csv, json)
- `--output=` - Output file path

```bash
# Export last 30 days as CSV
php artisan analytics:export --start-date="-30 days" --format=csv --output=export.csv

# Export specific site as JSON
php artisan analytics:export --site-id=1 --format=json --output=site1.json
```

### analytics:delete-visitor

Delete all data for a specific visitor (GDPR compliance):

```bash
php artisan analytics:delete-visitor {visitor_id}
```

```bash
# Delete by visitor ID
php artisan analytics:delete-visitor 123

# Delete by fingerprint
php artisan analytics:delete-visitor --fingerprint=abc123def456
```

## Cache Commands

### analytics:clear-cache

Clear all analytics cache:

```bash
php artisan analytics:clear-cache
```

Options:
- `--site-id=` - Clear cache for specific site only

```bash
# Clear all cache
php artisan analytics:clear-cache

# Clear cache for specific site
php artisan analytics:clear-cache --site-id=1
```

### analytics:warm-cache

Pre-populate cache for better performance:

```bash
php artisan analytics:warm-cache
```

Options:
- `--site-id=` - Warm cache for specific site

## Statistics Commands

### analytics:stats

Display quick statistics:

```bash
php artisan analytics:stats
```

Options:
- `--site-id=` - Stats for specific site
- `--period=` - Time period (today, week, month, year)

```bash
# Today's stats
php artisan analytics:stats --period=today

# Monthly stats for specific site
php artisan analytics:stats --site-id=1 --period=month
```

Output:
```text
Analytics Statistics (Last 30 Days)
===================================

Page Views:     12,345
Visitors:       3,456
Sessions:       4,567
Bounce Rate:    45.2%
Avg. Duration:  2m 34s

Top Pages:
  1. /products (1,234 views)
  2. /about (567 views)
  3. /contact (345 views)
```

## Scheduling Commands

Add to your scheduler for automated maintenance:

```php
// routes/console.php or app/Console/Kernel.php

use Illuminate\Support\Facades\Schedule;

// Daily cleanup at 3 AM
Schedule::command('analytics:cleanup')->dailyAt('03:00');

// Hourly aggregation
Schedule::command('analytics:aggregate')->hourly();

// Warm cache every hour
Schedule::command('analytics:warm-cache')->hourly();

// Weekly full aggregation
Schedule::command('analytics:aggregate --period=week')->weekly();
```

## Creating Custom Commands

Extend analytics functionality with custom commands:

```php
namespace App\Console\Commands;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Console\Command;

class AnalyticsReport extends Command
{
    protected $signature = 'analytics:report {--email=}';
    protected $description = 'Generate and send analytics report';

    public function handle(AnalyticsQuery $query): int
    {
        $range = DateRange::lastWeek();
        $stats = $query->getStats($range);

        $this->info("Weekly Analytics Report");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Page Views', number_format($stats['pageviews'])],
                ['Visitors', number_format($stats['visitors'])],
                ['Sessions', number_format($stats['sessions'])],
                ['Bounce Rate', $stats['bounce_rate'] . '%'],
            ]
        );

        if ($email = $this->option('email')) {
            // Send email with report
        }

        return Command::SUCCESS;
    }
}
```

## Command Exit Codes

All commands follow standard exit codes:

- `0` - Success
- `1` - General error
- `2` - Misuse of command

Use in scripts:

```bash
php artisan analytics:cleanup
if [ $? -eq 0 ]; then
    echo "Cleanup successful"
else
    echo "Cleanup failed"
fi
```
