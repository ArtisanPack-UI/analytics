---
title: Frontend Installation
---

# Frontend Installation

> **Since 1.1.0**

This guide covers installing the React or Vue analytics dashboard components for use with Inertia.js.

## Prerequisites

- ArtisanPack UI Analytics `^1.1`
- Laravel 11 or 12
- [Inertia.js](https://inertiajs.com/) installed and configured
- Node.js 18+

## Automated Installation

The `analytics:install-frontend` Artisan command handles publishing components and adding npm dependencies:

### React

```bash
php artisan analytics:install-frontend --stack=react
```

This publishes:
- React components to `resources/js/vendor/artisanpack-analytics/react/`
- Shared TypeScript types to `resources/js/vendor/artisanpack-analytics/types/`

And adds these npm dependencies to `package.json`:
- `@artisanpack-ui/react` ^1.0
- `@artisanpack-ui/tokens` ^1.0
- `react` ^18.0 || ^19.0
- `react-dom` ^18.0 || ^19.0
- `react-apexcharts` ^1.4
- `apexcharts` ^3.44

### Vue

```bash
php artisan analytics:install-frontend --stack=vue
```

This publishes:
- Vue SFC components to `resources/js/vendor/artisanpack-analytics/vue/`
- Shared TypeScript types to `resources/js/vendor/artisanpack-analytics/types/`

And adds these npm dependencies to `package.json`:
- `@artisanpack-ui/vue` ^1.0
- `@artisanpack-ui/tokens` ^1.0
- `vue` ^3.4
- `vue3-apexcharts` ^1.5
- `apexcharts` ^3.44

### Options

| Option | Description |
|--------|-------------|
| `--stack=react\|vue` | **Required.** The frontend framework to install. |
| `--force` | Overwrite previously published files. |

### After Installation

```bash
npm install
npm run dev
```

## Configuration

### Dashboard Driver

Set the dashboard driver to `'inertia'` in `config/artisanpack/analytics.php`:

```php
'dashboard_driver' => 'inertia',
```

This tells the package to register Inertia routes instead of relying on Livewire components for the dashboard.

### Inertia Page Components

Customize the Inertia page component names in the `inertia.pages` config section:

```php
'inertia' => [
    'pages' => [
        'dashboard' => 'Analytics/Dashboard',
        'pages'     => 'Analytics/Pages',
        'traffic'   => 'Analytics/Traffic',
        'audience'  => 'Analytics/Audience',
        'events'    => 'Analytics/Events',
        'realtime'  => 'Analytics/Realtime',
    ],
],
```

These values map to your Inertia page component paths (e.g., `resources/js/Pages/Analytics/Dashboard.tsx`).

### Dashboard Routes

The Inertia dashboard registers these routes automatically when `dashboard_driver` is `'inertia'`:

| Route | Name | Controller Method |
|-------|------|-------------------|
| `GET /analytics` | `analytics.dashboard` | `index` |
| `GET /analytics/pages` | `analytics.dashboard.pages` | `pages` |
| `GET /analytics/traffic` | `analytics.dashboard.traffic` | `traffic` |
| `GET /analytics/audience` | `analytics.dashboard.audience` | `audience` |
| `GET /analytics/events` | `analytics.dashboard.events` | `events` |
| `GET /analytics/realtime` | `analytics.dashboard.realtime` | `realtime` |

The route prefix is controlled by the `dashboard_route` config value (default: `'analytics'`).

All routes use the middleware defined in `dashboard_middleware` (default: `['web', 'auth']`).

## Manual Installation

If you prefer manual setup:

1. Publish the components for your framework:

```bash
# React
php artisan vendor:publish --tag=analytics-react

# Vue
php artisan vendor:publish --tag=analytics-vue
```

2. Manually add npm dependencies to your `package.json` and run `npm install`.

3. Set `dashboard_driver` to `'inertia'` in config.

4. Create your Inertia page components that import the published widgets.

## Republishing After Updates

When updating the analytics package, republish the frontend components to get the latest changes:

```bash
php artisan analytics:install-frontend --stack=react --force
# or
php artisan analytics:install-frontend --stack=vue --force
```

Review the CHANGELOG for any breaking changes to component props or APIs before republishing.

## Switching Between Livewire and Inertia

You can switch dashboard drivers at any time by changing the `dashboard_driver` config value. The tracking API, data collection, and backend remain identical regardless of the dashboard driver.

```php
// Use Livewire dashboard (default)
'dashboard_driver' => 'livewire',

// Use React/Vue dashboard via Inertia
'dashboard_driver' => 'inertia',
```
