<!--
  StatsCards Vue component.

  Displays key analytics metrics using @artisanpack-ui/vue Stat
  components with comparison indicators. Mirrors the Livewire
  StatsCards widget.

  @since 1.1.0
-->
<script setup lang="ts">
import { computed } from 'vue';
import { Stat } from '@artisanpack-ui/vue';

import type { AnonymousFilterMode, StatsComparison } from '../../types';

interface Stats {
    pageviews: number;
    visitors: number;
    sessions: number;
    bounce_rate: number;
    avg_session_duration: number;
    pages_per_session?: number;
    realtime_visitors?: number;
    anonymous_pageviews?: number;
    anonymous_mode?: AnonymousFilterMode;
    identified_only_metrics_available?: boolean;
    comparison?: StatsComparison | null;
}

const props = defineProps<{
    /** Core statistics object from the API. */
    stats: Stats;
}>();

// When anonymous page views are in scope, every card says which scope it
// covers. A combined page-view figure sitting unlabelled beside a
// consented-only visitor figure invites a ratio nobody should compute.
const combined = computed( () => props.stats.anonymous_mode === 'include' );
const anonymousOnly = computed( () => props.stats.anonymous_mode === 'only' );

const withAnonymous = computed( () => {
    if ( combined.value ) {
        return ' (incl. anonymous)';
    }

    return anonymousOnly.value ? ' (anonymous only)' : '';
} );

// In an anonymous-only view these metrics are not reported at all. The API
// sends zeroes so the shape stays stable; rendering those as real figures
// would invent a fact, so they are shown as unavailable instead.
const unavailable = computed(
    () => anonymousOnly.value || false === props.stats.identified_only_metrics_available,
);

const consentedOnly = computed( () => {
    if ( unavailable.value ) {
        return ' (not available)';
    }

    return combined.value ? ' (consented only)' : '';
} );

function identifiedValue( value: string ): string {
    return unavailable.value ? '—' : value;
}

function formatDuration( seconds: number ): string {
    const totalSeconds = Math.round( seconds );

    if ( totalSeconds < 60 ) {
        return `${totalSeconds}s`;
    }

    const minutes = Math.floor( totalSeconds / 60 );
    const remainingSeconds = totalSeconds % 60;

    return `${minutes}m ${remainingSeconds}s`;
}

function formatChange( value?: { change: number } ): string | undefined {
    if ( ! value ) {
        return undefined;
    }

    const sign = value.change >= 0 ? '+' : '';

    return `${sign}${value.change.toFixed( 1 )}%`;
}

function changeDirection( value?: { change: number } ): 'up' | 'down' | 'neutral' {
    if ( ! value || value.change === 0 ) {
        return 'neutral';
    }

    return value.change > 0 ? 'up' : 'down';
}
</script>

<template>
    <div class="stats stats-vertical lg:stats-horizontal shadow w-full">
        <Stat
            :title="`Pageviews${withAnonymous}`"
            :value="new Intl.NumberFormat().format( props.stats.pageviews )"
            :change="formatChange( props.stats.comparison?.pageviews )"
            :change-direction="changeDirection( props.stats.comparison?.pageviews )"
            :description="props.stats.comparison ? 'vs previous period' : undefined"
        />
        <Stat
            :title="`Visitors${consentedOnly}`"
            :value="identifiedValue( new Intl.NumberFormat().format( props.stats.visitors ) )"
            :change="unavailable ? undefined : formatChange( props.stats.comparison?.visitors )"
            :change-direction="unavailable ? 'neutral' : changeDirection( props.stats.comparison?.visitors )"
            :description="props.stats.comparison ? 'vs previous period' : undefined"
        />
        <Stat
            :title="`Sessions${consentedOnly}`"
            :value="identifiedValue( new Intl.NumberFormat().format( props.stats.sessions ) )"
            :change="unavailable ? undefined : formatChange( props.stats.comparison?.sessions )"
            :change-direction="unavailable ? 'neutral' : changeDirection( props.stats.comparison?.sessions )"
            :description="props.stats.comparison ? 'vs previous period' : undefined"
        />
        <Stat
            :title="`Bounce Rate${consentedOnly}`"
            :value="identifiedValue( `${props.stats.bounce_rate.toFixed( 1 )}%` )"
            :change="unavailable ? undefined : formatChange( props.stats.comparison?.bounce_rate )"
            :change-direction="unavailable ? 'neutral' : changeDirection( props.stats.comparison?.bounce_rate )"
            :description="props.stats.comparison ? 'vs previous period' : undefined"
        />
        <Stat
            :title="`Avg. Session Duration${consentedOnly}`"
            :value="identifiedValue( formatDuration( props.stats.avg_session_duration ) )"
            :change="unavailable ? undefined : formatChange( props.stats.comparison?.avg_session_duration )"
            :change-direction="unavailable ? 'neutral' : changeDirection( props.stats.comparison?.avg_session_duration )"
            :description="props.stats.comparison ? 'vs previous period' : undefined"
        />
    </div>
</template>
