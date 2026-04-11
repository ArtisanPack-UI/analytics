# Listen for Privacy package consent events

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"v1.1"

## Problem Statement

When users grant or revoke consent through the Privacy package, Analytics needs to react accordingly to start/stop tracking.

## Proposed Solution

Create event listeners for Privacy package consent events.

## Acceptance Criteria

- [ ] Listen for `ArtisanPackUI\Privacy\Events\ConsentGiven`
- [ ] Listen for `ArtisanPackUI\Privacy\Events\ConsentWithdrawn`
- [ ] Enable tracking when analytics consent granted
- [ ] Disable tracking when analytics consent revoked
- [ ] Optional: Delete existing data on opt-out
- [ ] Configurable consent category name
- [ ] Listeners only registered when Privacy installed
- [ ] Unit tests for listener behavior

## Use Cases

1. User grants analytics consent → tracking starts
2. User revokes analytics consent → tracking stops
3. User revokes consent with "delete my data" → data deleted

## Additional Context

```php
Event::listen(ConsentGiven::class, function ($event) {
    if ($event->consent->category === config('analytics.privacy.consent_category')) {
        Analytics::enableTracking($event->consent->consentable);
    }
});

Event::listen(ConsentWithdrawn::class, function ($event) {
    if ($event->consent->category === config('analytics.privacy.consent_category')) {
        Analytics::disableTracking($event->consent->consentable);
    }
});
```

---

**Related Issues:**
- #001 (Privacy Package Detection)
