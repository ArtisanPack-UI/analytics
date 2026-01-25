# Respond to Privacy data deletion requests

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"v1.1"

## Problem Statement

When a user requests data deletion through the Privacy package, Analytics data for that user must also be deleted.

## Proposed Solution

Listen for Privacy data deletion events and delete Analytics data.

## Acceptance Criteria

- [ ] Listen for `ArtisanPackUI\Privacy\Events\DataDeletionRequested`
- [ ] Delete all analytics data for the user
- [ ] Delete page views, sessions, events
- [ ] Handle both user and visitor deletion
- [ ] Log deletion for audit trail
- [ ] Configurable retention of anonymized aggregates
- [ ] Unit tests for deletion logic
- [ ] Feature test for full flow

## Use Cases

1. User submits deletion request via Privacy
2. Privacy fires DataDeletionRequested event
3. Analytics deletes all user data
4. Aggregated stats may be retained (anonymized)

## Additional Context

```php
Event::listen(DataDeletionRequested::class, function ($event) {
    Analytics::deleteUserData($event->user);
});
```

Consider:
- What to do with historical aggregated data
- Whether to soft-delete or hard-delete
- How to handle visitor (cookie-based) deletion

---

**Related Issues:**
- #002 (Privacy Event Listeners)
