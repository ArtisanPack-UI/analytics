<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Events;

use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a goal conversion is recorded.
 *
 * This event is dispatched when a visitor completes a goal,
 * allowing listeners to react to conversions (e.g., send notifications,
 * trigger workflows, update external systems).
 *
 * @since 1.0.0
 */
class GoalConverted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  Goal  $goal  The goal that was converted.
     * @param  Conversion  $conversion  The conversion record.
     * @param  Session|null  $session  The session where conversion occurred.
     * @param  Visitor|null  $visitor  The visitor who converted.
     *
     * @since 1.0.0
     */
    public function __construct(
        public Goal $goal,
        public Conversion $conversion,
        public ?Session $session = null,
        public ?Visitor $visitor = null,
    ) {
    }

    /**
     * Get the goal name.
     *
     *
     * @since 1.0.0
     */
    public function getGoalName(): string
    {
        return $this->goal->name;
    }

    /**
     * Get the goal type.
     *
     *
     * @since 1.0.0
     */
    public function getGoalType(): string
    {
        return $this->goal->type;
    }

    /**
     * Get the conversion value.
     *
     *
     * @since 1.0.0
     */
    public function getConversionValue(): ?float
    {
        return $this->conversion->value;
    }

    /**
     * Get the conversion metadata.
     *
     * @return array<string, mixed>|null
     *
     * @since 1.0.0
     */
    public function getMetadata(): ?array
    {
        return $this->conversion->metadata;
    }

    /**
     * Check if this is the first conversion for the visitor.
     *
     *
     * @since 1.0.0
     */
    public function isFirstConversion(): bool
    {
        if ( null === $this->visitor ) {
            return true;
        }

        return Conversion::query()
            ->where( 'visitor_id', $this->visitor->id )
            ->where( 'goal_id', $this->goal->id )
            ->where( 'id', '!=', $this->conversion->id )
            ->doesntExist();
    }

    /**
     * Get the total conversion count for this visitor and goal.
     *
     *
     * @since 1.0.0
     */
    public function getVisitorConversionCount(): int
    {
        if ( null === $this->visitor ) {
            return 1;
        }

        return Conversion::query()
            ->where( 'visitor_id', $this->visitor->id )
            ->where( 'goal_id', $this->goal->id )
            ->count();
    }
}
