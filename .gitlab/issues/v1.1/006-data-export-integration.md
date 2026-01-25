# Contribute to Privacy data exports

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"v1.1"

## Problem Statement

When a user requests data export through Privacy, their Analytics data should be included in the export.

## Proposed Solution

Listen for Privacy data export events and contribute Analytics data.

## Acceptance Criteria

- [ ] Listen for `ArtisanPackUI\Privacy\Events\DataExportRequested`
- [ ] Collect all analytics data for the user
- [ ] Include page views with timestamps
- [ ] Include sessions with metadata
- [ ] Include tracked events
- [ ] Format data according to export format
- [ ] Add data to export via event callback
- [ ] Unit tests for data collection
- [ ] Feature test for full flow

## Use Cases

1. User submits export request via Privacy
2. Privacy fires DataExportRequested event
3. Analytics contributes its data to export
4. User receives complete data package

## Additional Context

```php
Event::listen(DataExportRequested::class, function ($event) {
    $event->addData('analytics', [
        'page_views' => Analytics::getPageViewsForUser($event->user),
        'sessions' => Analytics::getSessionsForUser($event->user),
        'events' => Analytics::getEventsForUser($event->user),
    ]);
});
```

---

**Related Issues:**
- #002 (Privacy Event Listeners)
