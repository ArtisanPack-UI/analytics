---
title: Consent Components
---

# Consent Components

> **Since 1.1.0**

GDPR-compliant consent management components for React and Vue. These components provide a complete consent UI that syncs with the server-side `ConsentService` via API.

## Overview

Three components work together to provide a complete consent management experience:

| Component | Purpose |
|-----------|---------|
| **ConsentBanner** | Cookie consent bar shown to new visitors (accept/reject/customize) |
| **ConsentPreferences** | Detailed category-level consent toggles |
| **ConsentStatus** | Compact indicator showing current consent state |

All components use the `useConsent` hook (React) or composable (Vue) under the hood for state management and API synchronization.

## React Components

### ConsentBanner

A consent banner that appears at the top or bottom of the page for first-time visitors.

```tsx
import { ConsentBanner } from '@/vendor/artisanpack-analytics/react';

<ConsentBanner
    position="bottom"
    title="Cookie Consent"
    description="We use cookies to analyze site usage and improve your experience."
    acceptLabel="Accept All"
    rejectLabel="Reject All"
    customizeLabel="Customize"
    onConsentSaved={(categories) => console.log('Saved:', categories)}
/>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `position` | `'top' \| 'bottom'` | `'bottom'` | Banner position |
| `title` | `string` | `'Cookie Consent'` | Banner heading |
| `description` | `string` | — | Consent explanation text |
| `acceptLabel` | `string` | `'Accept All'` | Accept button label |
| `rejectLabel` | `string` | `'Reject All'` | Reject button label |
| `customizeLabel` | `string` | `'Customize'` | Customize button label |
| `saveLabel` | `string` | `'Save'` | Save preferences button label |
| `onConsentSaved` | `(categories: Record<string, boolean>) => void` | — | Callback after consent is saved |
| `className` | `string` | — | Additional CSS classes |
| `apiPrefix` | `string` | — | API endpoint prefix (from `UseConsentOptions`) |

### ConsentPreferences

A detailed preferences panel with per-category toggles.

```tsx
import { ConsentPreferences } from '@/vendor/artisanpack-analytics/react';

<ConsentPreferences
    title="Privacy Preferences"
    description="Choose which categories of cookies you'd like to allow."
    showBulkActions={true}
    onSaved={(categories) => console.log('Updated:', categories)}
/>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `string` | `'Privacy Preferences'` | Panel heading |
| `description` | `string` | — | Explanation text |
| `saveLabel` | `string` | `'Save'` | Save button label |
| `acceptAllLabel` | `string` | `'Accept All'` | Accept all button label |
| `rejectAllLabel` | `string` | `'Reject All'` | Reject all button label |
| `showBulkActions` | `boolean` | `true` | Show accept/reject all buttons |
| `onSaved` | `(categories: Record<string, boolean>) => void` | — | Callback after preferences are saved |
| `className` | `string` | — | Additional CSS classes |

### ConsentStatus

A compact status indicator with an optional "Manage" button.

```tsx
import { ConsentStatus } from '@/vendor/artisanpack-analytics/react';

<ConsentStatus
    label="Privacy Settings"
    showManageButton={true}
    manageLabel="Manage"
    onManageClick={() => setShowPreferences(true)}
/>
```

**Props:**

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | `string` | `'Privacy Settings'` | Status label |
| `showManageButton` | `boolean` | `true` | Show manage button |
| `manageLabel` | `string` | `'Manage'` | Manage button label |
| `onManageClick` | `() => void` | — | Callback when manage is clicked |
| `className` | `string` | — | Additional CSS classes |

## Vue Components

The Vue components have the same props and behavior as their React counterparts.

### ConsentBanner

```vue
<script setup lang="ts">
import { ConsentBanner } from '@/vendor/artisanpack-analytics/vue';
</script>

<template>
    <ConsentBanner
        position="bottom"
        title="Cookie Consent"
        description="We use cookies to analyze site usage and improve your experience."
        @consent-saved="(categories) => console.log('Saved:', categories)"
    />
</template>
```

### ConsentPreferences

```vue
<template>
    <ConsentPreferences
        title="Privacy Preferences"
        :show-bulk-actions="true"
        @saved="(categories) => console.log('Updated:', categories)"
    />
</template>
```

### ConsentStatus

```vue
<template>
    <ConsentStatus
        label="Privacy Settings"
        :show-manage-button="true"
        @manage-click="showPreferences = true"
    />
</template>
```

## Consent Categories

The components manage consent for these categories by default (configurable in `config/artisanpack/analytics.php`):

| Category | Description |
|----------|-------------|
| `analytics` | Analytics and performance tracking |
| `marketing` | Marketing and advertising cookies |
| `functional` | Functional cookies for enhanced features |
| `preferences` | User preference cookies |

## How It Works

1. **First visit**: The `ConsentBanner` appears, prompting the user to accept, reject, or customize consent.
2. **Consent storage**: Consent state is persisted in `localStorage` and cookies (365-day expiry) for client-side access, and synced to the server via the consent API.
3. **Visitor tracking**: A unique visitor ID is generated and stored in `localStorage` and a cookie, used to associate consent records with the visitor on the server.
4. **Subsequent visits**: The banner is hidden if consent has already been recorded. The `ConsentStatus` component can be used to let users update preferences later.

## Integration with Tracking

When consent is required (`privacy.require_consent` is `true` in config), the JavaScript tracker respects the consent state:

- **Consent granted**: Tracking is active for the granted categories.
- **Consent denied**: No tracking data is collected for denied categories.
- **No consent recorded**: Tracking is paused until the user makes a choice.

See [Privacy & Consent](Advanced-Privacy-Consent) for server-side consent configuration.
