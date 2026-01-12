---
title: Multi-Tenancy
---

# Multi-Tenancy

ArtisanPack UI Analytics provides comprehensive multi-tenant support for SaaS and multi-site applications.

## Enabling Multi-Tenancy

```php
// .env
ANALYTICS_MULTI_TENANT=true
```

```php
// config/artisanpack/analytics.php
'multi_tenant' => [
    'enabled' => env('ANALYTICS_MULTI_TENANT', false),
],
```

## Site Resolution

Sites are resolved from incoming requests using configurable resolvers.

### Available Resolvers

| Resolver | Priority | Method |
|----------|----------|--------|
| `ApiKeyResolver` | 10 | API key in header/query |
| `HeaderResolver` | 50 | X-Site-ID header |
| `SubdomainResolver` | 90 | Subdomain extraction |
| `DomainResolver` | 100 | Full domain match |

### Configure Resolvers

```php
// config/artisanpack/analytics.php
'multi_tenant' => [
    'resolvers' => [
        \ArtisanPackUI\Analytics\Resolvers\ApiKeyResolver::class,
        \ArtisanPackUI\Analytics\Resolvers\HeaderResolver::class,
        \ArtisanPackUI\Analytics\Resolvers\SubdomainResolver::class,
        \ArtisanPackUI\Analytics\Resolvers\DomainResolver::class,
    ],
],
```

## Resolution Strategies

### API Key Resolution

Best for embedded tracking scripts:

```javascript
// In customer's website
<script src="https://yourapp.com/analytics.js" data-api-key="site_abc123"></script>
```

The API key can be sent via:
- `X-API-Key` header
- `api_key` query parameter (if enabled)

```php
'multi_tenant' => [
    'allow_query_api_key' => env('ANALYTICS_ALLOW_QUERY_API_KEY', false),
],
```

### Header Resolution

For API integrations:

```php
// HTTP Request
GET /api/analytics/track HTTP/1.1
X-Site-ID: 123
```

Configure the header name:

```php
'multi_tenant' => [
    'site_header' => env('ANALYTICS_SITE_HEADER', 'X-Site-ID'),
],
```

### Subdomain Resolution

For subdomain-based multi-tenancy:

```php
// .env
ANALYTICS_BASE_DOMAIN=myapp.com

// Resolves:
// tenant1.myapp.com → Site with domain "tenant1"
// tenant2.myapp.com → Site with domain "tenant2"
```

### Domain Resolution

For custom domain support:

```php
// Sites table
| id | domain           |
|----|------------------|
| 1  | client1.com      |
| 2  | client2.com      |

// Request to client1.com → Site ID 1
```

## Creating Sites

### Via Code

```php
use ArtisanPackUI\Analytics\Models\Site;

$site = Site::create([
    'name' => 'Client Website',
    'domain' => 'client.example.com',
    'api_key' => Str::random(32),
    'is_active' => true,
    'tenant_id' => $tenant->id, // Optional
    'settings' => [
        'tracking' => [
            'enabled' => true,
            'anonymize_ip' => true,
        ],
    ],
]);
```

### Via Artisan

```bash
php artisan analytics:create-site "Client Website" --domain=client.example.com
```

## Site Settings

Each site can have custom settings that override global configuration:

```php
$site->settings = [
    'tracking' => [
        'enabled' => true,
        'respect_dnt' => true,
        'anonymize_ip' => true,
        'track_hash_changes' => false,
    ],
    'dashboard' => [
        'public' => false,
        'default_date_range' => 30,
        'realtime_enabled' => true,
    ],
    'privacy' => [
        'consent_required' => true,
        'excluded_paths' => ['/admin/*'],
    ],
    'features' => [
        'events' => true,
        'goals' => true,
        'conversions' => true,
    ],
];

$site->save();
```

## Using Site Context

### In Controllers

```php
use ArtisanPackUI\Analytics\Services\TenantManager;

class DashboardController extends Controller
{
    public function index(TenantManager $tenantManager)
    {
        $site = $tenantManager->currentSite();

        if (!$site) {
            abort(404, 'Site not found');
        }

        return view('dashboard', compact('site'));
    }
}
```

### In Livewire Components

```php
<livewire:artisanpack-analytics::analytics-dashboard
    :site-id="$site->id"
/>
```

### In Queries

```php
use ArtisanPackUI\Analytics\Models\PageView;

// Automatic site scoping
$pageViews = PageView::forSite($siteId)->get();

// Or using tenant ID
$pageViews = PageView::forTenant($tenantId)->get();
```

## Multi-Tenant Dashboard

Use the dedicated multi-tenant dashboard:

```blade
<livewire:artisanpack-analytics::multi-tenant-dashboard />
```

Or the site selector:

```blade
<livewire:artisanpack-analytics::site-selector />
```

## Platform Dashboard

For platform administrators to view all sites:

```blade
<livewire:artisanpack-analytics::platform-dashboard />
```

## Cross-Tenant Reporting

Use the `CrossTenantReporting` service for aggregate stats:

```php
use ArtisanPackUI\Analytics\Services\CrossTenantReporting;

$reporting = app(CrossTenantReporting::class);

// Get stats for all sites
$allStats = $reporting->getAllSitesStats($dateRange);

// Get stats for specific tenant
$tenantStats = $reporting->getTenantStats($tenantId, $dateRange);
```

## Custom Site Resolver

Create a custom resolver for your specific needs:

```php
use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

class TeamBasedResolver implements SiteResolverInterface
{
    public function resolve(Request $request): ?Site
    {
        $user = $request->user();

        if (!$user || !$user->currentTeam) {
            return null;
        }

        return Site::where('team_id', $user->currentTeam->id)->first();
    }

    public function getPriority(): int
    {
        return 25;
    }
}
```

Register in config:

```php
'multi_tenant' => [
    'resolvers' => [
        \App\Analytics\TeamBasedResolver::class,
        // ... other resolvers
    ],
],
```

## Middleware

Apply site resolution middleware to routes:

```php
// routes/web.php
Route::middleware(['analytics.site'])->group(function () {
    Route::get('/analytics', AnalyticsController::class);
});
```

## Default Site

Configure a fallback site:

```php
'multi_tenant' => [
    'default_site_id' => env('ANALYTICS_DEFAULT_SITE_ID'),
],
```

## Database Considerations

### Indexing

Ensure proper indexes for multi-tenant queries:

```php
// In a migration
$table->index(['site_id', 'created_at']);
$table->index(['tenant_id', 'created_at']);
```

### Separate Databases

For large-scale deployments, consider separate databases:

```php
'local' => [
    'connection' => env('ANALYTICS_DB_CONNECTION', 'analytics'),
],
```

## Testing Multi-Tenancy

```php
use ArtisanPackUI\Analytics\Models\Site;

test('tracks to correct site', function () {
    $site = Site::factory()->create();

    $this->withHeader('X-API-Key', $site->api_key)
        ->post('/api/analytics/pageview', [
            'path' => '/test',
        ])
        ->assertOk();

    expect($site->pageViews()->count())->toBe(1);
});
```
