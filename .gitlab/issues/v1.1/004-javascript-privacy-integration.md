# Integrate JavaScript tracker with Privacy consent API

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"v1.1" ~"Area::Frontend"

## Problem Statement

The Analytics JavaScript tracker needs to respect Privacy package consent on the frontend.

## Proposed Solution

Update `analytics.js` to check `window.PrivacyConsent` before tracking.

## Acceptance Criteria

- [ ] Check `window.PrivacyConsent.hasConsent('analytics')` before tracking
- [ ] Listen for `privacy:consent-updated` events
- [ ] Enable/disable tracking based on consent changes
- [ ] Queue events during consent decision
- [ ] Flush queued events on consent grant
- [ ] Discard queued events on consent denial
- [ ] Fallback to own consent when Privacy not present
- [ ] TypeScript definitions updated
- [ ] Unit tests for JavaScript

## Use Cases

1. Page loads, Privacy consent pending → events queued
2. User grants consent → queued events sent, tracking enabled
3. User revokes consent mid-session → tracking disabled

## Additional Context

```javascript
const analytics = {
    track: function(event, data) {
        if (window.PrivacyConsent && !window.PrivacyConsent.hasConsent('analytics')) {
            return; // Don't track
        }
        this._sendEvent(event, data);
    }
};

window.addEventListener('privacy:consent-updated', function(e) {
    if (e.detail.category === 'analytics') {
        e.detail.granted ? analytics.enable() : analytics.disable();
    }
});
```

---

**Related Issues:**
- #003 (Consent Check Integration)
