---
title: Multiple Providers
---

# Multiple Providers

ArtisanPack UI Analytics supports sending data to multiple analytics providers simultaneously.

## Available Providers

| Provider | Description |
|----------|-------------|
| `local` | Database storage (default) |
| `google` | Google Analytics 4 |
| `plausible` | Plausible Analytics |

## Enabling Multiple Providers

```php
// .env
ANALYTICS_ACTIVE_PROVIDERS=local,google

// config/artisanpack/analytics.php
'active_providers' => array_filter(explode(',', env('ANALYTICS_ACTIVE_PROVIDERS', 'local'))),
```

## Local Provider

The local provider stores all data in your database.

### Configuration

```php
'local' => [
    'enabled' => env('ANALYTICS_LOCAL_ENABLED', true),
    'connection' => env('ANALYTICS_DB_CONNECTION', null),
    'table_prefix' => env('ANALYTICS_TABLE_PREFIX', 'analytics_'),
    'anonymize_ip' => env('ANALYTICS_ANONYMIZE_IP', true),
    'queue_processing' => env('ANALYTICS_QUEUE_PROCESSING', true),
    'queue_name' => env('ANALYTICS_QUEUE_NAME', 'analytics'),
],
```

### Benefits

- Complete data ownership
- Privacy compliance
- Custom queries
- No external dependencies

## Google Analytics 4

Send data to Google Analytics 4 via the Measurement Protocol.

### Configuration

```php
// .env
ANALYTICS_GOOGLE_ENABLED=true
ANALYTICS_GOOGLE_MEASUREMENT_ID=G-XXXXXXXXXX
ANALYTICS_GOOGLE_API_SECRET=your-api-secret

// config
'providers' => [
    'google' => [
        'enabled' => env('ANALYTICS_GOOGLE_ENABLED', false),
        'measurement_id' => env('ANALYTICS_GOOGLE_MEASUREMENT_ID'),
        'api_secret' => env('ANALYTICS_GOOGLE_API_SECRET'),
    ],
],
```

### Getting Credentials

1. Go to Google Analytics Admin
2. Select your GA4 property
3. Go to Data Streams → Web
4. Create or select a stream
5. Copy the Measurement ID (G-XXXXXXXX)
6. Under "Measurement Protocol API secrets", create a secret

### What Gets Sent

- Page views
- Custom events with properties
- Session information
- User properties

## Plausible Analytics

Send data to Plausible (cloud or self-hosted).

### Configuration

```php
// .env
ANALYTICS_PLAUSIBLE_ENABLED=true
ANALYTICS_PLAUSIBLE_DOMAIN=yoursite.com
ANALYTICS_PLAUSIBLE_API_URL=https://plausible.io/api
ANALYTICS_PLAUSIBLE_API_KEY=your-api-key

// config
'providers' => [
    'plausible' => [
        'enabled' => env('ANALYTICS_PLAUSIBLE_ENABLED', false),
        'domain' => env('ANALYTICS_PLAUSIBLE_DOMAIN'),
        'api_url' => env('ANALYTICS_PLAUSIBLE_API_URL', 'https://plausible.io/api'),
        'api_key' => env('ANALYTICS_PLAUSIBLE_API_KEY'),
    ],
],
```

### Self-Hosted Plausible

For self-hosted instances:

```php
// .env
ANALYTICS_PLAUSIBLE_API_URL=https://analytics.yourdomain.com/api
```

## Creating Custom Providers

Implement the `AnalyticsProviderInterface`:

```php
namespace App\Analytics\Providers;

use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;

class MixpanelProvider implements AnalyticsProviderInterface
{
    public function __construct(
        private MixpanelClient $client,
        private array $config,
    ) {}

    public function trackPageView(PageViewData $data): void
    {
        $this->client->track('Page View', [
            'path' => $data->path,
            'title' => $data->title,
            'referrer' => $data->referrer,
        ]);
    }

    public function trackEvent(EventData $data): void
    {
        $this->client->track($data->name, array_merge(
            $data->properties ?? [],
            [
                'category' => $data->category,
                'value' => $data->value,
            ]
        ));
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    public function getName(): string
    {
        return 'mixpanel';
    }
}
```

### Register the Provider

```php
// In a service provider
use ArtisanPackUI\Analytics\Facades\Analytics;

public function boot(): void
{
    Analytics::extend('mixpanel', function ($app) {
        return new MixpanelProvider(
            $app->make(MixpanelClient::class),
            config('services.mixpanel'),
        );
    });
}
```

### Enable the Provider

```php
// .env
ANALYTICS_ACTIVE_PROVIDERS=local,mixpanel
```

## Provider Methods

### Get a Specific Provider

```php
use ArtisanPackUI\Analytics\Facades\Analytics;

$localProvider = Analytics::provider('local');
$googleProvider = Analytics::provider('google');
```

### Get Active Providers

```php
$providers = Analytics::getActiveProviders();
```

### Get Provider Names

```php
$names = Analytics::getProviderNames();
// ['local', 'google', 'plausible']
```

### Set Default Provider

```php
Analytics::setDefaultProvider('local');
```

## Provider Priority

When tracking, data is sent to all active providers. Each provider handles the data independently.

```php
// This sends to ALL active providers
trackPageView('/products', 'Products');

// Internally:
// 1. LocalProvider->trackPageView()
// 2. GoogleProvider->trackPageView()
// 3. PlausibleProvider->trackPageView()
```

## Error Handling

Provider failures don't affect other providers:

```php
// If Google fails, local still succeeds
// Errors are logged but don't throw exceptions
```

Configure error handling:

```php
// In a service provider
Analytics::extend('google', function ($app) {
    $provider = new GoogleProvider(...);

    // Wrap with error handling
    return new ErrorHandlingProvider($provider, function ($e) {
        Log::error('Google Analytics error', ['error' => $e->getMessage()]);
    });
});
```

## Testing Providers

```php
use ArtisanPackUI\Analytics\Facades\Analytics;

test('sends to multiple providers', function () {
    // Mock providers
    $local = Mockery::mock(LocalProvider::class);
    $local->shouldReceive('trackPageView')->once();
    $local->shouldReceive('isEnabled')->andReturn(true);

    $google = Mockery::mock(GoogleProvider::class);
    $google->shouldReceive('trackPageView')->once();
    $google->shouldReceive('isEnabled')->andReturn(true);

    // Register mocks
    Analytics::extend('local', fn() => $local);
    Analytics::extend('google', fn() => $google);

    // Track
    trackPageView('/test', 'Test');
});
```

## Use Cases

### Development vs Production

```php
// .env.local
ANALYTICS_ACTIVE_PROVIDERS=local

// .env.production
ANALYTICS_ACTIVE_PROVIDERS=local,google
```

### Gradual Migration

When migrating to a new provider:

```php
// Phase 1: Run both
ANALYTICS_ACTIVE_PROVIDERS=old_provider,new_provider

// Phase 2: Verify data
// Compare reports from both providers

// Phase 3: Switch
ANALYTICS_ACTIVE_PROVIDERS=new_provider
```

### Redundancy

For critical analytics:

```php
// Send to multiple backends
ANALYTICS_ACTIVE_PROVIDERS=local,google,plausible
```
