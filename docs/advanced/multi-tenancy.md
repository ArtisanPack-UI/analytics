---
title: Multi-Tenancy
---

# Multi-Tenancy

ArtisanPack UI Analytics provides comprehensive multi-tenant support for SaaS and multi-site applications.

Since 1.5.0, *which site a request is for* is decided once for the whole
ArtisanPack UI ecosystem, by `artisanpack-ui/core`. Analytics reads that answer
rather than resolving its own. Before this, each package that scoped data by
site kept its own resolver and its own configuration, so an application
installing two of them could resolve to site 2 in one and site 1 in the other,
in the same request, with nothing to signal it.

## Enabling Multi-Tenancy

```php
// .env
ARTISANPACK_MULTI_TENANT_ENABLED=true
```

```php
// config/artisanpack.php
'core' => [
    'multi_tenant' => [
        'enabled' => env('ARTISANPACK_MULTI_TENANT_ENABLED', false),
    ],
],
```

The pre-1.5 switch, `ANALYTICS_MULTI_TENANT` / `artisanpack.analytics.multi_tenant.enabled`,
still works: it enables analytics scoping and switches the shared flag on too,
so an upgrade does not silently pool every site's visits into one dashboard.
It is deprecated and will be removed in 2.0.

## Site Resolution

Sites are resolved from incoming requests using configurable resolvers, listed
under the shared configuration key. Resolvers are asked in the order they are
listed, and the first non-null answer wins — a chain assembled from several
packages' resolvers cannot be ordered by a priority number only one of those
packages knows about.

### Available Resolvers

| Resolver | Method |
|----------|--------|
| `ApiKeyResolver` | API key in header/query |
| `HeaderResolver` | X-Site-ID header |
| `SubdomainResolver` | Subdomain extraction |
| `DomainResolver` | Full domain match |

The resolvers this package ships answer from the request, so they resolve to
`null` in console commands and queue workers. Pin a site explicitly there — see
[Working across sites](#working-across-sites).

### Trust boundaries

The resolver list is now ecosystem-wide, so a resolver's trust assumptions apply
to every package that scopes data by site, not only to analytics. Two of the
shipped resolvers take the site from client-controlled input:

- `HeaderResolver` believes whatever `X-Site-ID` the caller sends.
- `ApiKeyResolver` is authenticated, but `allow_query_api_key` moves the
  credential into the query string, where it lands in access logs.

Both were only ever as trusted as the routes they ran on. Now they decide the
site for every package, so list them only where that is what you want — typically
on authenticated ingest and API routes — and prefer resolvers keyed on something
the caller cannot choose (the domain, the authenticated user) for routes serving
site-scoped data.

### Pinning per request

Resolvers are asked afresh on every call, by design: a worker looping over sites
changes the answer within one process, and a cached answer would scope the later
iterations to the wrong site. That means an unpinned request re-runs the chain
for every scoped query.

Apply the `analytics.site` middleware to route groups that query analytics data.
It resolves once and pins the result for the rest of the request:

```php
Route::middleware(['web', 'auth', 'analytics.site'])->group(function () {
    // ...
});
```

### Configure Resolvers

```php
// config/artisanpack.php
'core' => [
    'multi_tenant' => [
        'resolvers' => [
            \ArtisanPackUI\Analytics\Resolvers\ApiKeyResolver::class,
            \ArtisanPackUI\Analytics\Resolvers\HeaderResolver::class,
            \ArtisanPackUI\Analytics\Resolvers\SubdomainResolver::class,
            \ArtisanPackUI\Analytics\Resolvers\DomainResolver::class,
            // Answers from artisanpack-ui/cms-framework, when installed.
            \ArtisanPackUI\Core\MultiTenancy\HookSiteResolver::class,
        ],
    ],
],
```

A list still configured under `artisanpack.analytics.multi_tenant.resolvers` is
prepended to the shared list at boot while the deprecated analytics flag is on,
so resolution order survives the upgrade. Move the list to the core key and
empty the analytics one once you have migrated.

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
    'is_active' => true,
    'tenant_id' => $tenant->id, // Optional
    'settings' => [
        'tracking' => [
            'enabled' => true,
            'anonymize_ip' => true,
        ],
    ],
]);

// Generate and store the API key (creates a 64-character key and hashes it)
$apiKey = $site->generateApiKey();
// Save the plain-text API key securely - it won't be retrievable later
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
        $site = $tenantManager->current();

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

## Working across sites

A console command or queue worker has no request to resolve from, so pin the
site you want. The pin is shared, so every package scopes to it, not just
analytics:

```php
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\TenantManager;

$tenantManager = app(TenantManager::class);

foreach (Site::all() as $site) {
    $tenantManager->forSite($site, function () use ($site) {
        // Everything in here — analytics and any other ArtisanPack UI
        // package — is scoped to $site. The previous context is restored
        // afterwards, including if this throws.
    });
}

// Run something against every site's data at once:
$tenantManager->withoutSite(fn () => PageView::count());
```

`forSite()` also accepts a bare identifier, so a job that only carries a site ID
does not have to load the model first.

## Custom Site Resolver

Implement the ecosystem's shared contract. It is keyed on the site identifier
rather than a `Site` model, because core cannot depend on this package's models
— analytics looks the model up from the identifier you return:

```php
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use Illuminate\Http\Request;

class TeamBasedResolver implements SiteResolver
{
    public function __construct(private Request $request)
    {
    }

    public function currentSiteId(): int|string|null
    {
        $user = $this->request->user();

        if (!$user || !$user->currentTeam) {
            return null;
        }

        return Site::where('team_id', $user->currentTeam->id)->value('id');
    }
}
```

Note the resolver asks for the request in its constructor rather than taking one
as a parameter: a resolver that *requires* a request is unusable from the
console commands and queue workers where per-site iteration happens.

Register in config:

```php
'core' => [
    'multi_tenant' => [
        'resolvers' => [
            \App\Analytics\TeamBasedResolver::class,
            // ... other resolvers
        ],
    ],
],
```

A class listed here that does not exist, or does not implement `SiteResolver`,
raises `ArtisanPackUI\Core\Exceptions\SiteResolutionException` when the chain
is built. Dropping it quietly would leave an application that reads as
multi-site running with every query unscoped.

### Migrating a pre-1.5 resolver

A resolver implementing `SiteResolverInterface` keeps working if you extend
`ArtisanPackUI\Analytics\Resolvers\AbstractSiteResolver`, which implements
`currentSiteId()` in terms of your existing `resolve(Request): ?Site`:

```php
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Resolvers\AbstractSiteResolver;
use Illuminate\Http\Request;

class TeamBasedResolver extends AbstractSiteResolver
{
    public function resolve(Request $request): ?Site
    {
        // unchanged
    }
}
```

`AbstractSiteResolver::currentSiteId()` is what core calls; it reads the request
from the container and hands it to your `resolve()`, returning the site's ID.

`SiteResolverInterface` and its `priority()` method are deprecated and will be
removed in 2.0.

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
