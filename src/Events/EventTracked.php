<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Events;

use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an analytics event is tracked.
 *
 * This event is dispatched after an event is stored in the database,
 * allowing listeners to react to any tracked event.
 *
 * @since 1.0.0
 */
class EventTracked
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Event  $event  The tracked event.
     * @param  Session|null  $session  The session the event occurred in.
     * @param  Visitor|null  $visitor  The visitor who triggered the event.
     *
     * @since 1.0.0
     */
    public function __construct(
        public Event $event,
        public ?Session $session = null,
        public ?Visitor $visitor = null,
    ) {
    }

    /**
     * Get the event name.
     *
     *
     * @since 1.0.0
     */
    public function getEventName(): string
    {
        return $this->event->name;
    }

    /**
     * Get the event category.
     *
     *
     * @since 1.0.0
     */
    public function getEventCategory(): ?string
    {
        return $this->event->category;
    }

    /**
     * Get the event value.
     *
     *
     * @since 1.0.0
     */
    public function getEventValue(): ?float
    {
        return $this->event->value;
    }

    /**
     * Get the event properties.
     *
     * @return array<string, mixed>|null
     *
     * @since 1.0.0
     */
    public function getEventProperties(): ?array
    {
        return $this->event->properties;
    }

    /**
     * Check if the event is from a specific package.
     *
     * @param  string  $package  The package name to check.
     *
     * @since 1.0.0
     */
    public function isFromPackage( string $package ): bool
    {
        return $this->event->source_package === $package;
    }
}
