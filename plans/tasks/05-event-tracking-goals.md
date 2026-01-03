Feature Name: Event Tracking, Goals & Conversions
Requested By: Internal
Owned By: TBD

## What is the Feature

Implement the event tracking system, goal configuration, conversion tracking, funnel analysis, and package integrations for tracking form submissions, ecommerce events, and booking events.

## Tasks

### Event Tracking API

- [ ] Create JavaScript event tracking API (`APAnalytics.trackEvent()`)
- [ ] Create PHP event tracking API (`Analytics::event()`)
- [ ] Implement event category/action/label structure
- [ ] Implement event value tracking
- [ ] Create `EventProcessor` service for processing events
- [ ] Add event schema validation (optional)
- [ ] Implement automatic event category inference

### Standard Event Types

- [ ] Define core events (page_view, session_start, session_end, click, scroll, etc.)
- [ ] Define form events (form_view, form_start, form_submit, form_error)
- [ ] Define ecommerce events (product_view, add_to_cart, purchase, refund)
- [ ] Define booking events (service_view, booking_start, booking_created, booking_cancelled)
- [ ] Create event type constants/enums

### Goal System

- [ ] Implement Goal model with match conditions
- [ ] Support event-based goals (match event name/category/properties)
- [ ] Support pageview-based goals (match URL patterns)
- [ ] Support duration-based goals (session duration threshold)
- [ ] Support pages-per-session goals
- [ ] Implement operator matching (equals, contains, regex, gt, lt, etc.)
- [ ] Create `GoalMatcher` service for checking goal conditions
- [ ] Implement dynamic value extraction from events

### Conversion Tracking

- [ ] Create conversion recording logic
- [ ] Prevent duplicate conversions per session (configurable)
- [ ] Track conversion value (fixed or dynamic)
- [ ] Fire `GoalConverted` event for listeners
- [ ] Create conversion reporting queries

### Funnel Analysis

- [ ] Implement funnel steps in Goal model
- [ ] Create `FunnelAnalyzer` service
- [ ] Calculate step-by-step conversion rates
- [ ] Calculate drop-off rates between steps
- [ ] Calculate overall funnel conversion rate

### Package Integrations

- [ ] Create integration guide for Forms package
- [ ] Create integration guide for Ecommerce package
- [ ] Create integration guide for Booking package
- [ ] Add source package tagging to events

## Accessibility Notes

N/A - Backend tracking only

## UX Notes

- Event tracking should be non-blocking
- Goals should be easy to configure via UI or code
- Provide clear feedback on goal matches

## Testing Notes

- [ ] Test event tracking via JavaScript
- [ ] Test event tracking via PHP
- [ ] Test GoalMatcher with various conditions
- [ ] Test all operator types (equals, contains, regex, etc.)
- [ ] Test conversion recording
- [ ] Test duplicate prevention
- [ ] Test funnel analysis calculations

## Documentation Notes

- [ ] Document JavaScript event API
- [ ] Document PHP event API
- [ ] Document standard event types
- [ ] Document goal configuration
- [ ] Document funnel setup
- [ ] Document package integration

## Related Planning Documents

- [06-event-tracking.md](../06-event-tracking.md)
