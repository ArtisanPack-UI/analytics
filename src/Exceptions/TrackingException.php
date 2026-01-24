<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Exceptions;

/**
 * Exception thrown when tracking operations fail.
 *
 * This exception is thrown when page views, events, or sessions
 * cannot be tracked due to validation errors or processing failures.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Exceptions
 */
class TrackingException extends AnalyticsException
{
	/**
	 * Create an exception for invalid page view data.
	 *
	 * @param string $reason The reason the data is invalid.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function invalidPageViewData( string $reason ): static
	{
		return new static( __( 'Invalid page view data: :reason', [ 'reason' => $reason ] ) );
	}

	/**
	 * Create an exception for invalid event data.
	 *
	 * @param string $reason The reason the data is invalid.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function invalidEventData( string $reason ): static
	{
		return new static( __( 'Invalid event data: :reason', [ 'reason' => $reason ] ) );
	}

	/**
	 * Create an exception for invalid session data.
	 *
	 * @param string $reason The reason the data is invalid.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function invalidSessionData( string $reason ): static
	{
		return new static( __( 'Invalid session data: :reason', [ 'reason' => $reason ] ) );
	}

	/**
	 * Create an exception for when tracking is blocked by privacy settings.
	 *
	 * @param string $reason The specific privacy rule that blocked tracking.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function blockedByPrivacy( string $reason ): static
	{
		return new static( __( 'Tracking blocked by privacy settings: :reason', [ 'reason' => $reason ] ) );
	}

	/**
	 * Create an exception for missing consent.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function consentRequired(): static
	{
		return new static( __( 'User consent is required for tracking.' ) );
	}

	/**
	 * Create an exception for rate limiting.
	 *
	 * @param int $retryAfter Seconds until the rate limit resets.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function rateLimited( int $retryAfter ): static
	{
		return new static( __( 'Rate limit exceeded. Retry after :seconds seconds.', [ 'seconds' => $retryAfter ] ) );
	}
}
