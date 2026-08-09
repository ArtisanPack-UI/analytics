<!--
  AnalyticsDashboard Vue component.

  Main dashboard layout composing all analytics widgets with date range
  selection and tab navigation using @artisanpack-ui/vue Card, Tabs,
  Select, and Grid. Designed for use with Inertia.js page props.
  Mirrors the Livewire AnalyticsDashboard component.

  @since 1.1.0
-->
<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Card, Tabs, Select, Grid } from '@artisanpack-ui/vue';

import type { TabItem } from '@artisanpack-ui/vue';
import type { AnonymousStatsData, DateRangePreset, TopPageItem, TrafficSourceItem, StatsComparison } from '../../types';

import AnonymousTraffic from '../components/AnonymousTraffic.vue';
import StatsCards from '../components/StatsCards.vue';
import TopPages from '../components/TopPages.vue';
import TrafficSources from '../components/TrafficSources.vue';
import VisitorsChart from '../components/VisitorsChart.vue';

import type { ChartDataPoint } from '../components/VisitorsChart.vue';

interface Stats {
    pageviews: number;
    visitors: number;
    sessions: number;
    bounce_rate: number;
    avg_session_duration: number;
    pages_per_session?: number;
    realtime_visitors?: number;
    comparison?: StatsComparison | null;
}

const props = withDefaults( defineProps<{
    /** Statistics data for the StatsCards widget. */
    stats: Stats;
    /** Time-series chart data points. */
    chartData: ChartDataPoint[];
    /** Top pages data. */
    topPages: TopPageItem[];
    /** Traffic sources data. */
    trafficSources: TrafficSourceItem[];
    /** Active date range preset value. */
    dateRangePreset?: string;
    /** Available date range presets. */
    dateRangePresets?: Record<string, string>;
    /** Whether bot traffic is included. Bots are excluded by default. */
    includeBots?: boolean;
    /**
     * Whether anonymous (pre-consent) page views are folded into the page-view
     * figures. Excluded by default. Only page-view figures respond to this;
     * visitors, sessions and bounce rate never can.
     */
    includeAnonymous?: boolean;
    /**
     * Anonymous traffic summary. When omitted, or when `enabled` is false, the
     * anonymous toggle and tab are not offered at all.
     */
    anonymousStats?: AnonymousStatsData;
}>(), {
    dateRangePreset: '30d',
    includeBots: false,
    includeAnonymous: false,
    anonymousStats: undefined,
    dateRangePresets: () => ( {
        today: 'Today',
        yesterday: 'Yesterday',
        '7d': 'Last 7 days',
        '30d': 'Last 30 days',
        '90d': 'Last 90 days',
        this_week: 'This week',
        last_week: 'Last week',
        this_month: 'This month',
        last_month: 'Last month',
        this_year: 'This year',
    } ),
} );

const emit = defineEmits<{
    dateRangeChange: [preset: string];
    includeBotsChange: [includeBots: boolean];
    includeAnonymousChange: [includeAnonymous: boolean];
}>();

const activeTab = ref( 'overview' );

// Offered only once there is anonymous traffic to show, so the toggle and tab
// never appear on a dashboard where they would do nothing.
const hasAnonymousData = computed(
    () => Boolean( props.anonymousStats?.enabled && props.anonymousStats.anonymous_pageviews > 0 ),
);

// The Anonymous tab disappears the moment there is nothing to show, which a
// date-range change can do at any time. Anyone sitting on it would otherwise be
// left on a tab that no longer exists, with no panel rendered.
watch( hasAnonymousData, ( available ) => {
    if ( ! available && activeTab.value === 'anonymous' ) {
        activeTab.value = 'overview';
    }
} );

// The Anonymous panel fetches its own data, so it has to be told which period
// the dashboard is showing or it silently reports its own default instead.
const anonymousPeriod = computed( () => props.dateRangePreset as DateRangePreset );

const scopeAnnouncement = computed( () => {
    if ( ! hasAnonymousData.value ) {
        return '';
    }

    return props.includeAnonymous
        ? 'Showing page views from consented and anonymous visitors. Visitors, sessions, bounce rate and session duration still count consented visitors only.'
        : 'Showing consented visitors only.';
} );

const presetOptions = computed( () => {
    return Object.entries( props.dateRangePresets ).map( ( [ id, name ] ) => ( {
        id,
        name,
    } ) );
} );

function handlePresetChange( event: Event ): void {
    emit( 'dateRangeChange', ( event.target as HTMLSelectElement ).value );
}

function handleIncludeBotsChange( event: Event ): void {
    emit( 'includeBotsChange', ( event.target as HTMLInputElement ).checked );
}

function handleIncludeAnonymousChange( includeAnonymous: boolean ): void {
    emit( 'includeAnonymousChange', includeAnonymous );
}

function handleIncludeAnonymousToggle( event: Event ): void {
    handleIncludeAnonymousChange( ( event.target as HTMLInputElement ).checked );
}

const tabs = computed<TabItem[]>( () => {
    const items: TabItem[] = [
        { name: 'overview', label: 'Overview' },
        { name: 'pages', label: 'Pages' },
        { name: 'traffic', label: 'Traffic' },
        { name: 'audience', label: 'Audience' },
    ];

    if ( hasAnonymousData.value ) {
        items.push( { name: 'anonymous', label: 'Anonymous' } );
    }

    return items;
} );
</script>

<template>
    <div class="space-y-6">
        <!-- Header with date range selector -->
        <Card>
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold">Analytics Dashboard</h2>
                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                        <input
                            type="checkbox"
                            class="toggle toggle-sm"
                            :checked="props.includeBots"
                            aria-label="Include bot traffic"
                            @change="handleIncludeBotsChange"
                        />
                        <span>Include bot traffic</span>
                    </label>
                    <label
                        v-if="hasAnonymousData"
                        class="flex items-center gap-2 text-sm cursor-pointer select-none"
                    >
                        <input
                            type="checkbox"
                            class="toggle toggle-sm"
                            :checked="props.includeAnonymous"
                            aria-label="Include anonymous traffic in page-view figures"
                            @change="handleIncludeAnonymousToggle"
                        />
                        <span>Include anonymous traffic</span>
                    </label>
                    <div class="w-48">
                        <Select
                            :options="presetOptions"
                            :model-value="props.dateRangePreset"
                            @change="handlePresetChange"
                        />
                    </div>
                </div>
            </div>
        </Card>

        <!-- Announce the traffic scope so toggling does not leave a screen
             reader user on stale figures with no signal they changed. -->
        <div class="sr-only" role="status" aria-live="polite">
            {{ scopeAnnouncement }}
        </div>

        <!-- Scope banner: name the metrics that cannot include anonymous
             traffic rather than leaving it to be inferred from a ratio. -->
        <div
            v-if="props.includeAnonymous && hasAnonymousData"
            class="alert alert-info items-start"
        >
            <div>
                <h3 class="font-semibold">Including anonymous traffic</h3>
                <p class="text-sm">
                    Page views include visitors who have not granted consent. Visitors,
                    sessions, bounce rate and session duration count consented visitors
                    only — anonymous rows carry no visitor or session to count.
                </p>
            </div>
        </div>

        <!-- Tabbed content -->
        <Tabs
            :tabs="tabs"
            v-model:active-tab="activeTab"
            variant="bordered"
        >
            <template #overview>
                <div class="space-y-6 pt-4">
                    <StatsCards :stats="props.stats" />
                    <VisitorsChart :chart-data="props.chartData" />
                    <Grid :cols="1" :cols-lg="2" :gap="6">
                        <TopPages :top-pages="props.topPages" :limit="5" />
                        <TrafficSources :traffic-sources="props.trafficSources" :limit="5" />
                    </Grid>
                </div>
            </template>

            <template #pages>
                <div class="space-y-6 pt-4">
                    <VisitorsChart :chart-data="props.chartData" />
                    <TopPages :top-pages="props.topPages" />
                </div>
            </template>

            <template #traffic>
                <div class="space-y-6 pt-4">
                    <TrafficSources :traffic-sources="props.trafficSources" />
                </div>
            </template>

            <template #audience>
                <div class="space-y-6 pt-4">
                    <StatsCards :stats="props.stats" />
                </div>
            </template>

            <template #anonymous>
                <div class="space-y-6 pt-4">
                    <AnonymousTraffic
                        :period="anonymousPeriod"
                        :include-anonymous="props.includeAnonymous"
                        @include-anonymous-change="handleIncludeAnonymousChange"
                    />
                </div>
            </template>
        </Tabs>
    </div>
</template>
