/**
 * ArtisanPack Analytics — Vue dashboard components.
 *
 * Barrel file re-exporting all Vue dashboard components so consumers
 * can import from a single entry point:
 *
 *   import { AnalyticsDashboard, StatsCards } from '@artisanpack-ui/analytics/vue';
 *
 * @since 1.1.0
 */

// Widgets
export { default as StatsCards } from './widgets/StatsCards.vue';
export { default as VisitorsChart } from './widgets/VisitorsChart.vue';
export { default as TopPages } from './widgets/TopPages.vue';
export { default as TrafficSources } from './widgets/TrafficSources.vue';
export { default as RealtimeVisitors } from './widgets/RealtimeVisitors.vue';

// Dashboard components
export { default as AnalyticsDashboard } from './AnalyticsDashboard.vue';
export { default as PageAnalytics } from './PageAnalytics.vue';
export { default as SiteSelector } from './SiteSelector.vue';
export { default as MultiTenantDashboard } from './MultiTenantDashboard.vue';

// Consent components
export { default as ConsentBanner } from './ConsentBanner.vue';
export { default as ConsentPreferences } from './ConsentPreferences.vue';
export { default as ConsentStatus } from './ConsentStatus.vue';

// Composables
export { useAnalyticsApi } from './useAnalyticsApi';
export { useConsent } from './useConsent';
