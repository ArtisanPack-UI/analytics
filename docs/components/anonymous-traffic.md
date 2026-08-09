---
title: Anonymous Traffic
---

# Anonymous Traffic Component

> **Since 1.5.0**

The Anonymous Traffic component surfaces the page views collected before consent
by [anonymous mode](Advanced-Privacy-Consent). It shows the anonymous share of
total page views, the pages and referring hosts behind it, the device-class
split, and a trend.

Everything on this component is a count of page views. Anonymous rows carry no
visitor, session or fingerprint, so there is deliberately no visitor, session,
bounce or duration figure here — those numbers do not exist for this traffic,
and showing a zero would imply they did.

## Basic Usage

```blade
<livewire:artisanpack-analytics::widgets.anonymous-traffic />
```

The component is also rendered by the main dashboard under an **Anonymous** tab,
which appears only once anonymous rows have been collected.

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `dateRangePreset` | ?string | config default | Date range preset |
| `siteId` | ?int | `null` | Site ID for multi-tenant |
| `limit` | int | `10` | Maximum pages and hosts to display (1–100) |

## Usage Examples

### With a Custom Limit

```blade
<livewire:artisanpack-analytics::widgets.anonymous-traffic
    :limit="5"
/>
```

### Scoped to a Site

```blade
<livewire:artisanpack-analytics::widgets.anonymous-traffic
    :site-id="1"
/>
```

## Exposed Data

The component populates the following public properties from
`AnalyticsQuery::getAnonymousStats()`:

| Property | Type | Description |
|----------|------|-------------|
| `enabled` | bool | Whether `privacy.anonymous_mode` is on |
| `anonymousPageviews` | int | Page views recorded before consent |
| `identifiedPageviews` | int | Page views from consented visitors |
| `totalPageviews` | int | The two added together |
| `anonymousPercentage` | float | Anonymous share of all page views |
| `topPages` | Collection | Top pages by anonymous page views |
| `referringHosts` | Collection | Top referring hosts by anonymous page views |
| `deviceBreakdown` | Collection | Anonymous device-class split |
| `trend` | array | Anonymous page views over time |

## Empty States

The component distinguishes two kinds of nothing, because they mean different
things:

- **Anonymous mode is off.** Pre-consent visitors are not counted at all, and
  the component says so rather than reporting zero.
- **Anonymous mode is on with no rows for the period.** Nothing was collected,
  which is an absence rather than a fault.

## Relationship to the Dashboard Toggle

The main dashboard's **Include anonymous traffic** toggle folds anonymous page
views into the page-view figures across the dashboard. It appears only when
there is anonymous traffic to include, and every metric that cannot include it
— visitors, sessions, bounce rate, session duration, pages per session, active
now, and traffic sources — is labelled while it is on.

See [Privacy & Consent](Advanced-Privacy-Consent) for the full table of which
metrics can include anonymous traffic and why the rest cannot.

## Accessibility

- The dashboard toggle is a labelled control with `aria-pressed` reflecting its
  state.
- Toggling updates a polite live region describing the current traffic scope, so
  a screen reader user is not left on stale figures.
- The trend sparkline is exposed as an image with a descriptive label, and the
  device split states its percentages in text rather than relying on bar length
  or colour alone.
