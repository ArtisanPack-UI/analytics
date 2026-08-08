---
title: Contracts
---

# Contracts

ArtisanPack UI Analytics provides interfaces (contracts) that allow you to create custom implementations.

## AnalyticsProviderInterface

Interface for analytics providers.

### Definition

```php
namespace ArtisanPackUI\Analytics\Contracts;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;

interface AnalyticsProviderInterface
{
    /**
     * Track a page view.
     */
    public function trackPageView(PageViewData $data): void;

    /**
     * Track a custom event.
     */
    public function trackEvent(EventData $data): void;

    /**
     * Check if the provider is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;
}
```

### Implementation Example

```php
use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;

class MixpanelProvider implements AnalyticsProviderInterface
{
    public function __construct(
        private MixpanelClient $client,
    ) {}

    public function trackPageView(PageViewData $data): void
    {
        $this->client->track('Page View', [
            'path' => $data->path,
            'title' => $data->title,
        ]);
    }

    public function trackEvent(EventData $data): void
    {
        $this->client->track($data->name, $data->properties ?? []);
    }

    public function isEnabled(): bool
    {
        return config('services.mixpanel.enabled', false);
    }

    public function getName(): string
    {
        return 'mixpanel';
    }
}
```

### Registering Custom Providers

```php
// In a service provider
use ArtisanPackUI\Analytics\Facades\Analytics;

public function boot(): void
{
    Analytics::extend('mixpanel', function ($app) {
        return new MixpanelProvider(
            $app->make(MixpanelClient::class)
        );
    });
}
```

Then enable in config:

```php
// config/artisanpack/analytics.php
'active_providers' => ['local', 'mixpanel'],
```

---

## SiteResolver (core)

Since 1.5.0 site resolution is a single ecosystem-wide contract owned by
`artisanpack-ui/core`, so analytics and every sibling package resolve one site
from one configuration.

### Definition

```php
namespace ArtisanPackUI\Core\Contracts;

interface SiteResolver
{
    /**
     * The identifier of the site currently in context, or null for none.
     */
    public function currentSiteId(): int|string|null;
}
```

It is keyed on the identifier rather than a model, because core cannot depend on
any package's `Site`; and it takes no `Request`, because a resolver that
requires one is unusable from the console commands and queue workers where
per-site iteration happens. A resolver that wants the request injects it.

### Built-in Resolvers

Asked in the order they are listed in configuration; the first non-null answer
wins.

| Resolver | Resolution Method |
|----------|-------------------|
| `ApiKeyResolver` | API key in header or query |
| `HeaderResolver` | Custom header (X-Site-ID) |
| `SubdomainResolver` | Subdomain extraction |
| `DomainResolver` | Full domain matching |

### Implementation Example

```php
use ArtisanPackUI\Core\Contracts\SiteResolver;
use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

class TenantIdResolver implements SiteResolver
{
    public function __construct(private Request $request)
    {
    }

    public function currentSiteId(): int|string|null
    {
        $user = $this->request->user();

        if (!$user || !$user->tenant_id) {
            return null;
        }

        return Site::where('tenant_id', $user->tenant_id)->value('id');
    }
}
```

### SiteResolverInterface (deprecated)

`ArtisanPackUI\Analytics\Contracts\SiteResolverInterface` now extends the core
contract and keeps its `resolve(Request): ?Site` and `priority()` methods for
existing implementations. Core calls only `currentSiteId()`; `resolve()` is
called by the compatibility adapter,
`ArtisanPackUI\Analytics\Resolvers\AbstractSiteResolver`, whose
`currentSiteId()` delegates to it — extend that class to keep a request-shaped
resolver working. `priority()` is called by nothing. Both are removed in 2.0.

### Registering Custom Resolvers

```php
// config/artisanpack.php
'core' => [
    'multi_tenant' => [
        'resolvers' => [
            \ArtisanPackUI\Analytics\Resolvers\ApiKeyResolver::class,
            \App\Analytics\TenantIdResolver::class, // Your custom resolver
            \ArtisanPackUI\Analytics\Resolvers\DomainResolver::class,
        ],
    ],
],
```

---

## TenantResolverInterface

Legacy interface for tenant resolution. **Deprecated in 1.5.0.** It describes a
*tenant*, which is not always a site — it carries its own column name — so it
survived the move to the shared contract rather than being folded into it. It is
consulted only by the `TenantResolver` middleware, and only when nothing has put
a site in the shared context, so it cannot decide what queries are scoped to.
Where a tenant is a site, implement `ArtisanPackUI\Core\Contracts\SiteResolver`
instead.

### Definition

```php
namespace ArtisanPackUI\Analytics\Contracts;

interface TenantResolverInterface
{
    /**
     * Get the current tenant ID.
     */
    public function getCurrentTenantId(): int|string|null;

    /**
     * Set the current tenant ID.
     */
    public function setCurrentTenantId(int|string $tenantId): void;
}
```

### Implementation Example

```php
use ArtisanPackUI\Analytics\Contracts\TenantResolverInterface;

class SessionTenantResolver implements TenantResolverInterface
{
    public function getCurrentTenantId(): int|string|null
    {
        return session('current_tenant_id');
    }

    public function setCurrentTenantId(int|string $tenantId): void
    {
        session(['current_tenant_id' => $tenantId]);
    }
}
```

### Registration

```php
// config/artisanpack/analytics.php
'multi_tenant' => [
    'resolver' => \App\Analytics\SessionTenantResolver::class,
],
```

---

## Creating Custom Contracts

You can extend the package with your own contracts:

```php
namespace App\Analytics\Contracts;

use ArtisanPackUI\Analytics\Models\PageView;

interface PageViewEnricherInterface
{
    /**
     * Enrich a page view with additional data.
     */
    public function enrich(PageView $pageView): PageView;
}
```

Implementation:

```php
class GeoEnricher implements PageViewEnricherInterface
{
    public function __construct(
        private GeoIpService $geoService,
    ) {}

    public function enrich(PageView $pageView): PageView
    {
        $location = $this->geoService->lookup($pageView->ip_address);

        $pageView->custom_data = array_merge(
            $pageView->custom_data ?? [],
            ['geo' => $location]
        );

        return $pageView;
    }
}
```

Register in service provider:

```php
$this->app->bind(
    PageViewEnricherInterface::class,
    GeoEnricher::class
);
```

---

## Dependency Injection

All contracts can be injected into your classes:

```php
class MyService
{
    public function __construct(
        private AnalyticsProviderInterface $provider,
        private SiteContext $siteContext,
    ) {}

    public function doSomething(): void
    {
        if ($this->provider->isEnabled()) {
            // Use provider
        }
    }
}
```

## Testing with Contracts

Use contracts for easy mocking in tests:

```php
use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;

test('tracks page view', function () {
    $provider = Mockery::mock(AnalyticsProviderInterface::class);
    $provider->shouldReceive('trackPageView')->once();

    app()->instance(AnalyticsProviderInterface::class, $provider);

    // Test code that triggers page view tracking
});
```
