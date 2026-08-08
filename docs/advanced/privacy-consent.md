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

## Anonymous Mode

By default a visitor who never answers the consent banner produces nothing at
all. On a site where most visitors ignore the banner, that means most of your
traffic is invisible.

Anonymous mode records a page view for those visitors without identifying
them:

```php
// config/artisanpack/analytics.php
'privacy' => [
    'anonymous_mode' => env( 'ANALYTICS_ANONYMOUS_MODE', false ),
],
```

```javascript
window.__ARTISANPACK_ANALYTICS_CONFIG__ = {
    anonymousMode: true,
};
```

### What is and is not collected

Recorded to `analytics_anonymous_page_views`: path, page title, referring
**host**, device class (`desktop` / `mobile` / `tablet`) and a timestamp.

Never recorded: visitor ID, session ID, fingerprint, IP address, user agent
string, or the full referring URL — a referrer query string can carry search
terms or share identifiers, so the server reduces it to a host regardless of
what the client sent.

Nothing is written in the browser either. Anonymous mode never calls the
visitor, session or fingerprint setup, so no cookie and no `localStorage` key
is created before consent.

The table has no column capable of holding an identifier, which is deliberate:
two anonymous rows cannot be correlated to one person even by mistake, because
the columns that would let you do it do not exist.

### The trade

Anonymous rows support counting and nothing else. There are no sessions, no
returning-visitor detection and no per-visitor drill-down for this traffic,
because there is no identifier to group by. That is the cost of collecting it
without consent, and it is why the rows live in their own table rather than in
`analytics_page_views` — every visitor- and session-scoped query stays correct
without needing to know this feature exists.

### Seeing it in the dashboard

Anonymous rows are excluded from every figure by default, so enabling
collection does not silently move numbers that were already being reported.

Once rows exist, the dashboard offers an **Include anonymous traffic** toggle
and an **Anonymous** tab. Neither appears when the feature is off, or when it
is on but nothing has been recorded for the selected range — an empty
anonymous panel would read like a fault rather than an absence.

With the toggle on, only page-view figures change:

| Metric | Can include anonymous traffic | Why |
|--------|-------------------------------|-----|
| Page views | Yes | One anonymous row is one page view. |
| Top pages | Yes | Grouped by path, which anonymous rows record. |
| Referring hosts | Yes | Anonymous rows record the referring host. |
| Page views over time | Yes | Anonymous rows carry a timestamp. |
| Device split | Reported separately | Anonymous rows record a device class, but the identified breakdown counts sessions. Different units, so they are shown side by side rather than summed. |
| Unique visitors | No | No visitor ID to count distinctly. |
| Sessions | No | No session ID; a row is not part of a visit. |
| Bounce rate | No | Derived from sessions. |
| Session duration | No | Derived from sessions. |
| Pages per session | No | A ratio of two session-scoped figures. |
| Active now (realtime) | No | Counts active visitors, which anonymous rows are not. |
| Traffic sources | No | Counted per session. Use referring hosts on the Anonymous tab instead. |

Every metric in the second group is labelled in the interface while the toggle
is on. That labelling is the point: a combined page-view figure sitting
unlabelled beside a consented-only visitor figure invites a ratio nobody
should compute.

Programmatically the scope is a filter on `AnalyticsQuery`:

```php
use ArtisanPackUI\Analytics\Facades\AnalyticsQuery;

// Consented visitors only — the default, unchanged from before anonymous mode.
AnalyticsQuery::getPageViewCount( $range );

// Consented plus anonymous page views.
AnalyticsQuery::getPageViewCount( $range, [ 'anonymous' => 'include' ] );
AnalyticsQuery::includeAnonymous()->getPageViewCount( $range );

// Anonymous page views only.
AnalyticsQuery::onlyAnonymous()->getPageViewCount( $range );

// The whole anonymous summary in one call.
AnalyticsQuery::getAnonymousStats( $range );
```

The same modes are available over HTTP as `?anonymous=include` (or `only`) on
the analytics query endpoints, plus `GET /api/analytics/anonymous` for the
summary and `GET /api/analytics/referrers` for referring hosts counted in page
views.

These methods live on the concrete `AnalyticsQuery` service rather than on
`AnalyticsQueryInterface`. That interface is a published contract, and adding
methods to it would break every implementor.

### Retention changes historical figures

Anonymous rows are swept on the same retention schedule as everything else, so
a combined figure for an old period shrinks as those rows age out while the
consented figures for the same period are still there. If you need a stable
historical number, record the consented-only one.

### Granting consent mid-visit

When a visitor accepts, the tracker upgrades to normal identified tracking for
the rest of the visit. Rows already written stay anonymous and are never
back-filled with an identity: they were collected under a promise, and
retroactively attaching a visitor to them would break it.

### Do Not Track still wins

An explicit opt-out — `DNT: 1` or `Sec-GPC: 1` — suppresses everything,
anonymous mode included, both in the browser and at the ingest endpoint.
Anonymous mode is an argument about identifiability, not a way around someone
saying no.

### Before you enable it

Enabling this changes what you collect before consent. Review your consent
banner copy and privacy policy alongside it — wording along the lines of "we
don't collect anything until you accept" stops being true, and the usual basis
for this collection is legitimate interest rather than consent. That is a
decision for you and your legal advice, not a default this package can make
for you, which is why it ships off.

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

Patterns are matched against the path of the **page being tracked**, never against
the URI of the ingest endpoint the beacon was posted to. That distinction matters
because the ingest routes themselves live under `/api`, which the default list
excludes — matching on the ingest route would drop every beacon.

Exclusion is evaluated once per tracked page view or event, so a batched beacon
carrying several paths records the ones that are not excluded and drops only
those that are. Query strings and fragments are ignored when matching.

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
