# Document Privacy package integration

/label ~"Type::Documentation" ~"Status::Backlog" ~"Priority::Medium" ~"v1.1"

## Task Description

Create comprehensive documentation for the Privacy package integration feature.

## Acceptance Criteria

- [ ] Add Privacy Integration section to README
- [ ] Document automatic detection behavior
- [ ] Document consent flow with Privacy
- [ ] Document data deletion integration
- [ ] Document data export integration
- [ ] Document configuration options
- [ ] Document how to disable integration
- [ ] Add code examples
- [ ] Update CHANGELOG with v1.1 features

## Context

Developers need to understand how Analytics and Privacy work together.

## Notes

Documentation should cover:
1. What happens when both packages are installed
2. How consent flows between packages
3. How to configure the integration
4. How to troubleshoot common issues

Example documentation structure:
```markdown
## Privacy Package Integration

When `artisanpack-ui/privacy` is installed, Analytics automatically integrates with it:

### Automatic Behavior
- Analytics defers consent to Privacy's cookie banner
- Analytics checks Privacy consent before tracking
- Analytics responds to Privacy data subject requests

### Configuration
...
```

---

**Related Issues:**
All other v1.1 issues
