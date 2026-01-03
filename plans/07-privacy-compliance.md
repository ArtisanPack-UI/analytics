# Privacy & GDPR Compliance

**Purpose:** Define privacy features, GDPR compliance, consent management, and future privacy package integration
**Last Updated:** January 3, 2026

---

## Overview

The analytics package is designed with **privacy-first principles**:

1. **Local Data Storage** - Data stays in your database, not third-party servers
2. **IP Anonymization** - Option to anonymize IP addresses before storage
3. **Consent Management** - Built-in consent tracking with privacy package integration hooks
4. **Do Not Track Respect** - Honor browser DNT signals
5. **Data Retention** - Automatic cleanup of old data
6. **Data Export/Deletion** - GDPR Article 15 (access) and Article 17 (erasure) support

---

## Privacy Configuration

```php
// config/analytics.php

return [
    'privacy' => [
        // Require user consent before tracking
        'require_consent' => env('ANALYTICS_REQUIRE_CONSENT', false),

        // Consent cookie lifetime in days
        'cookie_lifetime' => 365,

        // Data retention period in days (null = unlimited)
        'data_retention' => env('ANALYTICS_DATA_RETENTION', 730), // 2 years

        // How long to keep aggregated data (usually longer than raw data)
        'aggregate_retention' => env('ANALYTICS_AGGREGATE_RETENTION', 1825), // 5 years

        // Anonymization settings
        'anonymization' => [
            // Anonymize IP addresses (zero last octet)
            'ip_address' => env('ANALYTICS_ANONYMIZE_IP', true),

            // Hash user agent strings
            'user_agent' => env('ANALYTICS_ANONYMIZE_UA', false),

            // Don't store exact screen dimensions (round to nearest 100)
            'screen_resolution' => env('ANALYTICS_ANONYMIZE_SCREEN', false),
        ],

        // Respect Do Not Track browser header
        'respect_dnt' => env('ANALYTICS_RESPECT_DNT', true),

        // Consent categories
        'consent_categories' => [
            'analytics' => [
                'name' => __('Analytics'),
                'description' => __('Helps us understand how visitors use our website'),
                'required' => false,
            ],
            'marketing' => [
                'name' => __('Marketing'),
                'description' => __('Used to track visitors across websites for advertising'),
                'required' => false,
            ],
        ],
    ],
];
```

---

## IP Anonymization

### Implementation

```php
// src/Services/VisitorResolver.php

class VisitorResolver
{
    public function resolve(VisitorData $data): Visitor
    {
        $fingerprint = $this->generateFingerprint($data);

        return Visitor::firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'ip_address' => $this->processIpAddress($data->ip_address),
                'user_agent' => $this->processUserAgent($data->user_agent),
                // ... other fields
            ]
        );
    }

    protected function processIpAddress(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        if (!config('analytics.privacy.anonymization.ip_address')) {
            return $ip;
        }

        return $this->anonymizeIp($ip);
    }

    protected function anonymizeIp(string $ip): string
    {
        // IPv4: Zero out last octet (192.168.1.123 -> 192.168.1.0)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        // IPv6: Zero out last 80 bits (5 groups)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts = array_slice($parts, 0, 3);
            return implode(':', $parts) . ':0:0:0:0:0';
        }

        return $ip;
    }

    protected function processUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        if (!config('analytics.privacy.anonymization.user_agent')) {
            return $userAgent;
        }

        // Hash the user agent but keep device/browser detection working
        return hash('sha256', $userAgent);
    }

    protected function generateFingerprint(VisitorData $data): string
    {
        // Privacy-preserving fingerprint
        // Does NOT include IP address to prevent cross-site tracking
        $components = [
            $data->user_agent ?? '',
            $data->screen_width ?? 0,
            $data->screen_height ?? 0,
            $data->timezone ?? '',
            $data->language ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }
}
```

---

## Consent Management

### Consent Model

```php
// src/Models/Consent.php

class Consent extends Model
{
    protected $table = 'analytics_consents';

    protected $fillable = [
        'site_id', 'visitor_id',
        'category', 'granted',
        'ip_address', 'user_agent',
        'granted_at', 'revoked_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        if (!$this->granted || $this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function revoke(): void
    {
        $this->update([
            'granted' => false,
            'revoked_at' => now(),
        ]);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('granted', true)
            ->whereNull('revoked_at')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
```

### Consent Service

```php
// src/Services/ConsentService.php

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Http\Request;

class ConsentService
{
    /**
     * Check if a visitor has active consent for a category.
     */
    public function hasConsent(string $visitorId, string $category = 'analytics'): bool
    {
        // Check with future privacy package first
        if ($this->hasPrivacyPackage()) {
            return app('privacy')->hasConsent($category);
        }

        // If consent not required, always allow
        if (!config('analytics.privacy.require_consent')) {
            return true;
        }

        // Check for DNT header
        if ($this->shouldRespectDnt() && request()->header('DNT') === '1') {
            return false;
        }

        // Check stored consent
        return Consent::query()
            ->whereHas('visitor', fn($q) => $q->where('fingerprint', $visitorId))
            ->where('category', $category)
            ->active()
            ->exists();
    }

    /**
     * Grant consent for categories.
     */
    public function grantConsent(
        string $visitorId,
        array $categories,
        Request $request
    ): void {
        $visitor = Visitor::firstWhere('fingerprint', $visitorId);

        if (!$visitor) {
            // Create visitor if doesn't exist
            $visitor = Visitor::create([
                'fingerprint' => $visitorId,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        foreach ($categories as $category) {
            Consent::updateOrCreate(
                [
                    'visitor_id' => $visitor->id,
                    'category' => $category,
                ],
                [
                    'site_id' => $this->getSiteId(),
                    'granted' => true,
                    'granted_at' => now(),
                    'revoked_at' => null,
                    'expires_at' => now()->addDays(config('analytics.privacy.cookie_lifetime', 365)),
                    'ip_address' => $this->anonymizeIp($request->ip()),
                    'user_agent' => $request->userAgent(),
                ]
            );
        }
    }

    /**
     * Revoke consent for categories.
     */
    public function revokeConsent(string $visitorId, array $categories): void
    {
        Consent::query()
            ->whereHas('visitor', fn($q) => $q->where('fingerprint', $visitorId))
            ->whereIn('category', $categories)
            ->active()
            ->each(fn($consent) => $consent->revoke());
    }

    /**
     * Get consent status for a visitor.
     */
    public function getConsentStatus(string $visitorId): array
    {
        $categories = config('analytics.privacy.consent_categories', []);
        $consents = Consent::query()
            ->whereHas('visitor', fn($q) => $q->where('fingerprint', $visitorId))
            ->active()
            ->get()
            ->keyBy('category');

        $status = [];
        foreach ($categories as $key => $category) {
            $status[$key] = [
                'name' => $category['name'],
                'description' => $category['description'],
                'required' => $category['required'] ?? false,
                'granted' => isset($consents[$key]),
                'granted_at' => $consents[$key]?->granted_at,
            ];
        }

        return $status;
    }

    protected function hasPrivacyPackage(): bool
    {
        return app()->bound('privacy');
    }

    protected function shouldRespectDnt(): bool
    {
        return config('analytics.privacy.respect_dnt', true);
    }

    protected function getSiteId(): ?int
    {
        if (!config('analytics.multi_tenant.enabled')) {
            return null;
        }
        return app('analytics.tenant')?->id;
    }

    protected function anonymizeIp(?string $ip): ?string
    {
        if (!$ip || !config('analytics.privacy.anonymization.ip_address')) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        return $ip;
    }
}
```

### Consent Controller

```php
// src/Http/Controllers/ConsentController.php

namespace ArtisanPackUI\Analytics\Http\Controllers;

use ArtisanPackUI\Analytics\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    public function __construct(
        protected ConsentService $consent
    ) {}

    public function status(Request $request): JsonResponse
    {
        $visitorId = $request->input('visitor_id');

        if (!$visitorId) {
            return response()->json(['error' => __('Visitor ID required')], 400);
        }

        return response()->json([
            'consent_required' => config('analytics.privacy.require_consent'),
            'categories' => $this->consent->getConsentStatus($visitorId),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visitor_id' => 'required|string',
            'categories' => 'required|array',
            'categories.*' => 'boolean',
        ]);

        $granted = [];
        $revoked = [];

        foreach ($validated['categories'] as $category => $granted) {
            if ($granted) {
                $granted[] = $category;
            } else {
                $revoked[] = $category;
            }
        }

        if (!empty($granted)) {
            $this->consent->grantConsent($validated['visitor_id'], $granted, $request);
        }

        if (!empty($revoked)) {
            $this->consent->revokeConsent($validated['visitor_id'], $revoked);
        }

        return response()->json([
            'success' => true,
            'categories' => $this->consent->getConsentStatus($validated['visitor_id']),
        ]);
    }
}
```

---

## Consent Banner Component

```blade
{{-- resources/views/components/consent-banner.blade.php --}}

@props([
    'position' => 'bottom', // bottom, top, modal
])

<div
    x-data="{
        show: false,
        categories: {},
        required: @js(config('analytics.privacy.require_consent', false)),

        init() {
            if (!this.required) return;

            // Check if consent already given
            const consent = localStorage.getItem('ap_analytics_consent');
            if (!consent) {
                this.show = true;
            }
        },

        acceptAll() {
            const categories = @js(array_keys(config('analytics.privacy.consent_categories', [])));
            categories.forEach(cat => this.categories[cat] = true);
            this.save();
        },

        rejectAll() {
            const categories = @js(array_keys(config('analytics.privacy.consent_categories', [])));
            categories.forEach(cat => this.categories[cat] = false);
            this.save();
        },

        save() {
            // Store locally
            localStorage.setItem('ap_analytics_consent', JSON.stringify(this.categories));

            // Notify analytics
            if (window.ArtisanPackAnalytics) {
                Object.entries(this.categories).forEach(([cat, granted]) => {
                    if (granted) {
                        ArtisanPackAnalytics.consent.grant([cat]);
                    } else {
                        ArtisanPackAnalytics.consent.revoke([cat]);
                    }
                });
            }

            this.show = false;
        }
    }"
    x-show="show"
    x-transition
    x-cloak
    class="fixed {{ $position === 'bottom' ? 'bottom-0' : 'top-0' }} inset-x-0 z-50 p-4"
>
    <div class="max-w-4xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('Privacy Settings') }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ __('We use cookies to understand how you use our website and improve your experience.') }}
                </p>
            </div>
        </div>

        <div class="space-y-3 mb-6">
            @foreach(config('analytics.privacy.consent_categories', []) as $key => $category)
                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        x-model="categories['{{ $key }}']"
                        {{ ($category['required'] ?? false) ? 'checked disabled' : '' }}
                        class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    >
                    <div>
                        <span class="font-medium text-gray-900 dark:text-white">
                            {{ $category['name'] }}
                            @if($category['required'] ?? false)
                                <span class="text-xs text-gray-500">({{ __('Required') }})</span>
                            @endif
                        </span>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $category['description'] }}
                        </p>
                    </div>
                </label>
            @endforeach
        </div>

        <div class="flex items-center justify-end gap-3">
            <button
                @click="rejectAll"
                class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
            >
                {{ __('Reject All') }}
            </button>
            <button
                @click="save"
                class="px-4 py-2 text-sm font-medium bg-gray-200 hover:bg-gray-300 rounded-lg"
            >
                {{ __('Save Preferences') }}
            </button>
            <button
                @click="acceptAll"
                class="px-4 py-2 text-sm font-medium bg-primary-600 text-white hover:bg-primary-700 rounded-lg"
            >
                {{ __('Accept All') }}
            </button>
        </div>
    </div>
</div>
```

---

## Data Retention

### Automatic Cleanup Job

```php
// src/Jobs/CleanupOldData.php

namespace ArtisanPackUI\Analytics\Jobs;

use ArtisanPackUI\Analytics\Models\Aggregate;
use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupOldData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $retentionDays = config('analytics.privacy.data_retention');

        if ($retentionDays === null) {
            Log::info('[Analytics] Data retention disabled, skipping cleanup');
            return;
        }

        $cutoff = now()->subDays($retentionDays);

        Log::info("[Analytics] Starting data cleanup for data older than {$cutoff}");

        // Delete in order of dependencies
        $this->deleteOldPageViews($cutoff);
        $this->deleteOldEvents($cutoff);
        $this->deleteOldConversions($cutoff);
        $this->deleteOldSessions($cutoff);
        $this->deleteOrphanedVisitors($cutoff);
        $this->deleteOldAggregates();
        $this->deleteExpiredConsents();

        Log::info('[Analytics] Data cleanup completed');
    }

    protected function deleteOldPageViews($cutoff): void
    {
        $count = PageView::where('created_at', '<', $cutoff)->delete();
        Log::info("[Analytics] Deleted {$count} old page views");
    }

    protected function deleteOldEvents($cutoff): void
    {
        $count = Event::where('created_at', '<', $cutoff)->delete();
        Log::info("[Analytics] Deleted {$count} old events");
    }

    protected function deleteOldConversions($cutoff): void
    {
        $count = Conversion::where('created_at', '<', $cutoff)->delete();
        Log::info("[Analytics] Deleted {$count} old conversions");
    }

    protected function deleteOldSessions($cutoff): void
    {
        $count = Session::where('started_at', '<', $cutoff)->delete();
        Log::info("[Analytics] Deleted {$count} old sessions");
    }

    protected function deleteOrphanedVisitors($cutoff): void
    {
        // Delete visitors with no recent activity
        $count = Visitor::where('last_seen_at', '<', $cutoff)
            ->whereDoesntHave('sessions', fn($q) => $q->where('started_at', '>=', $cutoff))
            ->delete();
        Log::info("[Analytics] Deleted {$count} orphaned visitors");
    }

    protected function deleteOldAggregates(): void
    {
        $aggregateRetention = config('analytics.privacy.aggregate_retention');

        if ($aggregateRetention === null) {
            return;
        }

        $cutoff = now()->subDays($aggregateRetention);
        $count = Aggregate::where('date', '<', $cutoff)->delete();
        Log::info("[Analytics] Deleted {$count} old aggregates");
    }

    protected function deleteExpiredConsents(): void
    {
        $count = Consent::where('expires_at', '<', now())->delete();
        Log::info("[Analytics] Deleted {$count} expired consents");
    }
}
```

### Scheduling

```php
// In AnalyticsServiceProvider or app's Console/Kernel

protected function schedule(Schedule $schedule): void
{
    $schedule->job(new CleanupOldData)
        ->daily()
        ->at('03:00')
        ->withoutOverlapping();

    $schedule->job(new AggregateAnalytics)
        ->daily()
        ->at('01:00');
}
```

---

## GDPR Data Subject Rights

### Data Export (Article 15 - Right of Access)

```php
// src/Services/DataExportService.php

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Collection;

class DataExportService
{
    public function exportVisitorData(string $visitorId): array
    {
        $visitor = Visitor::with([
            'sessions.pageViews',
            'sessions.events',
            'consents',
        ])->firstWhere('fingerprint', $visitorId);

        if (!$visitor) {
            return ['error' => __('Visitor not found')];
        }

        return [
            'visitor' => [
                'id' => $visitor->fingerprint,
                'first_seen' => $visitor->first_seen_at->toIso8601String(),
                'last_seen' => $visitor->last_seen_at->toIso8601String(),
                'country' => $visitor->country,
                'device_type' => $visitor->device_type,
                'browser' => $visitor->browser,
                'os' => $visitor->os,
            ],
            'sessions' => $visitor->sessions->map(fn($session) => [
                'started_at' => $session->started_at->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
                'duration_seconds' => $session->duration,
                'entry_page' => $session->entry_page,
                'exit_page' => $session->exit_page,
                'page_count' => $session->page_count,
                'referrer' => $session->referrer,
                'utm' => [
                    'source' => $session->utm_source,
                    'medium' => $session->utm_medium,
                    'campaign' => $session->utm_campaign,
                ],
                'page_views' => $session->pageViews->map(fn($pv) => [
                    'path' => $pv->path,
                    'title' => $pv->title,
                    'time_on_page' => $pv->time_on_page,
                    'timestamp' => $pv->created_at->toIso8601String(),
                ]),
                'events' => $session->events->map(fn($e) => [
                    'name' => $e->name,
                    'category' => $e->category,
                    'properties' => $e->properties,
                    'timestamp' => $e->created_at->toIso8601String(),
                ]),
            ]),
            'consents' => $visitor->consents->map(fn($c) => [
                'category' => $c->category,
                'granted' => $c->granted,
                'granted_at' => $c->granted_at?->toIso8601String(),
                'revoked_at' => $c->revoked_at?->toIso8601String(),
            ]),
            'exported_at' => now()->toIso8601String(),
        ];
    }

    public function exportAsCsv(string $visitorId): string
    {
        $data = $this->exportVisitorData($visitorId);

        // Convert to CSV format
        $csv = __('Analytics Data Export') . "\n";
        $csv .= __('Exported') . ": {$data['exported_at']}\n\n";

        $csv .= "=== " . __('Visitor Info') . " ===\n";
        foreach ($data['visitor'] as $key => $value) {
            $csv .= "{$key},{$value}\n";
        }

        $csv .= "\n=== " . __('Sessions') . " ===\n";
        $csv .= __('Started') . "," . __('Ended') . "," . __('Duration') . "," . __('Entry Page') . "," . __('Exit Page') . "," . __('Pages') . "\n";
        foreach ($data['sessions'] as $session) {
            $csv .= "{$session['started_at']},{$session['ended_at']},{$session['duration_seconds']},{$session['entry_page']},{$session['exit_page']},{$session['page_count']}\n";
        }

        return $csv;
    }
}
```

### Data Deletion (Article 17 - Right to Erasure)

```php
// src/Services/DataDeletionService.php

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataDeletionService
{
    public function deleteVisitorData(string $visitorId): bool
    {
        $visitor = Visitor::firstWhere('fingerprint', $visitorId);

        if (!$visitor) {
            return false;
        }

        DB::transaction(function () use ($visitor) {
            // Delete all related data
            // Cascades will handle most, but be explicit

            // Delete page views
            $visitor->pageViews()->delete();

            // Delete events
            $visitor->events()->delete();

            // Delete sessions
            $visitor->sessions()->delete();

            // Delete consents
            $visitor->consents()->delete();

            // Finally, delete the visitor
            $visitor->delete();

            Log::info("[Analytics] Deleted all data for visitor {$visitor->fingerprint}");
        });

        return true;
    }

    public function anonymizeVisitorData(string $visitorId): bool
    {
        $visitor = Visitor::firstWhere('fingerprint', $visitorId);

        if (!$visitor) {
            return false;
        }

        DB::transaction(function () use ($visitor) {
            // Anonymize visitor record
            $visitor->update([
                'ip_address' => null,
                'user_agent' => null,
                'country' => null,
                'region' => null,
                'city' => null,
            ]);

            // Anonymize session referrers
            $visitor->sessions()->update([
                'referrer' => null,
                'referrer_domain' => null,
            ]);

            Log::info("[Analytics] Anonymized data for visitor {$visitor->fingerprint}");
        });

        return true;
    }
}
```

---

## Future Privacy Package Integration

The analytics package is designed to integrate with a future `artisanpack-ui/privacy` package:

```php
// Integration hooks in AnalyticsService

class AnalyticsService
{
    public function trackPageView(PageViewData $data): void
    {
        // Check with privacy package if available
        if ($this->shouldBlockTracking()) {
            return;
        }

        // ... normal tracking logic
    }

    protected function shouldBlockTracking(): bool
    {
        // Future privacy package integration
        if (app()->bound('privacy')) {
            return !app('privacy')->canTrack('analytics');
        }

        // Fallback to local consent check
        if (config('analytics.privacy.require_consent')) {
            return !$this->hasLocalConsent();
        }

        return false;
    }

    protected function hasLocalConsent(): bool
    {
        // Check stored consent
        return app(ConsentService::class)->hasConsent(
            $this->getCurrentVisitorId(),
            'analytics'
        );
    }
}
```

### Privacy Package Interface (Future)

```php
// Expected interface for privacy package

interface PrivacyManagerInterface
{
    public function canTrack(string $category): bool;
    public function hasConsent(string $category): bool;
    public function grantConsent(array $categories): void;
    public function revokeConsent(array $categories): void;
    public function getConsentStatus(): array;
    public function isGdprRegion(): bool;
    public function shouldShowConsentBanner(): bool;
}
```

---

## Related Documents

- [01-architecture.md](./01-architecture.md) - Overall architecture
- [02-database-schema.md](./02-database-schema.md) - Database tables
- [03-local-analytics-engine.md](./03-local-analytics-engine.md) - Tracking implementation

---

*Last Updated: January 3, 2026*
