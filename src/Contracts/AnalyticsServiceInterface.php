<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Data\VisitorData;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;

/**
 * Interface for the main analytics service.
 *
 * This interface defines the primary API for tracking analytics data
 * including page views, events, sessions, and visitor identification.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Contracts
 */
interface AnalyticsServiceInterface
{
	/**
	 * Track a page view.
	 *
	 * Validates the data and routes it to all active providers.
	 *
	 * @param PageViewData $data The page view data to track.
	 *
	 * @since 1.0.0
	 */
	public function trackPageView( PageViewData $data ): void;

	/**
	 * Track a custom event.
	 *
	 * Validates the data and routes it to all active providers.
	 *
	 * @param EventData $data The event data to track.
	 *
	 * @since 1.0.0
	 */
	public function trackEvent( EventData $data ): void;

	/**
	 * Start a new session.
	 *
	 * Creates a new analytics session for tracking user activity.
	 *
	 * @param SessionData $data The session initialization data.
	 *
	 * @return Session The created session instance.
	 *
	 * @since 1.0.0
	 */
	public function startSession( SessionData $data ): Session;

	/**
	 * End an existing session.
	 *
	 * Finalizes the session with duration and engagement metrics.
	 *
	 * @param string $sessionId The session ID to end.
	 *
	 * @since 1.0.0
	 */
	public function endSession( string $sessionId ): void;

	/**
	 * Extend an existing session.
	 *
	 * Updates the last activity timestamp to keep the session alive.
	 *
	 * @param string $sessionId The session ID to extend.
	 *
	 * @since 1.0.0
	 */
	public function extendSession( string $sessionId ): void;

	/**
	 * Resolve or create a visitor.
	 *
	 * Identifies a visitor using fingerprinting or other methods.
	 *
	 * @param VisitorData $data The visitor identification data.
	 *
	 * @return Visitor The resolved or created visitor instance.
	 *
	 * @since 1.0.0
	 */
	public function resolveVisitor( VisitorData $data ): Visitor;

	/**
	 * Check if tracking is allowed for the current request.
	 *
	 * Evaluates privacy settings, consent status, and other factors.
	 *
	 * @return bool True if tracking is allowed.
	 *
	 * @since 1.0.0
	 */
	public function canTrack(): bool;
}
