# Provider Interface

**Purpose:** Define the extensible provider system for integrating external analytics services
**Last Updated:** January 3, 2026

---

## Overview

The provider interface enables integration with external analytics services (Google Analytics 4, Plausible, etc.) while maintaining the local analytics engine as the primary data source. This allows:

1. **Hybrid Deployments** - Use local tracking for privacy-sensitive data, external services for additional features
2. **Easy Migration** - Switch providers without changing application code
3. **Multiple Providers** - Send data to multiple services simultaneously
4. **Custom Providers** - Create custom integrations for any analytics service

---

## Provider Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        AnalyticsManager                              │
│  • Manages all registered providers                                  │
│  • Routes tracking calls to enabled providers                        │
│  • Handles provider lifecycle                                        │
└─────────────────────────────────────────────────────────────────────┘
                                   │
         ┌─────────────────────────┼─────────────────────────┐
         ▼                         ▼                         ▼
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│ LocalProvider   │      │   GA4Provider   │      │PlausibleProvider│
│ (Default)       │      │                 │      │                 │
├─────────────────┤      ├─────────────────┤      ├─────────────────┤
│ trackPageView() │      │ trackPageView() │      │ trackPageView() │
│ trackEvent()    │      │ trackEvent()    │      │ trackEvent()    │
│ isEnabled()     │      │ isEnabled()     │      │ isEnabled()     │
└─────────────────┘      └─────────────────┘      └─────────────────┘
         │                         │                         │
         ▼                         ▼                         ▼
┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
│   Local DB      │      │  Google API     │      │  Plausible API  │
└─────────────────┘      └─────────────────┘      └─────────────────┘
```

---

## Provider Interface

```php
// src/Contracts/AnalyticsProviderInterface.php

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
     * Check if this provider is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Get the provider's JavaScript tracking code (if any).
     */
    public function getTrackingScript(): ?string;

    /**
     * Check if this provider supports server-side tracking.
     */
    public function supportsServerSideTracking(): bool;

    /**
     * Check if this provider supports client-side tracking.
     */
    public function supportsClientSideTracking(): bool;
}
```

---

## Abstract Base Provider

```php
// src/Providers/AbstractAnalyticsProvider.php

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractAnalyticsProvider implements AnalyticsProviderInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    public function getName(): string
    {
        return class_basename(static::class);
    }

    public function getTrackingScript(): ?string
    {
        return null;
    }

    public function supportsServerSideTracking(): bool
    {
        return false;
    }

    public function supportsClientSideTracking(): bool
    {
        return true;
    }

    protected function log(string $message, array $context = []): void
    {
        if ($this->config['debug'] ?? config('analytics.debug', false)) {
            Log::debug("[Analytics:{$this->getName()}] {$message}", $context);
        }
    }

    protected function handleError(\Throwable $e, string $operation): void
    {
        Log::error("[Analytics:{$this->getName()}] {$operation} failed: {$e->getMessage()}", [
            'exception' => $e,
        ]);
    }
}
```

---

## Local Analytics Provider

```php
// src/Providers/LocalAnalyticsProvider.php

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\SessionManager;
use ArtisanPackUI\Analytics\Services\VisitorResolver;

class LocalAnalyticsProvider extends AbstractAnalyticsProvider
{
    public function __construct(
        array $config,
        protected VisitorResolver $visitorResolver,
        protected SessionManager $sessionManager,
    ) {
        parent::__construct($config);
    }

    public function trackPageView(PageViewData $data): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            // Resolve visitor
            $visitor = $this->visitorResolver->resolve($data);

            // Get session
            $session = $this->sessionManager->getOrCreate($data->session_id, $visitor);

            // Create page view
            PageView::create([
                'site_id' => $this->getSiteId(),
                'session_id' => $session->id,
                'visitor_id' => $visitor->id,
                'path' => $data->path,
                'title' => $data->title,
                'referrer_path' => $data->referrer_path,
                'load_time' => $data->load_time,
                'dom_ready_time' => $data->dom_ready_time,
                'first_contentful_paint' => $data->first_contentful_paint,
            ]);

            // Update counters
            $visitor->increment('total_pageviews');
            $visitor->touch('last_seen_at');

            $session->increment('page_count');
            $session->update([
                'last_activity_at' => now(),
                'is_bounce' => false,
            ]);

            $this->log('Page view tracked', ['path' => $data->path]);
        } catch (\Throwable $e) {
            $this->handleError($e, 'trackPageView');
        }
    }

    public function trackEvent(EventData $data): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $visitor = Visitor::firstWhere('fingerprint', $data->visitor_id);
            $session = $data->session_id
                ? Session::firstWhere('session_id', $data->session_id)
                : null;

            Event::create([
                'site_id' => $this->getSiteId(),
                'session_id' => $session?->id,
                'visitor_id' => $visitor?->id,
                'name' => $data->name,
                'category' => $data->category,
                'action' => $data->action,
                'label' => $data->label,
                'properties' => $data->properties,
                'value' => $data->value,
                'source_package' => $data->source_package,
            ]);

            if ($visitor) {
                $visitor->increment('total_events');
            }

            $this->log('Event tracked', ['name' => $data->name]);
        } catch (\Throwable $e) {
            $this->handleError($e, 'trackEvent');
        }
    }

    public function supportsServerSideTracking(): bool
    {
        return true;
    }

    public function supportsClientSideTracking(): bool
    {
        return true;
    }

    protected function getSiteId(): ?int
    {
        if (!config('analytics.multi_tenant.enabled')) {
            return null;
        }

        return app('analytics.tenant')?->id;
    }
}
```

---

## Google Analytics 4 Provider

```php
// src/Providers/GoogleAnalyticsProvider.php

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use Illuminate\Support\Facades\Http;

class GoogleAnalyticsProvider extends AbstractAnalyticsProvider
{
    protected string $measurementId;
    protected ?string $apiSecret;
    protected string $endpoint = 'https://www.google-analytics.com/mp/collect';

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->measurementId = $config['measurement_id'] ?? '';
        $this->apiSecret = $config['api_secret'] ?? null;
    }

    public function trackPageView(PageViewData $data): void
    {
        if (!$this->isEnabled() || !$this->supportsServerSideTracking()) {
            return;
        }

        try {
            $this->sendToMeasurementProtocol([
                'client_id' => $data->visitor_id,
                'events' => [
                    [
                        'name' => 'page_view',
                        'params' => [
                            'page_location' => $data->path,
                            'page_title' => $data->title,
                            'page_referrer' => $data->referrer_path,
                            'engagement_time_msec' => 100,
                        ],
                    ],
                ],
            ]);

            $this->log('Page view sent to GA4', ['path' => $data->path]);
        } catch (\Throwable $e) {
            $this->handleError($e, 'trackPageView');
        }
    }

    public function trackEvent(EventData $data): void
    {
        if (!$this->isEnabled() || !$this->supportsServerSideTracking()) {
            return;
        }

        try {
            $params = array_filter([
                'event_category' => $data->category,
                'event_label' => $data->label,
                'value' => $data->value,
                ...$this->flattenProperties($data->properties ?? []),
            ]);

            $this->sendToMeasurementProtocol([
                'client_id' => $data->visitor_id,
                'events' => [
                    [
                        'name' => $this->sanitizeEventName($data->name),
                        'params' => $params,
                    ],
                ],
            ]);

            $this->log('Event sent to GA4', ['name' => $data->name]);
        } catch (\Throwable $e) {
            $this->handleError($e, 'trackEvent');
        }
    }

    public function getTrackingScript(): ?string
    {
        if (!$this->measurementId) {
            return null;
        }

        return <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$this->measurementId}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{$this->measurementId}', {
        send_page_view: false
    });

    // Listen for ArtisanPack Analytics events
    document.addEventListener('ap:analytics:pageview', function(e) {
        gtag('event', 'page_view', {
            page_location: e.detail.path,
            page_title: e.detail.title
        });
    });

    document.addEventListener('ap:analytics:event', function(e) {
        gtag('event', e.detail.name, e.detail.properties || {});
    });
</script>
HTML;
    }

    public function supportsServerSideTracking(): bool
    {
        return !empty($this->apiSecret);
    }

    public function supportsClientSideTracking(): bool
    {
        return !empty($this->measurementId);
    }

    protected function sendToMeasurementProtocol(array $payload): void
    {
        if (!$this->apiSecret) {
            throw new \RuntimeException('GA4 API Secret required for server-side tracking');
        }

        $response = Http::post($this->endpoint, array_merge($payload, [
            'measurement_id' => $this->measurementId,
            'api_secret' => $this->apiSecret,
        ]));

        if (!$response->successful()) {
            throw new \RuntimeException('GA4 Measurement Protocol request failed: ' . $response->body());
        }
    }

    protected function sanitizeEventName(string $name): string
    {
        // GA4 event names: lowercase, underscores, max 40 chars
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        return substr(trim($name, '_'), 0, 40);
    }

    protected function flattenProperties(array $properties, string $prefix = ''): array
    {
        $result = [];

        foreach ($properties as $key => $value) {
            $fullKey = $prefix ? "{$prefix}_{$key}" : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenProperties($value, $fullKey));
            } else {
                // GA4 parameter names: max 40 chars
                $result[substr($fullKey, 0, 40)] = $value;
            }
        }

        return $result;
    }
}
```

---

## Plausible Provider

```php
// src/Providers/PlausibleProvider.php

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use Illuminate\Support\Facades\Http;

class PlausibleProvider extends AbstractAnalyticsProvider
{
    protected string $domain;
    protected ?string $apiKey;
    protected string $apiUrl;

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->domain = $config['domain'] ?? '';
        $this->apiKey = $config['api_key'] ?? null;
        $this->apiUrl = $config['self_hosted_url'] ?? 'https://plausible.io';
    }

    public function trackPageView(PageViewData $data): void
    {
        if (!$this->isEnabled() || !$this->supportsServerSideTracking()) {
            return;
        }

        try {
            $this->sendEvent('pageview', $data->path, [
                'referrer' => $data->referrer_path,
                'screen_width' => $data->screen_width,
            ], $data->user_agent, $data->ip_address);

            $this->log('Page view sent to Plausible', ['path' => $data->path]);
        } catch (\Throwable $e) {
            $this->handleError($e, 'trackPageView');
        }
    }

    public function trackEvent(EventData $data): void
    {
        if (!$this->isEnabled() || !$this->supportsServerSideTracking()) {
            return;
        }

        try {
            $this->sendEvent($data->name, $data->page_path ?? '/', array_merge(
                $data->properties ?? [],
                array_filter([
                    'value' => $data->value,
                ])
            ));

            $this->log('Event sent to Plausible', ['name' => $data->name]);
        } catch (\Throwable $e) {
            $this->handleError($e, 'trackEvent');
        }
    }

    public function getTrackingScript(): ?string
    {
        if (!$this->domain) {
            return null;
        }

        $scriptUrl = rtrim($this->apiUrl, '/') . '/js/script.js';

        return <<<HTML
<!-- Plausible Analytics -->
<script defer data-domain="{$this->domain}" src="{$scriptUrl}"></script>
<script>
    window.plausible = window.plausible || function() {
        (window.plausible.q = window.plausible.q || []).push(arguments);
    };

    // Listen for ArtisanPack Analytics events
    document.addEventListener('ap:analytics:event', function(e) {
        plausible(e.detail.name, { props: e.detail.properties || {} });
    });
</script>
HTML;
    }

    public function supportsServerSideTracking(): bool
    {
        return !empty($this->apiKey);
    }

    public function supportsClientSideTracking(): bool
    {
        return !empty($this->domain);
    }

    protected function sendEvent(
        string $name,
        string $url,
        array $props = [],
        ?string $userAgent = null,
        ?string $ip = null
    ): void {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => $userAgent ?? request()->userAgent(),
        ];

        if ($ip) {
            $headers['X-Forwarded-For'] = $ip;
        }

        $response = Http::withHeaders($headers)
            ->post("{$this->apiUrl}/api/event", [
                'domain' => $this->domain,
                'name' => $name,
                'url' => $this->buildUrl($url),
                'props' => empty($props) ? null : $props,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Plausible API request failed: ' . $response->body());
        }
    }

    protected function buildUrl(string $path): string
    {
        return config('app.url') . '/' . ltrim($path, '/');
    }
}
```

---

## Analytics Manager

```php
// src/Services/AnalyticsManager.php

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use Illuminate\Support\Manager;

class AnalyticsManager extends Manager
{
    protected array $customProviders = [];

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('analytics.provider', 'local');
    }

    /**
     * Create the local analytics driver.
     */
    protected function createLocalDriver(): AnalyticsProviderInterface
    {
        return $this->container->make(LocalAnalyticsProvider::class, [
            'config' => $this->config->get('analytics.local', []),
        ]);
    }

    /**
     * Create the Google Analytics driver.
     */
    protected function createGoogleDriver(): AnalyticsProviderInterface
    {
        return new GoogleAnalyticsProvider(
            $this->config->get('analytics.providers.google', [])
        );
    }

    /**
     * Create the Plausible driver.
     */
    protected function createPlausibleDriver(): AnalyticsProviderInterface
    {
        return new PlausibleProvider(
            $this->config->get('analytics.providers.plausible', [])
        );
    }

    /**
     * Track a page view across all enabled providers.
     */
    public function trackPageView(PageViewData $data): void
    {
        foreach ($this->getEnabledProviders() as $provider) {
            if ($provider->supportsServerSideTracking()) {
                $provider->trackPageView($data);
            }
        }
    }

    /**
     * Track an event across all enabled providers.
     */
    public function trackEvent(EventData $data): void
    {
        foreach ($this->getEnabledProviders() as $provider) {
            if ($provider->supportsServerSideTracking()) {
                $provider->trackEvent($data);
            }
        }
    }

    /**
     * Get all tracking scripts for enabled providers.
     */
    public function getTrackingScripts(): string
    {
        $scripts = [];

        foreach ($this->getEnabledProviders() as $provider) {
            if ($provider->supportsClientSideTracking()) {
                $script = $provider->getTrackingScript();
                if ($script) {
                    $scripts[] = $script;
                }
            }
        }

        return implode("\n", $scripts);
    }

    /**
     * Get all enabled providers.
     */
    public function getEnabledProviders(): array
    {
        $providers = [];

        // Always check local provider
        $local = $this->driver('local');
        if ($local->isEnabled()) {
            $providers[] = $local;
        }

        // Check configured external providers
        $externalProviders = $this->config->get('analytics.providers', []);
        foreach ($externalProviders as $name => $providerConfig) {
            if ($providerConfig['enabled'] ?? false) {
                try {
                    $providers[] = $this->driver($name);
                } catch (\InvalidArgumentException $e) {
                    // Provider not registered, skip
                }
            }
        }

        return $providers;
    }

    /**
     * Register a custom provider.
     */
    public function extend($driver, \Closure $callback): static
    {
        $this->customProviders[$driver] = $callback;
        return parent::extend($driver, $callback);
    }

    /**
     * Check if a provider is enabled.
     */
    public function isProviderEnabled(string $name): bool
    {
        try {
            return $this->driver($name)->isEnabled();
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
```

---

## Creating Custom Providers

### Example: Fathom Provider

```php
// In your application or package

namespace App\Analytics\Providers;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Providers\AbstractAnalyticsProvider;
use Illuminate\Support\Facades\Http;

class FathomProvider extends AbstractAnalyticsProvider
{
    protected string $siteId;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->siteId = $config['site_id'] ?? '';
    }

    public function trackPageView(PageViewData $data): void
    {
        // Fathom uses client-side tracking primarily
        // Server-side would require their API
    }

    public function trackEvent(EventData $data): void
    {
        // Custom event tracking for Fathom
    }

    public function getTrackingScript(): ?string
    {
        if (!$this->siteId) {
            return null;
        }

        return <<<HTML
<!-- Fathom Analytics -->
<script src="https://cdn.usefathom.com/script.js" data-site="{$this->siteId}" defer></script>
HTML;
    }

    public function supportsServerSideTracking(): bool
    {
        return false;
    }

    public function supportsClientSideTracking(): bool
    {
        return !empty($this->siteId);
    }
}
```

### Registering Custom Provider

```php
// In a service provider

use ArtisanPackUI\Analytics\Facades\Analytics;
use App\Analytics\Providers\FathomProvider;

public function boot(): void
{
    Analytics::extend('fathom', function ($app) {
        return new FathomProvider(
            config('analytics.providers.fathom', [])
        );
    });
}
```

### Configuration

```php
// config/analytics.php

return [
    'providers' => [
        'fathom' => [
            'enabled' => env('FATHOM_ENABLED', false),
            'site_id' => env('FATHOM_SITE_ID'),
        ],
    ],
];
```

---

## Provider Events

The package fires events that external providers can listen to:

```php
// Events fired by the analytics package

// When a page view is tracked
event(new PageViewTracked($pageView, $session, $visitor));

// When an event is tracked
event(new EventTracked($event, $session, $visitor));

// When a goal is converted
event(new GoalConverted($goal, $conversion, $session, $visitor));
```

### Custom Event Listeners

```php
// Listen for events to sync with external systems

use ArtisanPackUI\Analytics\Events\EventTracked;

class SyncEventToExternalService
{
    public function handle(EventTracked $event): void
    {
        // Custom sync logic
        ExternalService::track($event->event->name, $event->event->properties);
    }
}
```

---

## Related Documents

- [01-architecture.md](./01-architecture.md) - Overall architecture
- [03-local-analytics-engine.md](./03-local-analytics-engine.md) - Local tracking details
- [09-api-reference.md](./09-api-reference.md) - API documentation

---

*Last Updated: January 3, 2026*
