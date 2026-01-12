<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Enums\EventType;
use ArtisanPackUI\Analytics\Events\EventTracked;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Event Processor Service.
 *
 * Processes analytics events, validates them, stores them in the database,
 * and triggers goal matching for conversion tracking.
 *
 * @since 1.0.0
 */
class EventProcessor
{
    /**
     * Create a new EventProcessor instance.
     *
     * @param  GoalMatcher  $goalMatcher  The goal matcher service.
     *
     * @since 1.0.0
     */
    public function __construct(
        protected GoalMatcher $goalMatcher,
    ) {
    }

    /**
     * Process an event.
     *
     * @param  EventData  $data  The event data.
     * @param  int|null  $siteId  The site ID.
     *
     * @return Event The created event.
     *
     * @since 1.0.0
     */
    public function process( EventData $data, ?int $siteId = null ): Event
    {
        // Validate event schema if configured
        $this->validateEventSchema( $data );

        // Resolve visitor and session
        $visitor = $this->resolveVisitor( $data->visitorId, $siteId );
        $session = $this->resolveSession( $data->sessionId, $siteId );

        // Infer category if not provided
        $category = $data->category ?? EventType::inferCategory( $data->name );

        // Create the event record
        $event = Event::create( [
            'site_id'        => $siteId,
            'session_id'     => $session?->id,
            'visitor_id'     => $visitor?->id,
            'page_view_id'   => $data->pageViewId,
            'name'           => $data->name,
            'category'       => $category,
            'action'         => $data->action,
            'label'          => $data->label,
            'properties'     => $data->properties,
            'value'          => $data->value,
            'source_package' => $data->sourcePackage,
            'path'           => $data->path,
            'tenant_id'      => $data->tenantId,
        ] );

        // Update visitor stats
        if ( null !== $visitor ) {
            $visitor->increment( 'total_events' );
        }

        // Configure goal matcher for site/tenant
        $this->goalMatcher
            ->forSite( $siteId )
            ->forTenant( $data->tenantId );

        // Check for goal matches
        $this->goalMatcher->matchEvent( $event, $session, $visitor );

        // Fire the EventTracked event
        event( new EventTracked( $event, $session, $visitor ) );

        return $event;
    }

    /**
     * Process an event from the PHP API (convenience method).
     *
     * @param  string  $name  The event name.
     * @param  array<string, mixed>  $properties  The event properties.
     * @param  string|null  $category  The event category.
     * @param  float|null  $value  The event value.
     * @param  string|null  $sourcePackage  The source package.
     * @param  int|null  $siteId  The site ID.
     *
     * @return Event The created event.
     *
     * @since 1.0.0
     */
    public function track(
        string $name,
        array $properties = [],
        ?string $category = null,
        ?float $value = null,
        ?string $sourcePackage = null,
        ?int $siteId = null,
    ): Event {
        $data = new EventData(
            name: $name,
            properties: $properties,
            category: $category,
            value: $value,
            sourcePackage: $sourcePackage,
        );

        return $this->process( $data, $siteId );
    }

    /**
     * Validate event schema if configured.
     *
     * @param  EventData  $data  The event data.
     *
     * @throws InvalidArgumentException If required properties are missing.
     *
     * @since 1.0.0
     */
    protected function validateEventSchema( EventData $data ): void
    {
        // Check if schema validation is enabled
        $schema = config( "artisanpack.analytics.events.schema.{$data->name}" );

        if ( null === $schema ) {
            // Check if we have an EventType enum definition
            $eventType = EventType::tryFrom( $data->name );

            if ( null !== $eventType ) {
                $requiredProperties = $eventType->getRequiredProperties();

                foreach ( $requiredProperties as $field ) {
                    if ( ! isset( $data->properties[ $field ] ) ) {
                        Log::warning( __( 'Analytics event missing recommended property: :field for event :event', [
                            'field' => $field,
                            'event' => $data->name,
                        ] ) );
                    }
                }
            }

            return;
        }

        // Validate required properties from config
        foreach ( $schema['required'] ?? [] as $field ) {
            if ( ! isset( $data->properties[ $field ] ) ) {
                throw new InvalidArgumentException(
                    __( 'Missing required property: :field for event :event', [
                        'field' => $field,
                        'event' => $data->name,
                    ] ),
                );
            }
        }
    }

    /**
     * Resolve a visitor by fingerprint.
     *
     * @param  string|null  $visitorId  The visitor fingerprint identifier.
     * @param  int|null  $siteId  The site ID.
     *
     * @since 1.0.0
     */
    protected function resolveVisitor( ?string $visitorId, ?int $siteId ): ?Visitor
    {
        if ( null === $visitorId ) {
            return null;
        }

        $query = Visitor::query()->where( 'fingerprint', $visitorId );

        if ( null !== $siteId ) {
            $query->where( 'site_id', $siteId );
        }

        return $query->first();
    }

    /**
     * Resolve a session by ID.
     *
     * @param  string|null  $sessionId  The session ID.
     * @param  int|null  $siteId  The site ID.
     *
     * @since 1.0.0
     */
    protected function resolveSession( ?string $sessionId, ?int $siteId ): ?Session
    {
        if ( null === $sessionId ) {
            return null;
        }

        $query = Session::query()->where( 'session_id', $sessionId );

        if ( null !== $siteId ) {
            $query->where( 'site_id', $siteId );
        }

        return $query->first();
    }
}
