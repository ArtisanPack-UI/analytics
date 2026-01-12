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

## SiteResolverInterface

Interface for resolving the current site in multi-tenant setups.

### Definition

```php
namespace ArtisanPackUI\Analytics\Contracts;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

interface SiteResolverInterface
{
    /**
     * Resolve the site from the request.
     */
    public function resolve(Request $request): ?Site;

    /**
     * Get the resolver priority (lower runs first).
     */
    public function getPriority(): int;
}
```

### Built-in Resolvers

| Resolver | Priority | Resolution Method |
|----------|----------|-------------------|
| `ApiKeyResolver` | 10 | API key in header or query |
| `HeaderResolver` | 50 | Custom header (X-Site-ID) |
| `SubdomainResolver` | 90 | Subdomain extraction |
| `DomainResolver` | 100 | Full domain matching |

### Implementation Example

```php
use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

class TenantIdResolver implements SiteResolverInterface
{
    public function resolve(Request $request): ?Site
    {
        // Get tenant from authenticated user
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return null;
        }

        return Site::where('tenant_id', $user->tenant_id)->first();
    }

    public function getPriority(): int
    {
        return 20; // Run early, after API key
    }
}
```

### Registering Custom Resolvers

```php
// config/artisanpack/analytics.php
'multi_tenant' => [
    'resolvers' => [
        \ArtisanPackUI\Analytics\Resolvers\ApiKeyResolver::class,
        \App\Analytics\TenantIdResolver::class, // Your custom resolver
        \ArtisanPackUI\Analytics\Resolvers\DomainResolver::class,
    ],
],
```

---

## TenantResolverInterface

Legacy interface for tenant resolution.

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
        private SiteResolverInterface $resolver,
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
