<!--
  AnonymousTraffic Vue component.

  Surfaces the page views collected before consent by anonymous mode: the
  anonymous share of total page views, the pages and referring hosts behind
  it, the device-class split and a trend. Mirrors the Livewire
  AnonymousTraffic widget.

  Everything here is a page-view count. Anonymous rows carry no visitor,
  session or fingerprint, so there is deliberately no visitor, session, bounce
  or duration figure — those numbers do not exist for this traffic and a zero
  would imply they did.

  @since 1.5.0
-->
<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { Card, Table, Loading } from '@artisanpack-ui/vue';

import { useAnalyticsApi } from '../composables/useAnalyticsApi';

import type { TableColumn } from '@artisanpack-ui/vue';
import type { AnonymousStatsData, DateRangePreset } from '../../types';

const props = withDefaults( defineProps<{
    /** Date range preset to query. Defaults to '30d'. */
    period?: DateRangePreset;
    /** Optional site ID filter. */
    siteId?: number;
    /** Maximum number of pages and hosts to display. */
    limit?: number;
    /** Whether anonymous page views are folded into the dashboard figures. */
    includeAnonymous?: boolean;
}>(), {
    period: '30d',
    siteId: undefined,
    limit: 10,
    includeAnonymous: false,
} );

const emit = defineEmits<{
    includeAnonymousChange: [includeAnonymous: boolean];
}>();

const normalizedLimit = computed( () => {
    const raw = Number( props.limit );

    return Number.isFinite( raw ) ? Math.min( 100, Math.max( 1, Math.floor( raw ) ) ) : 10;
} );

const params = reactive( {
    period: props.period,
    site_id: props.siteId,
    limit: normalizedLimit.value,
} );

const { data, loading, error, refresh } = useAnalyticsApi<AnonymousStatsData>( {
    endpoint: 'anonymous',
    params,
} );

watch(
    () => [ props.period, props.siteId, props.limit ],
    () => {
        params.period = props.period;
        params.site_id = props.siteId;
        params.limit = normalizedLimit.value;
        refresh();
    },
);

const pageColumns: TableColumn[] = [
    { key: 'path', label: 'Path' },
    { key: 'views', label: 'Page views' },
];

const hostColumns: TableColumn[] = [
    { key: 'host', label: 'Host' },
    { key: 'views', label: 'Page views' },
];

const numberFormatter = new Intl.NumberFormat();

const enabled = computed( () => data.value?.enabled ?? false );
const anonymousPageviews = computed( () => data.value?.anonymous_pageviews ?? 0 );
const identifiedPageviews = computed( () => data.value?.identified_pageviews ?? 0 );
const anonymousPercentage = computed( () => data.value?.anonymous_percentage ?? 0 );
const trend = computed( () => data.value?.trend ?? [] );
const trendMax = computed( () => Math.max( 1, ...trend.value.map( ( point ) => point.pageviews ) ) );
const topPages = computed( () => ( data.value?.top_pages ?? [] ).slice( 0, normalizedLimit.value ) );
const referringHosts = computed( () => ( data.value?.referring_hosts ?? [] ).slice( 0, normalizedLimit.value ) );
const devices = computed( () => data.value?.device_breakdown ?? [] );

function handleIncludeAnonymousChange( event: Event ): void {
    emit( 'includeAnonymousChange', ( event.target as HTMLInputElement ).checked );
}
</script>

<template>
    <Card title="Anonymous Traffic">
        <div v-if="loading && ! data" class="flex justify-center py-8">
            <Loading size="lg" />
        </div>
        <p v-else-if="error && ! data" class="text-error text-center py-4">
            {{ error }}
        </p>

        <!-- Feature off, or on with nothing collected: say which, rather than
             rendering a zeroed panel that reads like a fault. -->
        <p
            v-else-if="! enabled"
            class="text-base-content/60 text-center py-6 max-w-prose mx-auto"
        >
            Anonymous mode is turned off, so visitors who have not granted consent are
            not counted at all. Enable it to record pre-consent page views.
        </p>
        <p
            v-else-if="anonymousPageviews === 0"
            class="text-base-content/60 text-center py-6 max-w-prose mx-auto"
        >
            Anonymous mode is on, but no pre-consent page views have been recorded for
            this period.
        </p>

        <div v-else>
            <p class="text-sm text-base-content/60 mb-4 max-w-prose">
                Page views recorded before consent was granted. These rows carry no
                visitor or session, so they can be counted but never attributed: they
                cannot contribute to visitors, sessions, bounce rate or session duration
                anywhere on this dashboard.
            </p>

            <label class="flex items-center gap-2 text-sm cursor-pointer select-none mb-4">
                <input
                    type="checkbox"
                    class="toggle toggle-sm"
                    :checked="props.includeAnonymous"
                    aria-label="Include anonymous traffic in dashboard page-view figures"
                    @change="handleIncludeAnonymousChange"
                />
                <span>Include anonymous traffic in dashboard page-view figures</span>
            </label>

            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-box bg-base-200 p-4">
                        <div class="text-xs uppercase tracking-wide text-base-content/50">
                            Anonymous page views
                        </div>
                        <div class="text-2xl font-bold font-mono">
                            {{ numberFormatter.format( anonymousPageviews ) }}
                        </div>
                    </div>
                    <div class="rounded-box bg-base-200 p-4">
                        <div class="text-xs uppercase tracking-wide text-base-content/50">
                            Consented page views
                        </div>
                        <div class="text-2xl font-bold font-mono">
                            {{ numberFormatter.format( identifiedPageviews ) }}
                        </div>
                    </div>
                    <div class="rounded-box bg-base-200 p-4">
                        <div class="text-xs uppercase tracking-wide text-base-content/50">
                            % of all page views
                        </div>
                        <div class="text-2xl font-bold font-mono">
                            {{ anonymousPercentage.toFixed( 1 ) }}%
                        </div>
                    </div>
                </div>

                <div v-if="trend.length > 0">
                    <div class="text-xs uppercase tracking-wide text-base-content/50 mb-2">
                        Anonymous page view trend
                    </div>
                    <div
                        class="flex items-end gap-px h-16"
                        role="img"
                        aria-label="Anonymous page views over time for the selected date range."
                    >
                        <div
                            v-for="( point, index ) in trend"
                            :key="`${point.date}-${index}`"
                            class="flex-1 bg-secondary/60 rounded-t min-h-[2px]"
                            :style="{ height: `${Math.max( 2, Math.round( ( point.pageviews / trendMax ) * 100 ) )}%` }"
                            :title="`${point.date}: ${numberFormatter.format( point.pageviews )}`"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-semibold mb-2">Top pages</h4>
                        <Table
                            :columns="pageColumns"
                            :rows="topPages"
                            striped
                            :empty-message="'No pages recorded for this period.'"
                        />
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold mb-2">Referring hosts</h4>
                        <Table
                            :columns="hostColumns"
                            :rows="referringHosts"
                            striped
                            :empty-message="'No referrers recorded for this period.'"
                        />
                    </div>
                </div>

                <div v-if="devices.length > 0">
                    <h4 class="text-sm font-semibold mb-2">Devices</h4>
                    <div class="space-y-3">
                        <div v-for="device in devices" :key="device.device_type">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium">{{ device.device_type }}</span>
                                <span class="text-sm text-base-content/70">
                                    {{ numberFormatter.format( device.views ) }}
                                    ({{ device.percentage.toFixed( 1 ) }}%)
                                </span>
                            </div>
                            <progress
                                class="progress progress-secondary w-full"
                                :value="device.percentage"
                                :max="100"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Card>
</template>
