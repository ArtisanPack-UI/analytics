# Add Privacy integration configuration options

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"v1.1"

## Problem Statement

Developers need configuration options to control how Analytics integrates with the Privacy package.

## Proposed Solution

Add `privacy` configuration section to `config/artisanpack/analytics.php`.

## Acceptance Criteria

- [ ] `privacy.use_privacy_package` option (auto-detected by default)
- [ ] `privacy.consent_category` option (default: 'analytics')
- [ ] `privacy.delete_on_optout` option (default: false)
- [ ] `privacy.contribute_to_export` option (default: true)
- [ ] Environment variable support for all options
- [ ] Options documented in config file
- [ ] Options documented in README

## Use Cases

1. Developer changes consent category name
2. Developer enables data deletion on opt-out
3. Developer disables Privacy integration despite package being installed

## Additional Context

```php
// config/artisanpack/analytics.php
'privacy' => [
    // Auto-detected, but can be overridden
    'use_privacy_package' => env('ANALYTICS_USE_PRIVACY_PACKAGE', null),

    // The consent category to check in Privacy package
    'consent_category' => env('ANALYTICS_CONSENT_CATEGORY', 'analytics'),

    // Delete Analytics data when user opts out
    'delete_on_optout' => env('ANALYTICS_DELETE_ON_OPTOUT', false),

    // Contribute data to Privacy data exports
    'contribute_to_export' => env('ANALYTICS_CONTRIBUTE_TO_EXPORT', true),
],
```

---

**Related Issues:**
All other v1.1 issues
