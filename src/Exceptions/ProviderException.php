<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Exceptions;
use Throwable;

/**
 * Exception thrown when analytics provider operations fail.
 *
 * This exception is thrown when a provider encounters an error during
 * initialization, data transmission, or other operations.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Exceptions
 */
class ProviderException extends AnalyticsException
{
	/**
	 * The provider name that caused the exception.
	 *
	 * @var string
	 */
	protected string $providerName;

	/**
	 * Create a new ProviderException instance.
	 *
	 * @param string          $providerName The name of the provider.
	 * @param string          $message      The exception message.
	 * @param int             $code         The exception code.
	 * @param Throwable|null $previous     The previous exception for chaining.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $providerName, string $message = '', int $code = 0, ?Throwable $previous = null )
	{
		$this->providerName = $providerName;
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Get the provider name that caused the exception.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function getProviderName(): string
	{
		return $this->providerName;
	}

	/**
	 * Create an exception for when a provider is not found.
	 *
	 * @param string $providerName The name of the provider that was not found.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function providerNotFound( string $providerName ): static
	{
		return new static(
			$providerName,
			__( 'Analytics provider ":name" is not registered.', [ 'name' => $providerName ] ),
		);
	}

	/**
	 * Create an exception for when a provider is missing configuration.
	 *
	 * @param string $providerName  The name of the provider.
	 * @param string $missingConfig The missing configuration key.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function missingConfiguration( string $providerName, string $missingConfig ): static
	{
		return new static(
			$providerName,
			__( 'Analytics provider ":name" is missing required configuration: :config', [
				'name'   => $providerName,
				'config' => $missingConfig,
			] ),
		);
	}

	/**
	 * Create an exception for when a provider fails to send data.
	 *
	 * @param string $providerName The name of the provider.
	 * @param string $reason       The reason for the failure.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function sendFailed( string $providerName, string $reason ): static
	{
		return new static(
			$providerName,
			__( 'Analytics provider ":name" failed to send data: :reason', [
				'name'   => $providerName,
				'reason' => $reason,
			] ),
		);
	}

	/**
	 * Create an exception for when a provider is disabled.
	 *
	 * @param string $providerName The name of the provider.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function providerDisabled( string $providerName ): static
	{
		return new static(
			$providerName,
			__( 'Analytics provider ":name" is disabled.', [ 'name' => $providerName ] ),
		);
	}
}
