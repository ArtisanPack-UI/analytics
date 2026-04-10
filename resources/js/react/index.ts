/**
 * ArtisanPack Analytics — React dashboard components.
 *
 * Barrel file re-exporting all React dashboard components so consumers
 * can import from a single entry point:
 *
 *   import { AnalyticsDashboard, StatsCards } from '@artisanpack-ui/analytics/react';
 *
 * @since 1.1.0
 */

// Widgets
export { default as StatsCards } from './widgets/StatsCards';
export { default as VisitorsChart } from './widgets/VisitorsChart';
export { default as TopPages } from './widgets/TopPages';
export { default as TrafficSources } from './widgets/TrafficSources';
export { default as RealtimeVisitors } from './widgets/RealtimeVisitors';

// Dashboard components
export { default as AnalyticsDashboard } from './AnalyticsDashboard';
export { default as PageAnalytics } from './PageAnalytics';
export { default as SiteSelector } from './SiteSelector';
export { default as MultiTenantDashboard } from './MultiTenantDashboard';

// Consent components
export { default as ConsentBanner } from './ConsentBanner';
export { default as ConsentPreferences } from './ConsentPreferences';
export { default as ConsentStatus } from './ConsentStatus';

// Hooks
export { useAnalyticsApi } from './useAnalyticsApi';
export { useConsent } from './useConsent';
