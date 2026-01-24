<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Events\GoalConverted;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Collection;

/**
 * Goal Matcher Service.
 *
 * Matches analytics events, page views, and sessions against configured goals
 * and records conversions when goals are met.
 *
 * @since 1.0.0
 */
class GoalMatcher
{
    /**
     * The site ID for multi-site filtering.
     */
    protected ?int $siteId = null;

    /**
     * The tenant ID for multi-tenant filtering.
     */
    protected string|int|null $tenantId = null;

    /**
     * Create a new GoalMatcher instance.
     *
     * @param  int|null  $siteId  The site ID.
     * @param  int|string|null  $tenantId  The tenant ID.
     *
     * @since 1.0.0
     */
    public function __construct( ?int $siteId = null, string|int|null $tenantId = null )
    {
        $this->siteId   = $siteId;
        $this->tenantId = $tenantId;
    }

    /**
     * Match an event against event-based goals.
     *
     * @param  Event  $event  The event to match.
     * @param  Session|null  $session  The session the event occurred in.
     * @param  Visitor|null  $visitor  The visitor who triggered the event.
     *
     * @return Collection<int, Conversion> Conversions created.
     *
     * @since 1.0.0
     */
    public function matchEvent( Event $event, ?Session $session, ?Visitor $visitor ): Collection
    {
        $goals = $this->getActiveGoals( Goal::TYPE_EVENT );

        $conversions = collect();

        foreach ( $goals as $goal ) {
            if ( $goal->matches( $event ) ) {
                $conversion = $this->recordConversion( $goal, $event, $session, $visitor );

                if ( null !== $conversion ) {
                    $conversions->push( $conversion );
                }
            }
        }

        return $conversions;
    }

    /**
     * Match a page view against pageview-based goals.
     *
     * @param  PageView  $pageView  The page view to match.
     * @param  Session|null  $session  The session the page view occurred in.
     * @param  Visitor|null  $visitor  The visitor who viewed the page.
     *
     * @return Collection<int, Conversion> Conversions created.
     *
     * @since 1.0.0
     */
    public function matchPageView( PageView $pageView, ?Session $session, ?Visitor $visitor ): Collection
    {
        $goals = $this->getActiveGoals( Goal::TYPE_PAGEVIEW );

        $conversions = collect();

        foreach ( $goals as $goal ) {
            if ( $goal->matches( $pageView ) ) {
                $conversion = $this->recordConversion( $goal, $pageView, $session, $visitor );

                if ( null !== $conversion ) {
                    $conversions->push( $conversion );
                }
            }
        }

        return $conversions;
    }

    /**
     * Match a session against duration and pages-per-session goals.
     *
     * This is typically called when a session ends.
     *
     * @param  Session  $session  The session to match.
     *
     * @return Collection<int, Conversion> Conversions created.
     *
     * @since 1.0.0
     */
    public function matchSession( Session $session ): Collection
    {
        $visitor     = $session->visitor;
        $conversions = collect();

        // Check duration goals
        $durationGoals = $this->getActiveGoals( Goal::TYPE_DURATION );

        foreach ( $durationGoals as $goal ) {
            if ( $goal->matches( $session ) ) {
                $conversion = $this->recordConversion( $goal, $session, $session, $visitor );

                if ( null !== $conversion ) {
                    $conversions->push( $conversion );
                }
            }
        }

        // Check pages per session goals
        $pagesGoals = $this->getActiveGoals( Goal::TYPE_PAGES_PER_SESSION );

        foreach ( $pagesGoals as $goal ) {
            if ( $goal->matches( $session ) ) {
                $conversion = $this->recordConversion( $goal, $session, $session, $visitor );

                if ( null !== $conversion ) {
                    $conversions->push( $conversion );
                }
            }
        }

        return $conversions;
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
     * Record a conversion for a goal.
     *
     * @param  Goal  $goal  The goal that was matched.
     * @param  Event|PageView|Session  $trigger  The trigger that caused the conversion.
     * @param  Session|null  $session  The session.
     * @param  Visitor|null  $visitor  The visitor.
     *
     * @return Conversion|null The conversion record, or null if duplicate.
     *
     * @since 1.0.0
     */
    protected function recordConversion(
        Goal $goal,
        Event|PageView|Session $trigger,
        ?Session $session,
        ?Visitor $visitor,
    ): ?Conversion {
        // Check for duplicate conversion in same session or by visitor
        if ( ! $this->shouldRecordConversion( $goal, $session, $visitor ) ) {
            return null;
        }

        $conversion = Conversion::create( [
            'site_id'      => $this->siteId ?? $goal->site_id,
            'goal_id'      => $goal->id,
            'session_id'   => $session?->id,
            'visitor_id'   => $visitor?->id,
            'event_id'     => $trigger instanceof Event ? $trigger->id : null,
            'page_view_id' => $trigger instanceof PageView ? $trigger->id : null,
            'value'        => $goal->calculateValue( $trigger ),
            'metadata'     => $this->extractMetadata( $trigger ),
            'tenant_id'    => $this->tenantId ?? $goal->tenant_id,
        ] );

        // Fire the GoalConverted event
        event( new GoalConverted( $goal, $conversion, $session, $visitor ) );

        return $conversion;
    }

    /**
     * Check if a conversion should be recorded.
     *
     * @param  Goal  $goal  The goal.
     * @param  Session|null  $session  The session.
     * @param  Visitor|null  $visitor  The visitor.
     *
     * @return bool True if conversion should be recorded.
     *
     * @since 1.0.0
     */
    protected function shouldRecordConversion( Goal $goal, ?Session $session, ?Visitor $visitor = null ): bool
    {
        // If multiple conversions per session are allowed, always record
        if ( config( 'artisanpack.analytics.goals.allow_multiple_per_session', false ) ) {
            return true;
        }

        // If we have a session, check for session-level duplicates
        if ( null !== $session ) {
            return ! Conversion::query()
                ->where( 'goal_id', $goal->id )
                ->where( 'session_id', $session->id )
                ->exists();
        }

        // Fallback to visitor-level dedupe when session is null
        if ( null !== $visitor ) {
            return ! Conversion::query()
                ->where( 'goal_id', $goal->id )
                ->where( 'visitor_id', $visitor->id )
                ->exists();
        }

        // No session or visitor available - allow recording
        return true;
    }

    /**
     * Get active goals of a specific type.
     *
     * @param  string  $type  The goal type.
     *
     * @return Collection<int, Goal> Active goals.
     *
     * @since 1.0.0
     */
    protected function getActiveGoals( string $type ): Collection
    {
        $query = Goal::query()
            ->where( 'type', $type )
            ->where( 'is_active', true );

        if ( null !== $this->siteId ) {
            $query->where( function ( $q ): void {
                $q->where( 'site_id', $this->siteId )
                    ->orWhereNull( 'site_id' );
            } );
        }

        if ( null !== $this->tenantId && config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
            $query->where( function ( $q ): void {
                $q->where( 'tenant_id', $this->tenantId )
                    ->orWhereNull( 'tenant_id' );
            } );
        }

        return $query->get();
    }

    /**
     * Extract metadata from the trigger.
     *
     * @param  Event|PageView|Session  $trigger  The trigger.
     *
     * @return array<string, mixed> The metadata.
     *
     * @since 1.0.0
     */
    protected function extractMetadata( Event|PageView|Session $trigger ): array
    {
        if ( $trigger instanceof Event ) {
            return [
                'trigger_type'     => 'event',
                'event_name'       => $trigger->name,
                'event_category'   => $trigger->category,
                'event_properties' => $trigger->properties,
                'event_value'      => $trigger->value,
            ];
        }

        if ( $trigger instanceof PageView ) {
            return [
                'trigger_type' => 'pageview',
                'path'         => $trigger->path,
                'title'        => $trigger->title,
            ];
        }

        // Session
        return [
            'trigger_type' => 'session',
            'duration'     => $trigger->duration,
            'page_count'   => $trigger->page_count,
            'entry_page'   => $trigger->entry_page,
            'exit_page'    => $trigger->exit_page,
        ];
    }
}
