Feature Name: Database Schema & Models
Requested By: Internal
Owned By: TBD

## What is the Feature

Create all database migrations and Eloquent models for storing analytics data including visitors, sessions, page views, events, goals, conversions, consents, and aggregates.

## Tasks

### Migrations

- [ ] Create `analytics_visitors` migration
- [ ] Create `analytics_sessions` migration
- [ ] Create `analytics_page_views` migration
- [ ] Create `analytics_events` migration
- [ ] Create `analytics_goals` migration
- [ ] Create `analytics_conversions` migration
- [ ] Create `analytics_consents` migration
- [ ] Create `analytics_aggregates` migration
- [ ] Create `analytics_sites` migration (for multi-tenant support)

### Eloquent Models

- [ ] Create `Visitor` model with relationships and scopes
- [ ] Create `Session` model with relationships and scopes
- [ ] Create `PageView` model with relationships and scopes
- [ ] Create `Event` model with relationships and scopes
- [ ] Create `Goal` model with match condition logic
- [ ] Create `Conversion` model with relationships
- [ ] Create `Consent` model with active/expired scopes
- [ ] Create `Aggregate` model with metric queries
- [ ] Create `Site` model (for multi-tenant support)

### Traits

- [ ] Create `BelongsToSite` trait for multi-tenant data isolation

## Accessibility Notes

N/A - Database layer only

## UX Notes

N/A - Database layer only

## Testing Notes

- [ ] Test all migrations run successfully
- [ ] Test all migrations can be rolled back
- [ ] Test model relationships
- [ ] Test model scopes
- [ ] Test Goal match condition logic
- [ ] Test `BelongsToSite` trait automatic scoping

## Documentation Notes

- [ ] Document database schema with ERD
- [ ] Document model relationships
- [ ] Document available scopes

## Related Planning Documents

- [02-database-schema.md](../02-database-schema.md)
