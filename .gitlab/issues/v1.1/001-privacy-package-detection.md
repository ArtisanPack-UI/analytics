# Detect and integrate with artisanpack-ui/privacy package

/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"v1.1"

## Problem Statement

When `artisanpack-ui/privacy` is installed alongside Analytics, the two packages should work together seamlessly rather than having duplicate consent management.

## Proposed Solution

Implement automatic detection of the Privacy package and defer consent handling to it.

## Acceptance Criteria

- [ ] `privacyPackageInstalled()` detection method
- [ ] Auto-disable Analytics consent banner when Privacy is present
- [ ] Register Privacy integration in service provider boot
- [ ] Configuration option to force Analytics consent (override)
- [ ] Documentation for integration behavior
- [ ] Unit tests for detection logic
- [ ] Feature tests for integration behavior

## Use Cases

1. Developer installs both packages
2. Analytics automatically detects Privacy
3. Analytics defers consent to Privacy
4. Single consent banner shown to users

## Additional Context

Detection:
```php
protected function privacyPackageInstalled(): bool
{
    return class_exists(\ArtisanPackUI\Privacy\PrivacyServiceProvider::class);
}
```

---

**Related Issues:**
- #002 (Privacy Event Listeners)
- #003 (Consent Check Integration)
