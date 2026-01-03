# Multi-Tenant SaaS Support

This document details the multi-tenant architecture for the analytics package, enabling both single-site deployments and multi-tenant SaaS applications.

## Table of Contents

1. [Overview](#overview)
2. [Site Model](#site-model)
3. [Tenant Resolution](#tenant-resolution)
4. [Data Isolation](#data-isolation)
5. [Per-Tenant Configuration](#per-tenant-configuration)
6. [Dashboard Integration](#dashboard-integration)
7. [Cross-Tenant Reporting](#cross-tenant-reporting)
8. [API Authentication](#api-authentication)

---

## Overview

The analytics package supports two deployment modes:

1. **Single-Site Mode**: Traditional single-application deployment where all analytics belong to one site
2. **Multi-Tenant Mode**: SaaS deployment where each tenant has isolated analytics data

### Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     Multi-Tenant Analytics                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │   Tenant A  │  │   Tenant B  │  │   Tenant C  │              │
│  │  site_id=1  │  │  site_id=2  │  │  site_id=3  │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
│         │                │                │                      │
│         ▼                ▼                ▼                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                   Tenant Resolver                         │   │
│  │         (Domain / Subdomain / Path / Header)              │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Analytics Scope                        │   │
│  │              (Automatic site_id filtering)                │   │
│  └──────────────────────────────────────────────────────────┘   │
│                              │                                   │
│         ┌────────────────────┼────────────────────┐             │
│         ▼                    ▼                    ▼             │
│  ┌────────────┐      ┌────────────┐      ┌────────────┐        │
│  │  Visitors  │      │  Sessions  │      │   Events   │        │
│  │ site_id=X  │      │ site_id=X  │      │ site_id=X  │        │
│  └────────────┘      └────────────┘      └────────────┘        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Site Model

### Database Schema

```sql
CREATE TABLE analytics_sites (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NOT NULL,

    -- Tenant relationship (polymorphic for flexibility)
    tenant_type VARCHAR(255) NULL,
    tenant_id BIGINT UNSIGNED NULL,

    -- Site settings
    settings JSON NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    currency VARCHAR(3) DEFAULT 'USD',

    -- Tracking configuration
    tracking_enabled BOOLEAN DEFAULT TRUE,
    public_dashboard BOOLEAN DEFAULT FALSE,

    -- API access
    api_key VARCHAR(64) NULL UNIQUE,
    api_key_last_used_at TIMESTAMP NULL,

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    INDEX idx_domain (domain),
    INDEX idx_tenant (tenant_type, tenant_id),
    INDEX idx_api_key (api_key)
);
```

### Migration

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('domain');

            // Polymorphic tenant relationship
            $table->nullableMorphs('tenant');

            // Site settings
            $table->json('settings')->nullable();
            $table->string('timezone', 50)->default('UTC');
            $table->string('currency', 3)->default('USD');

            // Tracking configuration
            $table->boolean('tracking_enabled')->default(true);
            $table->boolean('public_dashboard')->default(false);

            // API access
            $table->string('api_key', 64)->nullable()->unique();
            $table->timestamp('api_key_last_used_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_sites');
    }
};
```

### Eloquent Model

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Site extends Model
{
    use SoftDeletes;

    protected $table = 'analytics_sites';

    protected $fillable = [
        'uuid',
        'name',
        'domain',
        'tenant_type',
        'tenant_id',
        'settings',
        'timezone',
        'currency',
        'tracking_enabled',
        'public_dashboard',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'tracking_enabled' => 'boolean',
            'public_dashboard' => 'boolean',
            'api_key_last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (empty($site->uuid)) {
                $site->uuid = (string) Str::uuid();
            }
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class, 'site_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'site_id');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class, 'site_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'site_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class, 'site_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class, 'site_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class, 'site_id');
    }

    public function aggregates(): HasMany
    {
        return $this->hasMany(Aggregate::class, 'site_id');
    }

    // =========================================================================
    // API Key Management
    // =========================================================================

    public function generateApiKey(): string
    {
        $this->api_key = Str::random(64);
        $this->save();

        return $this->api_key;
    }

    public function rotateApiKey(): string
    {
        return $this->generateApiKey();
    }

    public function revokeApiKey(): void
    {
        $this->api_key = null;
        $this->save();
    }

    public function recordApiKeyUsage(): void
    {
        $this->api_key_last_used_at = now();
        $this->save();
    }

    // =========================================================================
    // Settings Helpers
    // =========================================================================

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    // =========================================================================
    // Tracking Script
    // =========================================================================

    public function getTrackingScript(): string
    {
        $endpoint = route('analytics.track');
        $siteId = $this->uuid;

        return <<<HTML
<script>
    (function() {
        window.APAnalytics = window.APAnalytics || {
            siteId: '{$siteId}',
            endpoint: '{$endpoint}'
        };
        var s = document.createElement('script');
        s.src = '/vendor/artisanpack/analytics/tracker.js';
        s.async = true;
        document.head.appendChild(s);
    })();
</script>
HTML;
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeForDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    public function scopeForTenant($query, Model $tenant)
    {
        return $query->where('tenant_type', get_class($tenant))
            ->where('tenant_id', $tenant->getKey());
    }

    public function scopeTrackingEnabled($query)
    {
        return $query->where('tracking_enabled', true);
    }

    public function scopePublicDashboard($query)
    {
        return $query->where('public_dashboard', true);
    }
}
```

---

## Tenant Resolution

The package supports multiple strategies for resolving the current tenant/site.

### Tenant Resolver Interface

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\MultiTenant;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

interface TenantResolverInterface
{
    /**
     * Resolve the current site from the request.
     */
    public function resolve(Request $request): ?Site;

    /**
     * Get the resolver priority (lower = higher priority).
     */
    public function priority(): int;
}
```

### Domain Resolver

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\MultiTenant\Resolvers;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantResolverInterface;
use Illuminate\Http\Request;

class DomainResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?Site
    {
        $host = $request->getHost();

        return Site::forDomain($host)
            ->trackingEnabled()
            ->first();
    }

    public function priority(): int
    {
        return 10;
    }
}
```

### Subdomain Resolver

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\MultiTenant\Resolvers;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantResolverInterface;
use Illuminate\Http\Request;

class SubdomainResolver implements TenantResolverInterface
{
    public function __construct(
        protected string $baseDomain
    ) {}

    public function resolve(Request $request): ?Site
    {
        $host = $request->getHost();

        // Extract subdomain from host
        $subdomain = str_replace('.' . $this->baseDomain, '', $host);

        if ($subdomain === $host) {
            // No subdomain found
            return null;
        }

        return Site::forDomain($subdomain . '.' . $this->baseDomain)
            ->trackingEnabled()
            ->first();
    }

    public function priority(): int
    {
        return 20;
    }
}
```

### Header Resolver (for API/Proxy scenarios)

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\MultiTenant\Resolvers;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantResolverInterface;
use Illuminate\Http\Request;

class HeaderResolver implements TenantResolverInterface
{
    public function __construct(
        protected string $headerName = 'X-Site-ID'
    ) {}

    public function resolve(Request $request): ?Site
    {
        $siteId = $request->header($this->headerName);

        if (empty($siteId)) {
            return null;
        }

        return Site::where('uuid', $siteId)
            ->trackingEnabled()
            ->first();
    }

    public function priority(): int
    {
        return 5; // High priority for explicit header
    }
}
```

### API Key Resolver

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\MultiTenant\Resolvers;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantResolverInterface;
use Illuminate\Http\Request;

class ApiKeyResolver implements TenantResolverInterface
{
    public function resolve(Request $request): ?Site
    {
        // Check Bearer token first
        $apiKey = $request->bearerToken();

        // Fall back to query parameter
        if (empty($apiKey)) {
            $apiKey = $request->query('api_key');
        }

        if (empty($apiKey)) {
            return null;
        }

        $site = Site::where('api_key', $apiKey)
            ->trackingEnabled()
            ->first();

        if ($site) {
            $site->recordApiKeyUsage();
        }

        return $site;
    }

    public function priority(): int
    {
        return 1; // Highest priority for API key
    }
}
```

### Tenant Manager

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\MultiTenant;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TenantManager
{
    protected ?Site $currentSite = null;
    protected Collection $resolvers;

    public function __construct()
    {
        $this->resolvers = collect();
    }

    /**
     * Register a tenant resolver.
     */
    public function addResolver(TenantResolverInterface $resolver): self
    {
        $this->resolvers->push($resolver);

        // Sort by priority
        $this->resolvers = $this->resolvers->sortBy(
            fn (TenantResolverInterface $r) => $r->priority()
        );

        return $this;
    }

    /**
     * Resolve the current site from the request.
     */
    public function resolve(Request $request): ?Site
    {
        if ($this->currentSite !== null) {
            return $this->currentSite;
        }

        foreach ($this->resolvers as $resolver) {
            $site = $resolver->resolve($request);

            if ($site !== null) {
                $this->currentSite = $site;
                return $site;
            }
        }

        // Fall back to default site in single-tenant mode
        if (config('analytics.multi_tenant.enabled') === false) {
            $this->currentSite = $this->getOrCreateDefaultSite();
            return $this->currentSite;
        }

        return null;
    }

    /**
     * Get the current site.
     */
    public function current(): ?Site
    {
        return $this->currentSite;
    }

    /**
     * Set the current site manually.
     */
    public function setCurrent(?Site $site): void
    {
        $this->currentSite = $site;
    }

    /**
     * Check if we're in multi-tenant mode.
     */
    public function isMultiTenant(): bool
    {
        return config('analytics.multi_tenant.enabled', false);
    }

    /**
     * Get or create the default site for single-tenant mode.
     */
    protected function getOrCreateDefaultSite(): Site
    {
        return Site::firstOrCreate(
            ['domain' => config('app.url')],
            [
                'name' => config('app.name', 'Default Site'),
                'timezone' => config('app.timezone', 'UTC'),
            ]
        );
    }

    /**
     * Run a callback within a specific site context.
     */
    public function forSite(Site $site, callable $callback): mixed
    {
        $previousSite = $this->currentSite;
        $this->currentSite = $site;

        try {
            return $callback($site);
        } finally {
            $this->currentSite = $previousSite;
        }
    }
}
```

### Tenant Middleware

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Http\Middleware;

use ArtisanPackUI\Analytics\MultiTenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $site = $this->tenantManager->resolve($request);

        if ($site === null && $this->tenantManager->isMultiTenant()) {
            abort(404, __('Site not found'));
        }

        // Share site with views
        view()->share('analyticsSite', $site);

        return $next($request);
    }
}
```

---

## Data Isolation

All analytics models automatically scope queries by site_id when a current site is set.

### Analytics Scope Trait

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Traits;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSite
{
    protected static bool $disableSiteScope = false;

    public static function bootBelongsToSite(): void
    {
        // Automatically scope all queries to the current site
        static::addGlobalScope('site', function (Builder $builder) {
            if (static::$disableSiteScope) {
                return;
            }

            $tenantManager = app(TenantManager::class);
            $site = $tenantManager->current();

            if ($site !== null) {
                $builder->where('site_id', $site->id);
            }
        });

        // Automatically set site_id when creating
        static::creating(function (Model $model) {
            if (empty($model->site_id)) {
                $tenantManager = app(TenantManager::class);
                $site = $tenantManager->current();

                if ($site !== null) {
                    $model->site_id = $site->id;
                }
            }
        });
    }

    /**
     * Relationship to the site.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Scope to a specific site.
     */
    public function scopeForSite(Builder $query, Site|int $site): Builder
    {
        $siteId = $site instanceof Site ? $site->id : $site;

        return $query->withoutGlobalScope('site')
            ->where('site_id', $siteId);
    }

    /**
     * Temporarily disable site scope.
     */
    public static function withoutSiteScope(callable $callback): mixed
    {
        static::$disableSiteScope = true;

        try {
            return $callback();
        } finally {
            static::$disableSiteScope = false;
        }
    }

    /**
     * Query across all sites (for admin/reporting).
     */
    public static function allSites(): Builder
    {
        return static::query()->withoutGlobalScope('site');
    }
}
```

### Model Implementation

All analytics models use the `BelongsToSite` trait:

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Models;

use ArtisanPackUI\Analytics\Traits\BelongsToSite;
use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    use BelongsToSite;

    protected $table = 'analytics_page_views';

    // ... rest of model implementation
}
```

### Query Examples

```php
<?php

use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantManager;

// Automatic scoping - queries only current site's data
$pageViews = PageView::where('created_at', '>=', now()->subDays(7))->get();

// Query a specific site
$site = Site::find(1);
$pageViews = PageView::forSite($site)->get();

// Query across all sites (admin only)
$allPageViews = PageView::allSites()->get();

// Temporarily disable scope
$total = PageView::withoutSiteScope(function () {
    return PageView::count();
});

// Run code in a specific site context
$tenantManager = app(TenantManager::class);
$tenantManager->forSite($site, function ($site) {
    // All queries here are scoped to $site
    $views = PageView::count();
    $visitors = Visitor::count();
});
```

---

## Per-Tenant Configuration

Each site can have its own configuration settings.

### Site Settings Schema

```php
<?php

// Default site settings structure
$defaultSettings = [
    // Tracking settings
    'tracking' => [
        'anonymize_ip' => true,
        'track_bots' => false,
        'track_logged_in' => true,
        'excluded_ips' => [],
        'excluded_paths' => [],
    ],

    // Dashboard settings
    'dashboard' => [
        'default_period' => '7d',
        'timezone' => 'UTC',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
    ],

    // Privacy settings
    'privacy' => [
        'consent_required' => true,
        'cookie_lifetime' => 365,
        'data_retention_days' => 730,
    ],

    // Feature toggles
    'features' => [
        'events' => true,
        'goals' => true,
        'realtime' => true,
        'heatmaps' => false,
    ],

    // Provider settings
    'providers' => [
        'enabled' => ['local'],
        'google_analytics' => [
            'measurement_id' => null,
        ],
        'plausible' => [
            'domain' => null,
        ],
    ],
];
```

### Site Settings Service

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantManager;

class SiteSettingsService
{
    protected array $defaults;

    public function __construct(
        protected TenantManager $tenantManager
    ) {
        $this->defaults = config('analytics.site_defaults', []);
    }

    /**
     * Get a setting value for the current site.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $site = $this->tenantManager->current();

        if ($site === null) {
            return $this->getDefault($key, $default);
        }

        return $site->getSetting($key) ?? $this->getDefault($key, $default);
    }

    /**
     * Set a setting value for the current site.
     */
    public function set(string $key, mixed $value): void
    {
        $site = $this->tenantManager->current();

        if ($site === null) {
            throw new \RuntimeException(__('No current site to save settings to'));
        }

        $site->setSetting($key, $value);
    }

    /**
     * Get all settings for the current site, merged with defaults.
     */
    public function all(): array
    {
        $site = $this->tenantManager->current();
        $settings = $site?->settings ?? [];

        return array_replace_recursive($this->defaults, $settings);
    }

    /**
     * Check if a feature is enabled for the current site.
     */
    public function featureEnabled(string $feature): bool
    {
        return (bool) $this->get("features.{$feature}", false);
    }

    /**
     * Get the default value for a setting.
     */
    protected function getDefault(string $key, mixed $fallback = null): mixed
    {
        return data_get($this->defaults, $key, $fallback);
    }
}
```

### Usage in Components

```php
<?php

use ArtisanPackUI\Analytics\Services\SiteSettingsService;

class AnalyticsDashboard extends Component
{
    public function mount(SiteSettingsService $settings): void
    {
        $this->defaultPeriod = $settings->get('dashboard.default_period', '7d');
        $this->showRealtime = $settings->featureEnabled('realtime');
        $this->showGoals = $settings->featureEnabled('goals');
    }
}
```

---

## Dashboard Integration

### Multi-Site Dashboard Selector

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Livewire;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantManager;
use Illuminate\Support\Collection;
use Livewire\Component;

class SiteSelector extends Component
{
    public ?int $selectedSiteId = null;
    public bool $showDropdown = false;

    public function mount(): void
    {
        $tenantManager = app(TenantManager::class);
        $currentSite = $tenantManager->current();

        $this->selectedSiteId = $currentSite?->id;
    }

    public function selectSite(int $siteId): void
    {
        $site = Site::find($siteId);

        if ($site && $this->canAccessSite($site)) {
            $this->selectedSiteId = $siteId;
            $this->showDropdown = false;

            $this->dispatch('site-changed', siteId: $siteId);
        }
    }

    public function getAccessibleSitesProperty(): Collection
    {
        // Override this method based on your authorization logic
        return Site::trackingEnabled()
            ->orderBy('name')
            ->get();
    }

    public function getSelectedSiteProperty(): ?Site
    {
        return $this->selectedSiteId
            ? Site::find($this->selectedSiteId)
            : null;
    }

    protected function canAccessSite(Site $site): bool
    {
        // Implement your authorization logic
        return true;
    }

    public function render()
    {
        return view('analytics::livewire.site-selector');
    }
}
```

### Site Selector View

```blade
{{-- resources/views/livewire/site-selector.blade.php --}}
<div class="relative" x-data="{ open: @entangle('showDropdown') }">
    <button
        @click="open = !open"
        type="button"
        class="flex items-center gap-2 px-4 py-2 bg-white border rounded-lg shadow-sm hover:bg-gray-50"
    >
        @if ($this->selectedSite)
            <span class="font-medium">{{ $this->selectedSite->name }}</span>
            <span class="text-gray-500 text-sm">{{ $this->selectedSite->domain }}</span>
        @else
            <span class="text-gray-500">{{ __('Select a site') }}</span>
        @endif

        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        x-transition
        class="absolute z-50 mt-2 w-72 bg-white border rounded-lg shadow-lg"
    >
        <div class="p-2">
            @forelse ($this->accessibleSites as $site)
                <button
                    wire:click="selectSite({{ $site->id }})"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 {{ $selectedSiteId === $site->id ? 'bg-primary-50 text-primary-700' : '' }}"
                >
                    <div class="flex-1 text-left">
                        <div class="font-medium">{{ $site->name }}</div>
                        <div class="text-sm text-gray-500">{{ $site->domain }}</div>
                    </div>

                    @if ($selectedSiteId === $site->id)
                        <svg class="w-5 h-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    @endif
                </button>
            @empty
                <p class="px-3 py-2 text-gray-500">{{ __('No sites available') }}</p>
            @endforelse
        </div>
    </div>
</div>
```

### Site-Aware Dashboard

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Livewire;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\MultiTenant\TenantManager;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Livewire\Attributes\On;
use Livewire\Component;

class MultiTenantDashboard extends Component
{
    public ?int $siteId = null;
    public string $period = '7d';

    protected AnalyticsQuery $query;
    protected TenantManager $tenantManager;

    public function boot(AnalyticsQuery $query, TenantManager $tenantManager): void
    {
        $this->query = $query;
        $this->tenantManager = $tenantManager;
    }

    public function mount(): void
    {
        $currentSite = $this->tenantManager->current();
        $this->siteId = $currentSite?->id;
    }

    #[On('site-changed')]
    public function onSiteChanged(int $siteId): void
    {
        $this->siteId = $siteId;

        // Update the tenant manager context
        $site = Site::find($siteId);
        if ($site) {
            $this->tenantManager->setCurrent($site);
        }
    }

    public function getSiteProperty(): ?Site
    {
        return $this->siteId ? Site::find($this->siteId) : null;
    }

    public function render()
    {
        // Query methods automatically use current site context
        return view('analytics::livewire.multi-tenant-dashboard', [
            'stats' => $this->site ? $this->query->getStats($this->period) : null,
            'showSiteSelector' => $this->tenantManager->isMultiTenant(),
        ]);
    }
}
```

---

## Cross-Tenant Reporting

For platform admins who need to view analytics across all tenants.

### Cross-Tenant Query Service

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Aggregate;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CrossTenantReporting
{
    /**
     * Get aggregated stats across all sites.
     */
    public function getPlatformStats(string $period = '30d'): array
    {
        $startDate = $this->getStartDate($period);

        return [
            'total_sites' => Site::count(),
            'active_sites' => Site::trackingEnabled()->count(),
            'total_visitors' => Visitor::allSites()
                ->where('created_at', '>=', $startDate)
                ->count(),
            'total_sessions' => Session::allSites()
                ->where('started_at', '>=', $startDate)
                ->count(),
            'total_page_views' => PageView::allSites()
                ->where('viewed_at', '>=', $startDate)
                ->count(),
        ];
    }

    /**
     * Get top sites by traffic.
     */
    public function getTopSitesByTraffic(int $limit = 10, string $period = '30d'): Collection
    {
        $startDate = $this->getStartDate($period);

        return Site::withCount([
            'pageViews as page_view_count' => function ($query) use ($startDate) {
                $query->where('viewed_at', '>=', $startDate);
            },
            'visitors as visitor_count' => function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            },
        ])
        ->orderByDesc('page_view_count')
        ->limit($limit)
        ->get();
    }

    /**
     * Get sites with growth comparison.
     */
    public function getSitesWithGrowth(string $period = '30d'): Collection
    {
        $currentStart = $this->getStartDate($period);
        $previousStart = $this->getStartDate($period . '_previous');
        $previousEnd = $currentStart;

        return Site::get()->map(function (Site $site) use ($currentStart, $previousStart, $previousEnd) {
            $currentViews = PageView::forSite($site)
                ->where('viewed_at', '>=', $currentStart)
                ->count();

            $previousViews = PageView::forSite($site)
                ->whereBetween('viewed_at', [$previousStart, $previousEnd])
                ->count();

            $growth = $previousViews > 0
                ? (($currentViews - $previousViews) / $previousViews) * 100
                : 0;

            return [
                'site' => $site,
                'current_views' => $currentViews,
                'previous_views' => $previousViews,
                'growth_percentage' => round($growth, 2),
            ];
        })->sortByDesc('current_views');
    }

    /**
     * Get aggregated data by site for a metric.
     */
    public function getAggregatesBySite(
        string $metric,
        string $period = '30d'
    ): Collection {
        $startDate = $this->getStartDate($period);

        return Aggregate::allSites()
            ->selectRaw('site_id, SUM(value) as total')
            ->where('metric', $metric)
            ->where('date', '>=', $startDate->format('Y-m-d'))
            ->groupBy('site_id')
            ->with('site')
            ->get()
            ->sortByDesc('total');
    }

    /**
     * Export platform-wide report.
     */
    public function exportPlatformReport(string $period = '30d'): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'period' => $period,
            'platform_stats' => $this->getPlatformStats($period),
            'top_sites' => $this->getTopSitesByTraffic(50, $period)
                ->map(fn ($site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'domain' => $site->domain,
                    'page_views' => $site->page_view_count,
                    'visitors' => $site->visitor_count,
                ])
                ->toArray(),
            'sites_with_growth' => $this->getSitesWithGrowth($period)
                ->take(50)
                ->values()
                ->toArray(),
        ];
    }

    protected function getStartDate(string $period): Carbon
    {
        return match ($period) {
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '365d', '1y' => now()->subYear(),
            '7d_previous' => now()->subDays(14),
            '30d_previous' => now()->subDays(60),
            '90d_previous' => now()->subDays(180),
            default => now()->subDays(30),
        };
    }
}
```

### Admin Dashboard Component

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Livewire\Admin;

use ArtisanPackUI\Analytics\Services\CrossTenantReporting;
use Livewire\Component;

class PlatformDashboard extends Component
{
    public string $period = '30d';

    public function render(CrossTenantReporting $reporting)
    {
        return view('analytics::livewire.admin.platform-dashboard', [
            'stats' => $reporting->getPlatformStats($this->period),
            'topSites' => $reporting->getTopSitesByTraffic(10, $this->period),
            'sitesWithGrowth' => $reporting->getSitesWithGrowth($this->period)->take(10),
        ]);
    }
}
```

---

## API Authentication

### API Key Authentication Guard

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Auth;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class ApiKeyGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;
    protected ?Site $site = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function user(): ?Site
    {
        if ($this->site !== null) {
            return $this->site;
        }

        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return null;
        }

        $this->site = Site::where('api_key', $apiKey)
            ->trackingEnabled()
            ->first();

        if ($this->site) {
            $this->site->recordApiKeyUsage();
        }

        return $this->site;
    }

    public function validate(array $credentials = []): bool
    {
        $apiKey = $credentials['api_key'] ?? null;

        if (empty($apiKey)) {
            return false;
        }

        return Site::where('api_key', $apiKey)
            ->trackingEnabled()
            ->exists();
    }

    protected function getApiKey(): ?string
    {
        // Check Bearer token
        $apiKey = $this->request->bearerToken();

        if (!empty($apiKey)) {
            return $apiKey;
        }

        // Check X-API-Key header
        $apiKey = $this->request->header('X-API-Key');

        if (!empty($apiKey)) {
            return $apiKey;
        }

        // Check query parameter (not recommended for production)
        return $this->request->query('api_key');
    }
}
```

### API Authentication Middleware

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\Analytics\Http\Middleware;

use ArtisanPackUI\Analytics\MultiTenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithApiKey
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $site = auth('analytics-api')->user();

        if ($site === null) {
            return response()->json([
                'error' => __('Unauthorized'),
                'message' => __('Invalid or missing API key'),
            ], 401);
        }

        // Set the current site context
        $this->tenantManager->setCurrent($site);

        return $next($request);
    }
}
```

### API Routes

```php
<?php

// routes/api.php

use ArtisanPackUI\Analytics\Http\Controllers\Api\AnalyticsApiController;
use ArtisanPackUI\Analytics\Http\Middleware\AuthenticateWithApiKey;

Route::prefix('analytics/v1')
    ->middleware(AuthenticateWithApiKey::class)
    ->group(function () {
        // Tracking endpoints
        Route::post('/track/pageview', [AnalyticsApiController::class, 'trackPageView']);
        Route::post('/track/event', [AnalyticsApiController::class, 'trackEvent']);

        // Query endpoints
        Route::get('/stats', [AnalyticsApiController::class, 'stats']);
        Route::get('/visitors', [AnalyticsApiController::class, 'visitors']);
        Route::get('/pages', [AnalyticsApiController::class, 'pages']);
        Route::get('/events', [AnalyticsApiController::class, 'events']);
        Route::get('/goals', [AnalyticsApiController::class, 'goals']);

        // Site management
        Route::get('/site', [AnalyticsApiController::class, 'siteInfo']);
        Route::put('/site/settings', [AnalyticsApiController::class, 'updateSettings']);
    });
```

---

## Configuration

### Multi-Tenant Configuration

```php
<?php

// config/analytics.php

return [
    // ... other config options

    'multi_tenant' => [
        // Enable multi-tenant mode
        'enabled' => env('ANALYTICS_MULTI_TENANT', false),

        // Tenant resolvers to use (in order of priority)
        'resolvers' => [
            \ArtisanPackUI\Analytics\MultiTenant\Resolvers\ApiKeyResolver::class,
            \ArtisanPackUI\Analytics\MultiTenant\Resolvers\HeaderResolver::class,
            \ArtisanPackUI\Analytics\MultiTenant\Resolvers\SubdomainResolver::class,
            \ArtisanPackUI\Analytics\MultiTenant\Resolvers\DomainResolver::class,
        ],

        // Base domain for subdomain resolution
        'base_domain' => env('ANALYTICS_BASE_DOMAIN', 'example.com'),

        // Header name for header-based resolution
        'site_header' => env('ANALYTICS_SITE_HEADER', 'X-Site-ID'),
    ],

    // Default settings for new sites
    'site_defaults' => [
        'tracking' => [
            'anonymize_ip' => true,
            'track_bots' => false,
            'track_logged_in' => true,
            'excluded_ips' => [],
            'excluded_paths' => [],
        ],
        'dashboard' => [
            'default_period' => '7d',
            'timezone' => 'UTC',
        ],
        'privacy' => [
            'consent_required' => true,
            'cookie_lifetime' => 365,
            'data_retention_days' => 730,
        ],
        'features' => [
            'events' => true,
            'goals' => true,
            'realtime' => true,
            'heatmaps' => false,
        ],
    ],
];
```

---

## Summary

The multi-tenant architecture provides:

1. **Flexible Tenant Resolution**: Support for domain, subdomain, header, and API key-based tenant identification
2. **Automatic Data Isolation**: Global scopes ensure all queries are automatically filtered by site
3. **Per-Tenant Configuration**: Each site can have its own settings and feature toggles
4. **Cross-Tenant Reporting**: Platform admins can view aggregate data across all sites
5. **API Authentication**: Secure API access with per-site API keys
6. **Site Selector**: UI component for switching between sites in multi-tenant deployments

This architecture supports both single-site deployments and complex SaaS scenarios with complete data isolation between tenants.
