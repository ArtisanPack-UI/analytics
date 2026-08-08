/**
 * AnonymousTraffic React component.
 *
 * Surfaces the page views collected before consent by anonymous mode: the
 * anonymous share of total page views, the pages and referring hosts behind
 * it, the device-class split and a trend. Mirrors the Livewire
 * AnonymousTraffic widget.
 *
 * Everything here is a page-view count. Anonymous rows carry no visitor,
 * session or fingerprint, so there is deliberately no visitor, session,
 * bounce or duration figure — those numbers do not exist for this traffic and
 * a zero would imply they did.
 *
 * @since 1.5.0
 */

import React from 'react';
import { Card, Table, Loading } from '@artisanpack-ui/react';

import { useAnalyticsApi } from '../hooks/useAnalyticsApi';

import type { TableHeader } from '@artisanpack-ui/react';
import type {
    AnonymousReferringHostItem,
    AnonymousStatsData,
    AnonymousTopPageItem,
    DateRangePreset,
} from '../../types';

export interface AnonymousTrafficProps {
    /** Date range preset to query. Defaults to '30d'. */
    period?: DateRangePreset;
    /** Optional site ID filter. */
    siteId?: number;
    /** Maximum number of pages and hosts to display. */
    limit?: number;
    /** Initial data (e.g. from Inertia page props) to render before fetching. */
    initialData?: AnonymousStatsData;
    /** Whether anonymous page views are folded into the dashboard figures. */
    includeAnonymous?: boolean;
    /** Called when the user toggles anonymous traffic in the dashboard figures. */
    onIncludeAnonymousChange?: ( includeAnonymous: boolean ) => void;
    /** Optional CSS class name for the container. */
    className?: string;
}

const pageHeaders: TableHeader<AnonymousTopPageItem>[] = [
    { key: 'path', label: 'Path' },
    { key: 'views', label: 'Page views' },
];

const hostHeaders: TableHeader<AnonymousReferringHostItem>[] = [
    { key: 'host', label: 'Host' },
    { key: 'views', label: 'Page views' },
];

const numberFormatter = new Intl.NumberFormat();

export default function AnonymousTraffic( {
    period = '30d',
    siteId,
    limit = 10,
    initialData,
    includeAnonymous,
    onIncludeAnonymousChange,
    className = '',
}: AnonymousTrafficProps ): React.ReactElement {
    const normalizedLimit = Number.isFinite( limit )
        ? Math.min( 100, Math.max( 1, Math.floor( limit ) ) )
        : 10;

    const { data, loading, error } = useAnalyticsApi<AnonymousStatsData>( {
        endpoint: 'anonymous',
        params: { period, site_id: siteId, limit: normalizedLimit },
        initialData,
        fetchOnMount: ! initialData,
    } );

    const stats = data ?? initialData;
    const trend = stats?.trend ?? [];
    const trendMax = Math.max( 1, ...trend.map( ( point ) => point.pageviews ) );
    const topPages = ( stats?.top_pages ?? [] ).slice( 0, normalizedLimit );
    const referringHosts = ( stats?.referring_hosts ?? [] ).slice( 0, normalizedLimit );
    const devices = stats?.device_breakdown ?? [];
    const anonymousPageviews = stats?.anonymous_pageviews ?? 0;

    if ( loading && ! stats ) {
        return (
            <Card title="Anonymous Traffic" className={className}>
                <div className="flex justify-center py-8">
                    <Loading size="lg" />
                </div>
            </Card>
        );
    }

    if ( error && ! stats ) {
        return (
            <Card title="Anonymous Traffic" className={className}>
                <p className="text-error text-center py-4">{error}</p>
            </Card>
        );
    }

    // Feature off, or on with nothing collected: say which, rather than
    // rendering a zeroed panel that reads like a fault.
    if ( stats && ! stats.enabled ) {
        return (
            <Card title="Anonymous Traffic" className={className}>
                <p className="text-base-content/60 text-center py-6 max-w-prose mx-auto">
                    Anonymous mode is turned off, so visitors who have not granted consent
                    are not counted at all. Enable it to record pre-consent page views.
                </p>
            </Card>
        );
    }

    if ( anonymousPageviews === 0 ) {
        return (
            <Card title="Anonymous Traffic" className={className}>
                <p className="text-base-content/60 text-center py-6 max-w-prose mx-auto">
                    Anonymous mode is on, but no pre-consent page views have been recorded
                    for this period.
                </p>
            </Card>
        );
    }

    return (
        <Card title="Anonymous Traffic" className={className}>
            <p className="text-sm text-base-content/60 mb-4 max-w-prose">
                Page views recorded before consent was granted. These rows carry no visitor
                or session, so they can be counted but never attributed: they cannot
                contribute to visitors, sessions, bounce rate or session duration anywhere
                on this dashboard.
            </p>

            {onIncludeAnonymousChange && (
                <label className="flex items-center gap-2 text-sm cursor-pointer select-none mb-4">
                    <input
                        type="checkbox"
                        className="toggle toggle-sm"
                        checked={includeAnonymous ?? false}
                        onChange={( event ) => onIncludeAnonymousChange( event.target.checked )}
                        aria-label="Include anonymous traffic in dashboard page-view figures"
                    />
                    <span>Include anonymous traffic in dashboard page-view figures</span>
                </label>
            )}

            <div className="space-y-6">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="rounded-box bg-base-200 p-4">
                        <div className="text-xs uppercase tracking-wide text-base-content/50">
                            Anonymous page views
                        </div>
                        <div className="text-2xl font-bold font-mono">
                            {numberFormatter.format( anonymousPageviews )}
                        </div>
                    </div>
                    <div className="rounded-box bg-base-200 p-4">
                        <div className="text-xs uppercase tracking-wide text-base-content/50">
                            Consented page views
                        </div>
                        <div className="text-2xl font-bold font-mono">
                            {numberFormatter.format( stats?.identified_pageviews ?? 0 )}
                        </div>
                    </div>
                    <div className="rounded-box bg-base-200 p-4">
                        <div className="text-xs uppercase tracking-wide text-base-content/50">
                            % of all page views
                        </div>
                        <div className="text-2xl font-bold font-mono">
                            {( stats?.anonymous_percentage ?? 0 ).toFixed( 1 )}%
                        </div>
                    </div>
                </div>

                {trend.length > 0 && (
                    <div>
                        <div className="text-xs uppercase tracking-wide text-base-content/50 mb-2">
                            Anonymous page view trend
                        </div>
                        <div
                            className="flex items-end gap-px h-16"
                            role="img"
                            aria-label="Anonymous page views over time for the selected date range."
                        >
                            {trend.map( ( point, index ) => (
                                <div
                                    key={`${point.date}-${index}`}
                                    className="flex-1 bg-secondary/60 rounded-t min-h-[2px]"
                                    style={{
                                        height: `${Math.max( 2, Math.round( ( point.pageviews / trendMax ) * 100 ) )}%`,
                                    }}
                                    title={`${point.date}: ${numberFormatter.format( point.pageviews )}`}
                                />
                            ) )}
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h4 className="text-sm font-semibold mb-2">Top pages</h4>
                        <Table<AnonymousTopPageItem>
                            headers={pageHeaders}
                            rows={topPages}
                            striped
                        />
                    </div>
                    <div>
                        <h4 className="text-sm font-semibold mb-2">Referring hosts</h4>
                        <Table<AnonymousReferringHostItem>
                            headers={hostHeaders}
                            rows={referringHosts}
                            striped
                        />
                    </div>
                </div>

                {devices.length > 0 && (
                    <div>
                        <h4 className="text-sm font-semibold mb-2">Devices</h4>
                        <div className="space-y-3">
                            {devices.map( ( device ) => (
                                <div key={device.device_type}>
                                    <div className="flex items-center justify-between mb-1">
                                        <span className="text-sm font-medium">
                                            {device.device_type}
                                        </span>
                                        <span className="text-sm text-base-content/70">
                                            {numberFormatter.format( device.views )} (
                                            {device.percentage.toFixed( 1 )}%)
                                        </span>
                                    </div>
                                    <progress
                                        className="progress progress-secondary w-full"
                                        value={device.percentage}
                                        max={100}
                                    />
                                </div>
                            ) )}
                        </div>
                    </div>
                )}
            </div>
        </Card>
    );
}
