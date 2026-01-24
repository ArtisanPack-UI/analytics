---
title: Privacy & Consent
---

# Privacy & Consent

ArtisanPack UI Analytics provides comprehensive privacy features for GDPR, CCPA, and other privacy regulation compliance.

## Privacy-First Design

The package is designed with privacy in mind:

- Local data storage (no third-party sharing)
- IP anonymization by default
- Do Not Track support
- Built-in consent management
- Data retention controls
- Export and deletion capabilities

## Configuration

### Basic Privacy Settings

```php
// config/artisanpack/analytics.php
'privacy' => [
    'consent_required' => env('ANALYTICS_CONSENT_REQUIRED', false),
    'consent_cookie_lifetime' => env('ANALYTICS_CONSENT_LIFETIME', 365),
    'respect_dnt' => env('ANALYTICS_RESPECT_DNT', true),
],
```

### IP Anonymization

```php
'privacy' => [
    'anonymization' => [
        'ip_address' => env('ANALYTICS_ANONYMIZE_IP', true),
        'user_agent' => env('ANALYTICS_ANONYMIZE_UA', false),
        'screen_resolution' => env('ANALYTICS_ANONYMIZE_SCREEN', false),
    ],
],
```

IP anonymization zeroes:

```text
- Last octet for IPv4 (192.168.1.100 → 192.168.1.0)
- Last 80 bits for IPv6
```

## Consent Management

### Consent Categories

Configure consent categories:

```php
'privacy' => [
    'consent_categories' => [
        'necessary' => [
            'name' => 'Necessary',
            'description' => 'Essential for the website to function.',
            'required' => true, // Cannot be disabled
        ],
        'analytics' => [
            'name' => 'Analytics',
            'description' => 'Helps us understand how visitors use our website.',
            'required' => false,
        ],
        'marketing' => [
            'name' => 'Marketing',
            'description' => 'Used to track visitors for advertising purposes.',
            'required' => false,
        ],
    ],
],
```

### Consent Banner

Add the consent banner to your layout:

```blade
@analyticsConsentBanner
```

Or use the Livewire component directly:

```blade
<livewire:artisanpack-analytics::consent-banner />
```

### Manual Consent Management

Using helper functions:

```php
// Check consent
if (analyticsHasConsent($fingerprint, 'analytics')) {
    // Track data
}

// Grant consent
analyticsGrantConsent($fingerprint, ['analytics', 'marketing']);

// Revoke consent
analyticsRevokeConsent($fingerprint, ['marketing']);

// Get status
$status = analyticsConsentStatus($fingerprint);
```

Using the ConsentService:

```php
use ArtisanPackUI\Analytics\Services\ConsentService;

$consentService = app(ConsentService::class);

$consentService->hasConsent($fingerprint, 'analytics');
$consentService->grantConsent($fingerprint, ['analytics']);
$consentService->revokeConsent($fingerprint, ['analytics']);
```

### JavaScript API

```javascript
// Check consent
if (analytics.hasConsent('analytics')) {
    // Tracking is allowed
}

// Grant consent
analytics.grantConsent(['analytics', 'marketing']);

// Revoke consent
analytics.revokeConsent(['marketing']);

// Get all consent status
const status = analytics.getConsentStatus();
```

## Do Not Track

When enabled, the DNT browser header is respected:

```php
'privacy' => [
    'respect_dnt' => true,
],
```

Check DNT in your code:

```php
use ArtisanPackUI\Analytics\Services\TrackingService;

$service = app(TrackingService::class);

if (!$service->canTrack()) {
    // DNT is set or consent not given
}
```

## Exclusions

### Exclude IP Addresses

```php
// .env
ANALYTICS_EXCLUDED_IPS=192.168.1.1,10.0.0.0/8

// config
'privacy' => [
    'excluded_ips' => array_filter(explode(',', env('ANALYTICS_EXCLUDED_IPS', ''))),
],
```

### Exclude User Agents

```php
'privacy' => [
    'excluded_user_agents' => [
        '/bot/i',
        '/crawler/i',
        '/spider/i',
        '/slurp/i',
        '/mediapartners/i',
    ],
],
```

### Exclude Paths

```php
'privacy' => [
    'excluded_paths' => [
        '/admin/*',
        '/api/*',
        '/_debugbar/*',
        '/telescope/*',
    ],
],
```

## Data Retention

Configure automatic data cleanup:

```php
'retention' => [
    'period' => env('ANALYTICS_RETENTION_DAYS', 90),
    'aggregate_before_delete' => true,
    'aggregation_retention' => 0, // 0 = keep forever
    'cleanup_schedule' => '0 3 * * *', // Daily at 3 AM
],
```

### Manual Cleanup

```bash
php artisan analytics:cleanup
```

```php
use ArtisanPackUI\Analytics\Services\DataDeletionService;

$service = app(DataDeletionService::class);
$service->deleteOldData(90); // Delete data older than 90 days
```

## GDPR Compliance

### Data Subject Rights

#### Right to Access (Export)

```php
use ArtisanPackUI\Analytics\Services\DataExportService;

$service = app(DataExportService::class);
$data = $service->exportVisitorData($visitorId);

// Returns all data associated with the visitor
```

#### Right to Erasure (Deletion)

```php
use ArtisanPackUI\Analytics\Services\DataDeletionService;

$service = app(DataDeletionService::class);
$service->deleteVisitorData($visitorId);

// Deletes all data for the visitor
```

### Privacy Integration

Integrate with your existing privacy tools:

```php
use ArtisanPackUI\Analytics\Services\PrivacyIntegration;

$privacy = app(PrivacyIntegration::class);

// Check if tracking is allowed for a user
$canTrack = $privacy->canTrackUser($user);

// Handle data subject request
$privacy->handleDataRequest($user, 'export');
$privacy->handleDataRequest($user, 'delete');
```

## Events for Compliance

Listen for consent events:

```php
use ArtisanPackUI\Analytics\Events\ConsentGranted;
use ArtisanPackUI\Analytics\Events\ConsentRevoked;

// Log consent for compliance audit
Event::listen(ConsentGranted::class, function ($event) {
    AuditLog::create([
        'action' => 'consent_granted',
        'visitor_id' => $event->visitor->id,
        'categories' => $event->categories,
        'timestamp' => now(),
    ]);
});
```

## Cookie Policy

The package uses these cookies:

| Cookie | Purpose | Lifetime |
|--------|---------|----------|
| `_ap_vid` | Visitor identifier | 365 days |
| `_ap_sid` | Session identifier | 30 minutes |
| `_ap_consent` | Consent preferences | 365 days |

Configure cookie names:

```php
'session' => [
    'cookie_name' => '_ap_sid',
    'visitor_cookie_name' => '_ap_vid',
    'cookie_lifetime' => 365,
],
```

## Best Practices

1. **Enable consent by default** for EU visitors
2. **Anonymize IPs** in privacy-sensitive contexts
3. **Set reasonable retention** periods (90 days is common)
4. **Document your tracking** in your privacy policy
5. **Provide easy opt-out** mechanisms
6. **Test consent flows** regularly
7. **Audit consent records** periodically

## Privacy Policy Template

Include in your privacy policy:

```text
We use ArtisanPack UI Analytics to collect anonymized usage data.
This includes:
- Pages visited
- Time spent on pages
- Traffic sources
- Device and browser information

We do not:
- Share data with third parties
- Track individual users without consent
- Store complete IP addresses

You can opt out of analytics tracking by:
- Using the "Do Not Track" browser setting
- Declining analytics cookies when prompted
- Contacting us to request data deletion
```
