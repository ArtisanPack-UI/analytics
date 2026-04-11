---
title: Frontend Components Overview
---

# Frontend Components Overview

> **Since 1.1.0**

ArtisanPack UI Analytics provides pre-built dashboard components for both React and Vue, enabling you to build analytics dashboards with Inertia.js alongside the existing Livewire dashboard.

## Available Frontend Stacks

| Stack | Dashboard | Consent | Hooks/Composables |
|-------|-----------|---------|-------------------|
| **Livewire** (default) | Blade + Livewire widgets | Blade component | N/A |
| **React** | React + TypeScript | React components | `useAnalyticsApi`, `useConsent` |
| **Vue** | Vue 3 SFCs + TypeScript | Vue components | `useAnalyticsApi`, `useConsent` |

## Architecture

The frontend components are designed around the Inertia.js integration. When `dashboard_driver` is set to `'inertia'`, the package registers dedicated controller routes that return Inertia responses with analytics data as typed page props.

```
Request
  -> InertiaDashboardController
    -> AnalyticsQuery service
    -> API Resources (serialize data)
    -> Inertia::render() with typed props
      -> React/Vue page component
        -> Widget components (StatsCards, VisitorsChart, etc.)
```

## Quick Start

1. Install frontend components:

```bash
php artisan analytics:install-frontend --stack=react
# or
php artisan analytics:install-frontend --stack=vue
```

2. Set the dashboard driver in `config/artisanpack/analytics.php`:

```php
'dashboard_driver' => 'inertia',
```

3. Install npm dependencies and compile:

```bash
npm install
npm run dev
```

See the [Installation Guide](Frontend-Installation) for detailed setup instructions.

## Component Categories

### Dashboard Widgets

Reusable analytics display components:

- **StatsCards** - Key metrics (pageviews, visitors, sessions, bounce rate)
- **VisitorsChart** - Line chart of visitors/pageviews over time
- **TopPages** - Table of most viewed pages
- **TrafficSources** - Traffic source breakdown
- **RealtimeVisitors** - Live visitor count with polling
- **SiteSelector** - Site picker for multi-tenant setups

### Dashboard Pages

Full-page Inertia components that compose widgets:

- **AnalyticsDashboard** - Main dashboard overview
- **PageAnalytics** - Per-page analytics detail
- **MultiTenantDashboard** - Multi-site management view

### Consent Components

GDPR-compliant consent management UI:

- **ConsentBanner** - Cookie consent banner with accept/reject/customize
- **ConsentPreferences** - Category-level consent toggles
- **ConsentStatus** - Compact consent status indicator

### Hooks & Composables

Shared logic for data fetching and consent management:

- **useAnalyticsApi** - Fetch analytics data with polling support
- **useConsent** - Consent state management with API sync

## TypeScript Types

All components are fully typed. Shared type definitions cover API responses, data models, enums, and component props. See the [TypeScript Types Reference](Frontend-TypeScript-Types) for details.

## Documentation

- [Installation](Frontend-Installation)
- [React Components](Frontend-React-Components)
- [Vue Components](Frontend-Vue-Components)
- [Consent Components](Frontend-Consent-Components)
- [Hooks & Composables](Frontend-Hooks-Composables)
- [TypeScript Types](Frontend-TypeScript-Types)
