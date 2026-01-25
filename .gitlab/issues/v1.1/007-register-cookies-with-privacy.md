# Register Analytics cookies with Privacy package

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"v1.1"

## Problem Statement

The Privacy package shows users which cookies each category uses. Analytics needs to register its cookies.

## Proposed Solution

Use Privacy filter hooks to register Analytics cookies in the analytics category.

## Acceptance Criteria

- [ ] Register `_ap_sid` (session) cookie with description
- [ ] Register `_ap_vid` (visitor) cookie with description
- [ ] Register consent cookie if using own consent
- [ ] Cookies appear in Privacy preferences UI
- [ ] Registration only when Privacy installed
- [ ] Unit tests for registration

## Use Cases

1. User opens cookie preferences
2. Sees Analytics cookies listed under Analytics category
3. Understands what each cookie does

## Additional Context

```php
addAction('privacy.cookies.register', function ($cookieRegistry) {
    $cookieRegistry->register('analytics', [
        config('analytics.session.cookie_name', '_ap_sid') => __('Session identifier for analytics tracking'),
        config('analytics.session.visitor_cookie_name', '_ap_vid') => __('Visitor identifier for returning visitor recognition'),
    ]);
});
```

---

**Related Issues:**
- #001 (Privacy Package Detection)
