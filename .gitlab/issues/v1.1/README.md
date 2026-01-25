# ArtisanPack UI Analytics Package - v1.1 Issues

This directory contains GitLab issue templates for the Analytics package v1.1 release, focusing on Privacy package integration.

## How to Use

1. Navigate to the GitLab repository
2. Create a new issue
3. Copy the content from the appropriate issue file
4. Adjust labels and assignees as needed

## Issue Summary

| # | Issue | Priority | Area |
|---|-------|----------|------|
| 001 | Privacy Package Detection | High | Backend |
| 002 | Privacy Event Listeners | High | Backend |
| 003 | Consent Check Integration | High | Backend |
| 004 | JavaScript Privacy Integration | High | Frontend |
| 005 | Data Deletion Integration | High | Backend |
| 006 | Data Export Integration | Medium | Backend |
| 007 | Register Cookies with Privacy | Medium | Backend |
| 008 | Privacy Configuration Options | Medium | Backend |
| 009 | Documentation | Medium | Docs |

## Total: 9 Issues

### By Priority
- **High**: 5 issues
- **Medium**: 4 issues

## v1.1 Release Focus

The v1.1 release is focused entirely on integrating with the `artisanpack-ui/privacy` package. Key features:

1. **Automatic Detection** - Analytics detects when Privacy is installed
2. **Unified Consent** - Analytics defers consent handling to Privacy
3. **Event Integration** - Analytics responds to Privacy consent events
4. **Data Subject Rights** - Analytics contributes to data exports and deletions
5. **Cookie Registration** - Analytics cookies listed in Privacy preferences

## Dependencies

This release depends on `artisanpack-ui/privacy` v1.0 being available. The integration is optional - Analytics continues to work standalone when Privacy is not installed.

## Labels Used

- `Type::Feature` - New feature
- `Type::Documentation` - Documentation task
- `Status::Backlog` - Not yet started
- `Priority::High` - Critical for release
- `Priority::Medium` - Important but not blocking
- `v1.1` - Version milestone
- `Area::Frontend` - UI/JavaScript work
- `Area::Backend` - Service/logic work
