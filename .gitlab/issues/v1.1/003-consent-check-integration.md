# Use Privacy package for consent checks

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"v1.1"

## Problem Statement

Analytics has its own consent checking, but when Privacy is installed, it should use Privacy's consent state as the source of truth.

## Proposed Solution

Modify `TrackingService` to check Privacy consent when Privacy package is available.

## Acceptance Criteria

- [ ] `shouldTrack()` checks Privacy consent when available
- [ ] Falls back to Analytics consent when Privacy not installed
- [ ] Configurable consent category to check
- [ ] Handle guest (cookie-based) consent
- [ ] Handle authenticated user consent
- [ ] Performance: Use cached consent when available
- [ ] Unit tests for both scenarios

## Use Cases

1. Privacy installed: `shouldTrack()` calls `privacyHasConsent('analytics')`
2. Privacy not installed: `shouldTrack()` uses internal consent check
3. User has consent in Privacy → tracking allowed

## Additional Context

```php
// In TrackingService
public function shouldTrack(): bool
{
    if ($this->privacyIntegration) {
        return privacyHasConsent(config('analytics.privacy.consent_category', 'analytics'));
    }

    return $this->hasOwnConsent();
}
```

---

**Related Issues:**
- #001 (Privacy Package Detection)
