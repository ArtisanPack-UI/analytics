# Event Tracking, Goals & Conversions

**Purpose:** Define custom event tracking, goal configuration, conversion tracking, and package integrations
**Last Updated:** January 3, 2026

---

## Overview

The event tracking system provides:

1. **Custom Events** - Track any user interaction with flexible properties
2. **Automatic Events** - Integration with forms, ecommerce, and booking packages
3. **Goals** - Define conversion goals with various trigger conditions
4. **Conversions** - Track when visitors complete goals
5. **Funnel Analysis** - Multi-step conversion tracking

---

## Event Tracking

### JavaScript Event API

```javascript
// Basic event tracking
ArtisanPackAnalytics.event('button_clicked', {
    button_id: 'cta-hero',
    button_text: 'Get Started',
});

// Event with value
ArtisanPackAnalytics.event('purchase', {
    order_id: 'ORD-12345',
    total: 99.99,
    products: ['product-1', 'product-2'],
}, { value: 99.99, category: 'ecommerce' });

// Shorthand helpers
ArtisanPackAnalytics.track.click('cta-hero');
ArtisanPackAnalytics.track.formSubmit('contact-form');
ArtisanPackAnalytics.track.purchase('ORD-12345', 99.99);
ArtisanPackAnalytics.track.addToCart('product-1');
```

### PHP Event API

```php
use ArtisanPackUI\Analytics\Facades\Analytics;

// Basic event
Analytics::event('user_registered', [
    'plan' => 'premium',
    'referral_code' => 'FRIEND10',
]);

// Event with category and value
Analytics::event('purchase', [
    'order_id' => $order->order_number,
    'products' => $order->items->pluck('product_id')->toArray(),
], category: 'ecommerce', value: $order->total);

// Package-specific event (auto-tagged with source)
Analytics::event('form_submitted', [
    'form_id' => $form->id,
    'form_name' => $form->name,
], sourcePackage: 'forms');
```

---

## Standard Event Types

### Core Events

| Event Name | Description | Properties |
|------------|-------------|------------|
| `page_view` | Page was viewed | path, title, referrer |
| `session_start` | New session started | entry_page, referrer |
| `session_end` | Session ended | exit_page, duration, page_count |
| `click` | Element clicked | element, element_id, element_text |
| `scroll` | Scroll milestone | depth (25, 50, 75, 100) |
| `search` | Site search | query, results_count |
| `download` | File downloaded | file_name, file_type, file_size |
| `outbound_link` | External link clicked | url, link_text |
| `video_play` | Video started | video_id, video_title |
| `video_complete` | Video finished | video_id, watch_time |

### Form Events

| Event Name | Description | Properties |
|------------|-------------|------------|
| `form_view` | Form was viewed | form_id, form_name |
| `form_start` | User started filling form | form_id, form_name |
| `form_submit` | Form was submitted | form_id, form_name, submission_id |
| `form_error` | Form submission failed | form_id, error_type |

### Ecommerce Events

| Event Name | Description | Properties |
|------------|-------------|------------|
| `product_view` | Product page viewed | product_id, product_name, price |
| `add_to_cart` | Product added to cart | product_id, quantity, price |
| `remove_from_cart` | Product removed from cart | product_id, quantity |
| `begin_checkout` | Checkout started | cart_total, item_count |
| `add_payment_info` | Payment info added | payment_method |
| `purchase` | Order completed | order_id, total, currency, items |
| `refund` | Order refunded | order_id, amount |

### Booking Events

| Event Name | Description | Properties |
|------------|-------------|------------|
| `service_view` | Service page viewed | service_id, service_name |
| `booking_start` | Booking flow started | service_id |
| `time_selected` | Time slot selected | service_id, datetime |
| `booking_created` | Booking completed | booking_id, service_id, amount |
| `booking_cancelled` | Booking cancelled | booking_id, reason |

---

## Event Processing

### Event Processor Service

```php
// src/Services/EventProcessor.php

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Events\EventTracked;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;

class EventProcessor
{
    public function __construct(
        protected GoalMatcher $goalMatcher,
        protected AnalyticsManager $providers,
    ) {}

    public function process(EventData $data): Event
    {
        // Find visitor and session
        $visitor = Visitor::firstWhere('fingerprint', $data->visitor_id);
        $session = $data->session_id
            ? Session::firstWhere('session_id', $data->session_id)
            : null;

        // Validate event schema if defined
        $this->validateEventSchema($data);

        // Create event record
        $event = Event::create([
            'site_id' => $this->getSiteId(),
            'session_id' => $session?->id,
            'visitor_id' => $visitor?->id,
            'name' => $data->name,
            'category' => $data->category ?? $this->inferCategory($data->name),
            'action' => $data->action,
            'label' => $data->label,
            'properties' => $data->properties,
            'value' => $data->value,
            'source_package' => $data->source_package,
        ]);

        // Update visitor stats
        if ($visitor) {
            $visitor->increment('total_events');
        }

        // Check for goal matches
        $this->goalMatcher->matchEvent($event, $session, $visitor);

        // Fire event for listeners
        event(new EventTracked($event, $session, $visitor));

        // Send to external providers
        $this->providers->trackEvent($data);

        return $event;
    }

    protected function validateEventSchema(EventData $data): void
    {
        $schema = config("analytics.events.schema.{$data->name}");

        if (!$schema) {
            return; // No schema defined, allow any properties
        }

        foreach ($schema['required'] ?? [] as $field) {
            if (!isset($data->properties[$field])) {
                throw new \InvalidArgumentException(__('Missing required property: :field', ['field' => $field]));
            }
        }
    }

    protected function inferCategory(string $eventName): ?string
    {
        return match(true) {
            str_starts_with($eventName, 'form_') => 'forms',
            str_starts_with($eventName, 'product_') || in_array($eventName, ['add_to_cart', 'purchase', 'refund']) => 'ecommerce',
            str_starts_with($eventName, 'booking_') || $eventName === 'service_view' => 'booking',
            str_starts_with($eventName, 'video_') => 'engagement',
            in_array($eventName, ['click', 'scroll', 'download', 'search']) => 'engagement',
            default => null,
        };
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

## Goal System

### Goal Model

```php
// src/Models/Goal.php

namespace ArtisanPackUI\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Goal extends Model
{
    protected $table = 'analytics_goals';

    protected $fillable = [
        'site_id', 'name', 'description', 'type',
        'conditions', 'value_type', 'fixed_value', 'dynamic_value_path',
        'is_active', 'funnel_steps',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'funnel_steps' => 'array',
            'is_active' => 'boolean',
            'fixed_value' => 'decimal:4',
        ];
    }

    // Goal Types
    public const TYPE_EVENT = 'event';
    public const TYPE_PAGEVIEW = 'pageview';
    public const TYPE_DURATION = 'duration';
    public const TYPE_PAGES = 'pages_per_session';

    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }

    /**
     * Check if a subject matches this goal's conditions.
     */
    public function matches(Event|PageView|Session $subject): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return match($this->type) {
            self::TYPE_EVENT => $this->matchesEventConditions($subject),
            self::TYPE_PAGEVIEW => $this->matchesPageViewConditions($subject),
            self::TYPE_DURATION => $this->matchesDurationConditions($subject),
            self::TYPE_PAGES => $this->matchesPagesConditions($subject),
            default => false,
        };
    }

    protected function matchesEventConditions(Event $event): bool
    {
        $conditions = $this->conditions;

        // Check event name
        if (isset($conditions['event_name'])) {
            if ($conditions['event_name'] !== $event->name) {
                return false;
            }
        }

        // Check category
        if (isset($conditions['event_category'])) {
            if ($conditions['event_category'] !== $event->category) {
                return false;
            }
        }

        // Check property matches
        if (isset($conditions['property_matches'])) {
            foreach ($conditions['property_matches'] as $key => $value) {
                $eventValue = data_get($event->properties, $key);

                if (is_array($value)) {
                    // Operator-based matching
                    if (!$this->matchesOperator($eventValue, $value)) {
                        return false;
                    }
                } else {
                    // Exact match
                    if ($eventValue != $value) {
                        return false;
                    }
                }
            }
        }

        // Check minimum value
        if (isset($conditions['min_value'])) {
            if (($event->value ?? 0) < $conditions['min_value']) {
                return false;
            }
        }

        return true;
    }

    protected function matchesPageViewConditions(PageView $pageView): bool
    {
        $conditions = $this->conditions;

        // Exact path match
        if (isset($conditions['path_exact'])) {
            return $pageView->path === $conditions['path_exact'];
        }

        // Pattern match (wildcards)
        if (isset($conditions['path_pattern'])) {
            return Str::is($conditions['path_pattern'], $pageView->path);
        }

        // Regex match
        if (isset($conditions['path_regex'])) {
            return preg_match($conditions['path_regex'], $pageView->path) === 1;
        }

        // Contains match
        if (isset($conditions['path_contains'])) {
            return str_contains($pageView->path, $conditions['path_contains']);
        }

        return false;
    }

    protected function matchesDurationConditions(Session $session): bool
    {
        $minSeconds = $this->conditions['min_seconds'] ?? 0;
        return $session->duration >= $minSeconds;
    }

    protected function matchesPagesConditions(Session $session): bool
    {
        $minPages = $this->conditions['min_pages'] ?? 0;
        return $session->page_count >= $minPages;
    }

    protected function matchesOperator($value, array $operator): bool
    {
        $op = array_key_first($operator);
        $target = $operator[$op];

        return match($op) {
            'equals', 'eq' => $value == $target,
            'not_equals', 'neq' => $value != $target,
            'greater_than', 'gt' => $value > $target,
            'less_than', 'lt' => $value < $target,
            'greater_or_equal', 'gte' => $value >= $target,
            'less_or_equal', 'lte' => $value <= $target,
            'contains' => str_contains((string)$value, $target),
            'starts_with' => str_starts_with((string)$value, $target),
            'ends_with' => str_ends_with((string)$value, $target),
            'in' => in_array($value, (array)$target),
            'not_in' => !in_array($value, (array)$target),
            'regex' => preg_match($target, (string)$value) === 1,
            default => false,
        };
    }

    /**
     * Calculate the conversion value for this goal.
     */
    public function calculateValue(Event|PageView|Session $subject): ?float
    {
        return match($this->value_type) {
            'fixed' => $this->fixed_value,
            'dynamic' => $this->extractDynamicValue($subject),
            default => null,
        };
    }

    protected function extractDynamicValue($subject): ?float
    {
        if (!$this->dynamic_value_path) {
            return null;
        }

        if ($subject instanceof Event) {
            return data_get($subject->properties, $this->dynamic_value_path);
        }

        return null;
    }
}
```

### Goal Condition Examples

```php
// Event-based goal: Form submission
$goal = Goal::create([
    'name' => 'Contact Form Submission',
    'type' => 'event',
    'conditions' => [
        'event_name' => 'form_submitted',
        'property_matches' => [
            'form_name' => 'Contact Form',
        ],
    ],
    'value_type' => 'fixed',
    'fixed_value' => 50.00, // $50 lead value
]);

// Event-based goal: Purchase over $100
$goal = Goal::create([
    'name' => 'High Value Purchase',
    'type' => 'event',
    'conditions' => [
        'event_name' => 'purchase',
        'min_value' => 100,
    ],
    'value_type' => 'dynamic',
    'dynamic_value_path' => 'total', // Get value from event properties.total
]);

// Page view goal: Thank you page
$goal = Goal::create([
    'name' => 'Reached Thank You Page',
    'type' => 'pageview',
    'conditions' => [
        'path_pattern' => '/thank-you*',
    ],
]);

// Duration goal: Engaged visitor
$goal = Goal::create([
    'name' => 'Engaged Visitor',
    'type' => 'duration',
    'conditions' => [
        'min_seconds' => 300, // 5 minutes
    ],
]);

// Pages per session goal: Exploratory visit
$goal = Goal::create([
    'name' => 'Exploratory Visit',
    'type' => 'pages_per_session',
    'conditions' => [
        'min_pages' => 5,
    ],
]);
```

---

## Goal Matcher Service

```php
// src/Services/GoalMatcher.php

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Events\GoalConverted;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;

class GoalMatcher
{
    protected ?int $siteId = null;

    public function __construct()
    {
        if (config('analytics.multi_tenant.enabled')) {
            $this->siteId = app('analytics.tenant')?->id;
        }
    }

    public function matchEvent(Event $event, ?Session $session, ?Visitor $visitor): void
    {
        $goals = Goal::query()
            ->where('type', Goal::TYPE_EVENT)
            ->where('is_active', true)
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->get();

        foreach ($goals as $goal) {
            if ($goal->matches($event)) {
                $this->recordConversion($goal, $event, $session, $visitor);
            }
        }
    }

    public function matchPageView(PageView $pageView, Session $session, Visitor $visitor): void
    {
        $goals = Goal::query()
            ->where('type', Goal::TYPE_PAGEVIEW)
            ->where('is_active', true)
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->get();

        foreach ($goals as $goal) {
            if ($goal->matches($pageView)) {
                $this->recordConversion($goal, $pageView, $session, $visitor);
            }
        }
    }

    public function matchSession(Session $session): void
    {
        $visitor = $session->visitor;

        // Check duration goals
        $durationGoals = Goal::query()
            ->where('type', Goal::TYPE_DURATION)
            ->where('is_active', true)
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->get();

        foreach ($durationGoals as $goal) {
            if ($goal->matches($session)) {
                $this->recordConversion($goal, $session, $session, $visitor);
            }
        }

        // Check pages per session goals
        $pagesGoals = Goal::query()
            ->where('type', Goal::TYPE_PAGES)
            ->where('is_active', true)
            ->when($this->siteId, fn($q) => $q->where('site_id', $this->siteId))
            ->get();

        foreach ($pagesGoals as $goal) {
            if ($goal->matches($session)) {
                $this->recordConversion($goal, $session, $session, $visitor);
            }
        }
    }

    protected function recordConversion(
        Goal $goal,
        Event|PageView|Session $trigger,
        ?Session $session,
        ?Visitor $visitor
    ): Conversion {
        // Check for duplicate conversion in same session
        $existingConversion = Conversion::query()
            ->where('goal_id', $goal->id)
            ->where('session_id', $session?->id)
            ->first();

        if ($existingConversion && !config('analytics.goals.allow_multiple_per_session', false)) {
            return $existingConversion;
        }

        $conversion = Conversion::create([
            'site_id' => $this->siteId,
            'goal_id' => $goal->id,
            'session_id' => $session?->id,
            'visitor_id' => $visitor?->id,
            'event_id' => $trigger instanceof Event ? $trigger->id : null,
            'page_view_id' => $trigger instanceof PageView ? $trigger->id : null,
            'value' => $goal->calculateValue($trigger),
            'metadata' => $this->extractMetadata($trigger),
        ]);

        // Fire event
        event(new GoalConverted($goal, $conversion, $session, $visitor));

        return $conversion;
    }

    protected function extractMetadata($trigger): array
    {
        if ($trigger instanceof Event) {
            return [
                'event_name' => $trigger->name,
                'event_properties' => $trigger->properties,
            ];
        }

        if ($trigger instanceof PageView) {
            return [
                'path' => $trigger->path,
                'title' => $trigger->title,
            ];
        }

        if ($trigger instanceof Session) {
            return [
                'duration' => $trigger->duration,
                'page_count' => $trigger->page_count,
            ];
        }

        return [];
    }
}
```

---

## Package Integrations

### Forms Package Integration

```php
// In artisanpack-ui/forms package

use ArtisanPackUI\Analytics\Facades\Analytics;

class FormRenderer extends Component
{
    public function submit(): void
    {
        // ... form processing ...

        // Track form view (if not already tracked)
        $this->trackFormSubmission();

        // ... rest of submission logic ...
    }

    protected function trackFormSubmission(): void
    {
        if (!class_exists(Analytics::class)) {
            return;
        }

        Analytics::event('form_submitted', [
            'form_id' => $this->form->id,
            'form_name' => $this->form->name,
            'form_slug' => $this->form->slug,
            'submission_id' => $this->submission?->id,
            'field_count' => count($this->form->fields),
        ], sourcePackage: 'forms');
    }
}
```

### Ecommerce Package Integration

```php
// In artisanpack-ui/ecommerce package

use ArtisanPackUI\Analytics\Facades\Analytics;

class CartService
{
    public function add(Product $product, int $quantity = 1): void
    {
        // ... add to cart logic ...

        $this->trackAddToCart($product, $quantity);
    }

    protected function trackAddToCart(Product $product, int $quantity): void
    {
        if (!class_exists(Analytics::class)) {
            return;
        }

        Analytics::event('add_to_cart', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $quantity,
            'price' => $product->price,
        ], category: 'ecommerce', value: $product->price * $quantity, sourcePackage: 'ecommerce');
    }
}

class CheckoutService
{
    public function completeOrder(...): Order
    {
        // ... order completion logic ...

        $this->trackPurchase($order);

        return $order;
    }

    protected function trackPurchase(Order $order): void
    {
        if (!class_exists(Analytics::class)) {
            return;
        }

        Analytics::event('purchase', [
            'order_id' => $order->order_number,
            'total' => $order->total,
            'subtotal' => $order->subtotal,
            'tax' => $order->tax,
            'shipping' => $order->shipping,
            'currency' => $order->currency,
            'item_count' => $order->items->sum('quantity'),
            'items' => $order->items->map(fn($item) => [
                'product_id' => $item->product_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'price' => $item->price,
                'quantity' => $item->quantity,
            ])->toArray(),
        ], category: 'ecommerce', value: $order->total, sourcePackage: 'ecommerce');
    }
}
```

### Booking Package Integration

```php
// In artisanpack-ui/booking package

use ArtisanPackUI\Analytics\Facades\Analytics;

class BookingService
{
    public function createBooking(...): Booking
    {
        // ... booking creation logic ...

        $this->trackBooking($booking);

        return $booking;
    }

    protected function trackBooking(Booking $booking): void
    {
        if (!class_exists(Analytics::class)) {
            return;
        }

        Analytics::event('booking_created', [
            'booking_id' => $booking->booking_number,
            'service_id' => $booking->service_id,
            'service_name' => $booking->service->name,
            'provider_id' => $booking->provider_id,
            'datetime' => $booking->start_time->toIso8601String(),
            'duration' => $booking->service->duration,
            'amount' => $booking->service->price,
        ], category: 'booking', value: $booking->service->price, sourcePackage: 'booking');
    }
}
```

---

## Funnel Analysis

For multi-step conversion tracking:

```php
// Goal with funnel steps
$goal = Goal::create([
    'name' => 'Checkout Funnel',
    'type' => 'event',
    'conditions' => [
        'event_name' => 'purchase',
    ],
    'funnel_steps' => [
        ['name' => 'Product Viewed', 'type' => 'event', 'event_name' => 'product_view'],
        ['name' => 'Added to Cart', 'type' => 'event', 'event_name' => 'add_to_cart'],
        ['name' => 'Checkout Started', 'type' => 'event', 'event_name' => 'begin_checkout'],
        ['name' => 'Payment Added', 'type' => 'event', 'event_name' => 'add_payment_info'],
        ['name' => 'Purchase Complete', 'type' => 'event', 'event_name' => 'purchase'],
    ],
    'value_type' => 'dynamic',
    'dynamic_value_path' => 'total',
]);
```

### Funnel Query Service

```php
// src/Services/FunnelAnalyzer.php

class FunnelAnalyzer
{
    public function analyze(Goal $goal, DateRange $range): array
    {
        if (!$goal->funnel_steps) {
            throw new \InvalidArgumentException(__('Goal does not have funnel steps'));
        }

        $steps = collect($goal->funnel_steps);
        $results = [];

        $previousVisitors = null;

        foreach ($steps as $index => $step) {
            $visitors = $this->getVisitorsForStep($step, $range);

            $results[] = [
                'name' => $step['name'],
                'visitors' => $visitors->count(),
                'conversion_rate' => $previousVisitors
                    ? round(($visitors->count() / $previousVisitors->count()) * 100, 2)
                    : 100,
                'dropoff_rate' => $previousVisitors
                    ? round((($previousVisitors->count() - $visitors->count()) / $previousVisitors->count()) * 100, 2)
                    : 0,
            ];

            $previousVisitors = $visitors;
        }

        return [
            'steps' => $results,
            'overall_conversion' => $results[0]['visitors'] > 0
                ? round(($results[count($results) - 1]['visitors'] / $results[0]['visitors']) * 100, 2)
                : 0,
        ];
    }

    protected function getVisitorsForStep(array $step, DateRange $range): Collection
    {
        if ($step['type'] === 'event') {
            return Event::query()
                ->where('name', $step['event_name'])
                ->whereBetween('created_at', [$range->start, $range->end])
                ->distinct('visitor_id')
                ->pluck('visitor_id');
        }

        if ($step['type'] === 'pageview') {
            return PageView::query()
                ->where('path', 'LIKE', $step['path_pattern'])
                ->whereBetween('created_at', [$range->start, $range->end])
                ->distinct('visitor_id')
                ->pluck('visitor_id');
        }

        return collect();
    }
}
```

---

## Configuration

```php
// config/analytics.php (events section)

return [
    'events' => [
        // Event schema validation (optional)
        'schema' => [
            'purchase' => [
                'required' => ['order_id', 'total'],
            ],
            'form_submitted' => [
                'required' => ['form_id'],
            ],
        ],

        // Auto-track common events
        'auto_track' => [
            'outbound_links' => true,
            'file_downloads' => true,
            'scroll_depth' => true,
            'video_engagement' => false,
        ],
    ],

    'goals' => [
        // Allow multiple conversions per session
        'allow_multiple_per_session' => false,

        // Cache goal queries
        'cache_duration' => 300, // seconds
    ],
];
```

---

## Related Documents

- [01-architecture.md](./01-architecture.md) - Overall architecture
- [02-database-schema.md](./02-database-schema.md) - Database tables
- [05-dashboard-components.md](./05-dashboard-components.md) - Dashboard and reporting

---

*Last Updated: January 3, 2026*
