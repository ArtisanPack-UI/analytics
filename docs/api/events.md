---
title: Events
---

# Events

ArtisanPack UI Analytics dispatches Laravel events that you can listen for to extend functionality.

## Available Events

| Event | When Dispatched |
|-------|-----------------|
| `PageViewRecorded` | After a page view is tracked |
| `EventTracked` | After a custom event is tracked |
| `SessionStarted` | When a new session begins |
| `SessionEnded` | When a session ends |
| `ConsentGranted` | When consent is granted |
| `ConsentRevoked` | When consent is revoked |
| `GoalConverted` | When a goal conversion is recorded |

## PageViewRecorded

Dispatched after a page view is recorded.

### Payload

```php
use ArtisanPackUI\Analytics\Events\PageViewRecorded;

class PageViewRecorded
{
    public function __construct(
        public PageView $pageView,
        public Session $session,
        public Visitor $visitor,
    ) {}
}
```

### Listening

```php
// In EventServiceProvider
protected $listen = [
    \ArtisanPackUI\Analytics\Events\PageViewRecorded::class => [
        \App\Listeners\HandlePageView::class,
    ],
];
```

```php
// App\Listeners\HandlePageView
class HandlePageView
{
    public function handle(PageViewRecorded $event): void
    {
        // Access the page view
        $pageView = $event->pageView;

        // Check for specific pages
        if ($pageView->path === '/pricing') {
            // Track pricing page view
        }
    }
}
```

---

## EventTracked

Dispatched after a custom event is recorded.

### Payload

```php
use ArtisanPackUI\Analytics\Events\EventTracked;

class EventTracked
{
    public function __construct(
        public Event $event,
        public Session $session,
        public Visitor $visitor,
    ) {}
}
```

### Listening

```php
class HandleTrackedEvent
{
    public function handle(EventTracked $event): void
    {
        if ($event->event->name === 'purchase') {
            // Send to external service
            // Update inventory
            // Send notification
        }
    }
}
```

---

## SessionStarted

Dispatched when a new session begins.

### Payload

```php
use ArtisanPackUI\Analytics\Events\SessionStarted;

class SessionStarted
{
    public function __construct(
        public Session $session,
        public Visitor $visitor,
    ) {}
}
```

### Use Cases

```php
class HandleSessionStart
{
    public function handle(SessionStarted $event): void
    {
        // Log session start
        Log::info('New session started', [
            'session_id' => $event->session->session_id,
            'visitor' => $event->visitor->fingerprint,
            'entry_page' => $event->session->entry_page,
        ]);

        // Check for returning visitor
        if ($event->visitor->visit_count > 1) {
            // Returning visitor logic
        }
    }
}
```

---

## SessionEnded

Dispatched when a session ends (timeout or explicit end).

### Payload

```php
use ArtisanPackUI\Analytics\Events\SessionEnded;

class SessionEnded
{
    public function __construct(
        public Session $session,
        public Visitor $visitor,
    ) {}
}
```

### Use Cases

```php
class HandleSessionEnd
{
    public function handle(SessionEnded $event): void
    {
        // Calculate session metrics
        $duration = $event->session->duration;
        $pageViews = $event->session->page_views;
        $isBounce = $event->session->is_bounce;

        // Update aggregate statistics
        // Send session summary to external service
    }
}
```

---

## ConsentGranted

Dispatched when a visitor grants consent.

### Payload

```php
use ArtisanPackUI\Analytics\Events\ConsentGranted;

class ConsentGranted
{
    public function __construct(
        public Visitor $visitor,
        public array $categories, // ['analytics', 'marketing']
    ) {}
}
```

### Use Cases

```php
class HandleConsentGranted
{
    public function handle(ConsentGranted $event): void
    {
        // Enable tracking for this visitor
        if (in_array('marketing', $event->categories)) {
            // Enable marketing pixels
        }

        // Log consent for compliance
        Log::info('Consent granted', [
            'visitor' => $event->visitor->fingerprint,
            'categories' => $event->categories,
        ]);
    }
}
```

---

## ConsentRevoked

Dispatched when a visitor revokes consent.

### Payload

```php
use ArtisanPackUI\Analytics\Events\ConsentRevoked;

class ConsentRevoked
{
    public function __construct(
        public Visitor $visitor,
        public array $categories,
    ) {}
}
```

### Use Cases

```php
class HandleConsentRevoked
{
    public function handle(ConsentRevoked $event): void
    {
        // Disable tracking for this visitor
        // Optionally delete existing data

        if (in_array('marketing', $event->categories)) {
            // Remove from marketing lists
        }
    }
}
```

---

## GoalConverted

Dispatched when a goal conversion is recorded.

### Payload

```php
use ArtisanPackUI\Analytics\Events\GoalConverted;

class GoalConverted
{
    public function __construct(
        public Conversion $conversion,
        public Goal $goal,
        public Session $session,
        public Visitor $visitor,
    ) {}
}
```

### Use Cases

```php
class HandleGoalConversion
{
    public function handle(GoalConverted $event): void
    {
        // Send notification
        $goal = $event->goal;
        $value = $event->conversion->value;

        Notification::send(
            User::admins()->get(),
            new GoalConvertedNotification($goal, $value)
        );

        // Update CRM
        // Trigger follow-up actions
    }
}
```

---

## Registering Event Listeners

### Using EventServiceProvider

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \ArtisanPackUI\Analytics\Events\PageViewRecorded::class => [
        \App\Listeners\HandlePageView::class,
    ],
    \ArtisanPackUI\Analytics\Events\EventTracked::class => [
        \App\Listeners\HandleTrackedEvent::class,
    ],
    \ArtisanPackUI\Analytics\Events\GoalConverted::class => [
        \App\Listeners\HandleGoalConversion::class,
    ],
];
```

### Using Closures

```php
// In a service provider boot method
use ArtisanPackUI\Analytics\Events\PageViewRecorded;
use Illuminate\Support\Facades\Event;

Event::listen(PageViewRecorded::class, function ($event) {
    // Handle the event
});
```

### Using Subscribers

```php
class AnalyticsEventSubscriber
{
    public function handlePageView(PageViewRecorded $event): void
    {
        // ...
    }

    public function handleEvent(EventTracked $event): void
    {
        // ...
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            PageViewRecorded::class => 'handlePageView',
            EventTracked::class => 'handleEvent',
        ];
    }
}
```

## Queued Listeners

For heavy operations, queue your listeners:

```php
class HandlePageView implements ShouldQueue
{
    public function handle(PageViewRecorded $event): void
    {
        // Heavy processing is queued
    }
}
```
