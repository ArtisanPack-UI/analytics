<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\PageView;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Funnel Analyzer Service.
 *
 * Analyzes multi-step conversion funnels to calculate step-by-step
 * conversion rates, drop-off rates, and overall funnel performance.
 *
 * @since 1.0.0
 */
class FunnelAnalyzer
{
    /**
     * The site ID for filtering.
     */
    protected ?int $siteId = null;

    /**
     * The tenant ID for filtering.
     */
    protected string|int|null $tenantId = null;

    /**
     * Analyze a funnel for a goal within a date range.
     *
     * @param  Goal  $goal  The goal with funnel steps.
     * @param  DateRange  $range  The date range to analyze.
     *
     * @throws InvalidArgumentException If goal has no funnel steps.
     *
     * @return array<string, mixed> Funnel analysis results.
     *
     * @since 1.0.0
     */
    public function analyze( Goal $goal, DateRange $range ): array
    {
        if ( empty( $goal->funnel_steps ) ) {
            throw new InvalidArgumentException( __( 'Goal does not have funnel steps defined.' ) );
        }

        $steps                = collect( $goal->funnel_steps );
        $results              = [];
        $previousVisitorCount = 0;

        foreach ( $steps as $index => $step ) {
            $visitorCount = $this->getVisitorsForStep( $step, $range )->count();

            $stepResult = [
                'step'            => $index + 1,
                'name'            => $step['name'] ?? __( 'Step :number', ['number' => $index + 1] ),
                'type'            => $step['type'] ?? 'unknown',
                'visitors'        => $visitorCount,
                'conversion_rate' => 0 === $previousVisitorCount
                    ? 100.0
                    : round( ( $visitorCount / $previousVisitorCount ) * 100, 2 ),
                'dropoff_rate' => 0 === $previousVisitorCount
                    ? 0.0
                    : round( ( ( $previousVisitorCount - $visitorCount ) / $previousVisitorCount ) * 100, 2 ),
                'dropoff_count' => max( 0, $previousVisitorCount - $visitorCount ),
            ];

            $results[]            = $stepResult;
            $previousVisitorCount = $visitorCount;
        }

        $firstStepVisitors = $results[0]['visitors'] ?? 0;
        $lastStepVisitors  = $results[ count( $results ) - 1 ]['visitors'] ?? 0;

        return [
            'goal_id'    => $goal->id,
            'goal_name'  => $goal->name,
            'date_range' => [
                'start' => $range->startDate->toDateString(),
                'end'   => $range->endDate->toDateString(),
            ],
            'steps'              => $results,
            'total_steps'        => count( $results ),
            'overall_conversion' => $firstStepVisitors > 0
                ? round( ( $lastStepVisitors / $firstStepVisitors ) * 100, 2 )
                : 0.0,
            'total_dropoff'      => $firstStepVisitors - $lastStepVisitors,
            'entry_visitors'     => $firstStepVisitors,
            'completed_visitors' => $lastStepVisitors,
        ];
    }

    /**
     * Compare funnel performance between two date ranges.
     *
     * @param  Goal  $goal  The goal with funnel steps.
     * @param  DateRange  $currentRange  The current date range.
     * @param  DateRange  $previousRange  The previous date range for comparison.
     *
     * @return array<string, mixed> Comparison results.
     *
     * @since 1.0.0
     */
    public function compare( Goal $goal, DateRange $currentRange, DateRange $previousRange ): array
    {
        $current  = $this->analyze( $goal, $currentRange );
        $previous = $this->analyze( $goal, $previousRange );

        $currentOverall  = $current['overall_conversion'];
        $previousOverall = $previous['overall_conversion'];

        $change = $previousOverall > 0
            ? round( ( ( $currentOverall - $previousOverall ) / $previousOverall ) * 100, 2 )
            : ( $currentOverall > 0 ? 100.0 : 0.0 );

        return [
            'current'         => $current,
            'previous'        => $previous,
            'change'          => $change,
            'change_absolute' => round( $currentOverall - $previousOverall, 2 ),
            'trend'           => $change > 0 ? 'up' : ( $change < 0 ? 'down' : 'stable' ),
            'step_changes'    => $this->calculateStepChanges( $current['steps'], $previous['steps'] ),
        ];
    }

    /**
     * Get bottleneck steps (highest drop-off).
     *
     * @param  Goal  $goal  The goal with funnel steps.
     * @param  DateRange  $range  The date range.
     * @param  int  $limit  Maximum bottlenecks to return.
     *
     * @return array<int, array<string, mixed>> Bottleneck steps sorted by drop-off rate.
     *
     * @since 1.0.0
     */
    public function getBottlenecks( Goal $goal, DateRange $range, int $limit = 3 ): array
    {
        $analysis = $this->analyze( $goal, $range );

        // Filter out the first step (100% entry) and sort by drop-off
        $steps = collect( $analysis['steps'] )
            ->filter( fn ( array $step ) => $step['step'] > 1 )
            ->sortByDesc( 'dropoff_rate' )
            ->take( $limit )
            ->values()
            ->toArray();

        return $steps;
    }

    /**
     * Set the site ID for filtering.
     *
     * @param  int|null  $siteId  The site ID.
     *
     * @since 1.0.0
     */
    public function forSite( ?int $siteId ): static
    {
        $this->siteId = $siteId;

        return $this;
    }

    /**
     * Set the tenant ID for filtering.
     *
     * @param  int|string|null  $tenantId  The tenant ID.
     *
     * @since 1.0.0
     */
    public function forTenant( string|int|null $tenantId ): static
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    /**
     * Get visitors who completed a specific funnel step.
     *
     * @param  array<string, mixed>  $step  The step configuration.
     * @param  DateRange  $range  The date range.
     *
     * @return Collection<int, string> Collection of visitor IDs.
     *
     * @since 1.0.0
     */
    protected function getVisitorsForStep( array $step, DateRange $range ): Collection
    {
        $type = $step['type'] ?? 'event';

        return match ( $type ) {
            'event'    => $this->getEventStepVisitors( $step, $range ),
            'pageview' => $this->getPageViewStepVisitors( $step, $range ),
            default    => collect(),
        };
    }

    /**
     * Get visitors who completed an event-based step.
     *
     * @param  array<string, mixed>  $step  The step configuration.
     * @param  DateRange  $range  The date range.
     *
     * @return Collection<int, string> Collection of visitor IDs.
     *
     * @since 1.0.0
     */
    protected function getEventStepVisitors( array $step, DateRange $range ): Collection
    {
        $query = Event::query()
            ->whereBetween( 'created_at', [
                $range->startDate,
                $range->endDate,
            ] )
            ->whereNotNull( 'visitor_id' );

        // Filter by event name - support both 'name' and 'event_name' keys
        $eventName = $step['event_name'] ?? $step['name'] ?? null;
        if ( null !== $eventName ) {
            $query->where( 'name', $eventName );
        }

        // Filter by event category
        if ( isset( $step['event_category'] ) ) {
            $query->where( 'category', $step['event_category'] );
        }

        // Filter by event properties
        if ( isset( $step['property_matches'] ) && is_array( $step['property_matches'] ) ) {
            foreach ( $step['property_matches'] as $key => $value ) {
                $query->whereJsonContains( "properties->{$key}", $value );
            }
        }

        // Apply site filter
        if ( null !== $this->siteId ) {
            $query->where( function ( $q ): void {
                $q->where( 'site_id', $this->siteId )
                    ->orWhereNull( 'site_id' );
            } );
        }

        // Apply tenant filter
        if ( null !== $this->tenantId && config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
            $query->where( function ( $q ): void {
                $q->where( 'tenant_id', $this->tenantId )
                    ->orWhereNull( 'tenant_id' );
            } );
        }

        return $query->distinct( 'visitor_id' )->pluck( 'visitor_id' );
    }

    /**
     * Get visitors who completed a pageview-based step.
     *
     * @param  array<string, mixed>  $step  The step configuration.
     * @param  DateRange  $range  The date range.
     *
     * @return Collection<int, string> Collection of visitor IDs.
     *
     * @since 1.0.0
     */
    protected function getPageViewStepVisitors( array $step, DateRange $range ): Collection
    {
        $query = PageView::query()
            ->whereBetween( 'created_at', [
                $range->startDate,
                $range->endDate,
            ] )
            ->whereNotNull( 'visitor_id' );

        // Filter by path pattern
        if ( isset( $step['path_pattern'] ) ) {
            $pattern = str_replace( '*', '%', $step['path_pattern'] );
            $query->where( 'path', 'LIKE', $pattern );
        }

        // Filter by exact path
        $exactPath = $step['path_exact'] ?? $step['path'] ?? null;
        if ( null !== $exactPath ) {
            $query->where( 'path', $exactPath );
        }

        // Filter by path contains
        if ( isset( $step['path_contains'] ) ) {
            $query->where( 'path', 'LIKE', '%' . $step['path_contains'] . '%' );
        }

        // Apply site filter
        if ( null !== $this->siteId ) {
            $query->where( function ( $q ): void {
                $q->where( 'site_id', $this->siteId )
                    ->orWhereNull( 'site_id' );
            } );
        }

        // Apply tenant filter
        if ( null !== $this->tenantId && config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
            $query->where( function ( $q ): void {
                $q->where( 'tenant_id', $this->tenantId )
                    ->orWhereNull( 'tenant_id' );
            } );
        }

        // Filter by path regex using PHP-side filtering for SQLite compatibility
        if ( isset( $step['path_regex'] ) ) {
            return $query->select( ['visitor_id', 'path'] )
                ->get()
                ->filter( function ( $pageView ) use ( $step ): bool {
                    $result = preg_match( $step['path_regex'], $pageView->path );

                    return 1 === $result;
                } )
                ->pluck( 'visitor_id' )
                ->unique()
                ->values();
        }

        return $query->distinct( 'visitor_id' )->pluck( 'visitor_id' );
    }

    /**
     * Calculate changes between steps in two periods.
     *
     * @param  array<int, array<string, mixed>>  $currentSteps  Current period steps.
     * @param  array<int, array<string, mixed>>  $previousSteps  Previous period steps.
     *
     * @return array<int, array<string, mixed>> Step changes.
     *
     * @since 1.0.0
     */
    protected function calculateStepChanges( array $currentSteps, array $previousSteps ): array
    {
        $changes = [];

        foreach ( $currentSteps as $index => $currentStep ) {
            $previousStep = $previousSteps[ $index ] ?? null;

            $currentRate  = $currentStep['conversion_rate'] ?? 0;
            $previousRate = $previousStep['conversion_rate'] ?? 0;

            $changes[] = [
                'step'              => $currentStep['step'],
                'name'              => $currentStep['name'],
                'current_rate'      => $currentRate,
                'previous_rate'     => $previousRate,
                'rate_change'       => round( $currentRate - $previousRate, 2 ),
                'current_visitors'  => $currentStep['visitors'],
                'previous_visitors' => $previousStep['visitors'] ?? 0,
                'visitor_change'    => $currentStep['visitors'] - ( $previousStep['visitors'] ?? 0),
            ];
        }

        return $changes;
    }
}
