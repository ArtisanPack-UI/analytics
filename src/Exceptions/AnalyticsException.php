<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception class for all analytics-related errors.
 *
 * This exception serves as the parent class for all analytics exceptions,
 * allowing consumers to catch all analytics errors with a single catch block.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Exceptions
 */
class AnalyticsException extends Exception
{
	/**
	 * Create a new AnalyticsException instance.
	 *
	 * @param string          $message  The exception message.
	 * @param int             $code     The exception code.
	 * @param Throwable|null $previous The previous exception for chaining.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $message = '', int $code = 0, ?Throwable $previous = null )
	{
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Create an exception for when analytics is disabled.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function analyticsDisabled(): static
	{
		return new static( __( 'Analytics tracking is currently disabled.' ) );
	}

	/**
	 * Create an exception for an invalid configuration.
	 *
	 * @param string $key The configuration key that is invalid.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function invalidConfiguration( string $key ): static
	{
		return new static( __( 'Invalid analytics configuration for key: :key', [ 'key' => $key ] ) );
	}
}
