/**
 * StatsCards React component.
 *
 * Displays key analytics metrics using @artisanpack-ui/react Stat and
 * StatGroup components with comparison indicators and sparklines.
 * Mirrors the Livewire StatsCards widget.
 *
 * @since 1.1.0
 */

import React from 'react';
import { Stat, StatGroup } from '@artisanpack-ui/react';

import type { AnonymousFilterMode, StatsComparison } from '../../types';

export interface StatsCardsProps {
    /** Core statistics object from the API. */
    stats: {
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
    };
    /** Optional CSS class name for the container. */
    className?: string;
}

/**
 * Format a duration in seconds to a human-readable string.
 */
function formatDuration( seconds: number ): string {
    const totalSeconds = Math.round( seconds );

    if ( totalSeconds < 60 ) {
        return `${totalSeconds}s`;
    }

    const minutes = Math.floor( totalSeconds / 60 );
    const remainingSeconds = totalSeconds % 60;

    return `${minutes}m ${remainingSeconds}s`;
}

export default function StatsCards( { stats, className = '' }: StatsCardsProps ): React.ReactElement {
    // When anonymous page views are in scope, every card says which scope it
    // covers. A combined page-view figure sitting unlabelled beside a
    // consented-only visitor figure invites a ratio nobody should compute.
    const combined = stats.anonymous_mode === 'include';
    const anonymousOnly = stats.anonymous_mode === 'only';

    const withAnonymous = combined
        ? ' (incl. anonymous)'
        : anonymousOnly ? ' (anonymous only)' : '';

    // In an anonymous-only view these metrics are not reported at all. The API
    // sends zeroes so the shape stays stable; rendering those as real figures
    // would invent a fact, so they are shown as unavailable instead.
    const unavailable = anonymousOnly
        || false === stats.identified_only_metrics_available;
    const consentedOnly = unavailable
        ? ' (not available)'
        : combined ? ' (consented only)' : '';
    const identifiedValue = ( value: string ): string => ( unavailable ? '—' : value );

    return (
        <StatGroup className={className}>
            <Stat
                title={`Pageviews${withAnonymous}`}
                value={new Intl.NumberFormat().format( stats.pageviews )}
                color="primary"
                change={stats.comparison?.pageviews?.change}
                changeLabel="vs previous period"
            />
            <Stat
                title={`Visitors${consentedOnly}`}
                value={identifiedValue( new Intl.NumberFormat().format( stats.visitors ) )}
                color="secondary"
                change={unavailable ? undefined : stats.comparison?.visitors?.change}
                changeLabel="vs previous period"
            />
            <Stat
                title={`Sessions${consentedOnly}`}
                value={identifiedValue( new Intl.NumberFormat().format( stats.sessions ) )}
                color="accent"
                change={unavailable ? undefined : stats.comparison?.sessions?.change}
                changeLabel="vs previous period"
            />
            <Stat
                title={`Bounce Rate${consentedOnly}`}
                value={identifiedValue( `${stats.bounce_rate.toFixed( 1 )}%` )}
                color="warning"
                change={unavailable ? undefined : stats.comparison?.bounce_rate?.change}
                changeLabel="vs previous period"
            />
            <Stat
                title={`Avg. Session Duration${consentedOnly}`}
                value={identifiedValue( formatDuration( stats.avg_session_duration ) )}
                color="info"
                change={unavailable ? undefined : stats.comparison?.avg_session_duration?.change}
                changeLabel="vs previous period"
            />
        </StatGroup>
    );
}
