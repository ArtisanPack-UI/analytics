Feature Name: Privacy & GDPR Compliance
Requested By: Internal
Owned By: TBD

## What is the Feature

Implement privacy-first analytics features including IP anonymization, consent management, data retention, GDPR compliance (data export/deletion), and hooks for future privacy package integration.

## Tasks

### IP Anonymization

- [ ] Implement IPv4 last octet zeroing (192.168.1.123 -> 192.168.1.0)
- [ ] Implement IPv6 anonymization (zero last 80 bits)
- [ ] Add configurable user agent hashing
- [ ] Add optional screen resolution rounding
- [ ] Create privacy-preserving visitor fingerprinting (exclude IP)

### Consent Model & Service

- [ ] Create `Consent` model with granted/revoked tracking
- [ ] Implement `isActive()` method with expiration checking
- [ ] Implement `revoke()` method
- [ ] Create consent scopes (`active`, `forCategory`)
- [ ] Create `ConsentService` class
- [ ] Implement `hasConsent()` with privacy package integration hook
- [ ] Implement `grantConsent()` method
- [ ] Implement `revokeConsent()` method
- [ ] Implement `getConsentStatus()` method
- [ ] Add DNT (Do Not Track) header checking

### Consent Controller & Banner

- [ ] Create `ConsentController` with status endpoint
- [ ] Create `ConsentController` update endpoint
- [ ] Create `consent-banner` Blade component
- [ ] Implement Accept All functionality
- [ ] Implement Reject All functionality
- [ ] Implement Save Preferences functionality
- [ ] Add localStorage consent storage
- [ ] Integrate with JavaScript tracker consent methods

### Data Retention

- [ ] Create `CleanupOldData` queued job
- [ ] Implement page views cleanup
- [ ] Implement events cleanup
- [ ] Implement conversions cleanup
- [ ] Implement sessions cleanup
- [ ] Implement orphaned visitors cleanup
- [ ] Implement aggregates cleanup (separate retention period)
- [ ] Implement expired consents cleanup
- [ ] Add configurable retention periods
- [ ] Schedule cleanup job (daily at 03:00)

### GDPR Data Export (Article 15)

- [ ] Create `DataExportService` class
- [ ] Implement `exportVisitorData()` method
- [ ] Export visitor info (fingerprint, dates, location, device)
- [ ] Export sessions with page views and events
- [ ] Export consent history
- [ ] Implement `exportAsCsv()` method
- [ ] Add ISO 8601 timestamp formatting

### GDPR Data Deletion (Article 17)

- [ ] Create `DataDeletionService` class
- [ ] Implement `deleteVisitorData()` with cascade deletion
- [ ] Delete page views, events, sessions, consents
- [ ] Add transaction wrapper for data integrity
- [ ] Implement `anonymizeVisitorData()` as alternative
- [ ] Anonymize visitor record (null IP, user agent, location)
- [ ] Anonymize session referrers
- [ ] Add logging for audit trail

### Privacy Package Integration Hooks

- [ ] Add `app()->bound('privacy')` check in tracking
- [ ] Create `shouldBlockTracking()` method
- [ ] Implement fallback to local consent check
- [ ] Document expected `PrivacyManagerInterface`
- [ ] Add integration points for future privacy package

## Accessibility Notes

- [ ] Consent banner must be keyboard navigable
- [ ] Consent checkboxes must have proper labels
- [ ] Focus management when banner opens/closes
- [ ] Screen reader announcements for consent changes

## UX Notes

- Consent banner should appear only when required
- Preferences should be easy to change later
- Clear visual distinction between required and optional categories
- Non-intrusive positioning options (top, bottom, modal)

## Testing Notes

- [ ] Test IP anonymization for IPv4 and IPv6
- [ ] Test consent granting and revocation
- [ ] Test consent expiration
- [ ] Test DNT header respect
- [ ] Test data retention cleanup
- [ ] Test data export completeness
- [ ] Test data deletion cascade
- [ ] Test anonymization preserves aggregates
- [ ] Test privacy package integration hooks

## Documentation Notes

- [ ] Document privacy configuration options
- [ ] Document consent management API
- [ ] Document data retention policies
- [ ] Document GDPR compliance features
- [ ] Document data export/deletion endpoints
- [ ] Document privacy package integration

## Related Planning Documents

- [07-privacy-compliance.md](../07-privacy-compliance.md)
