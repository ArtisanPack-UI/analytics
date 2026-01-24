<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Exceptions;

/**
 * Exception thrown when analytics query operations fail.
 *
 * This exception is thrown when queries for analytics data
 * encounter errors due to invalid parameters or database issues.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Exceptions
 */
class QueryException extends AnalyticsException
{
	/**
	 * Create an exception for an invalid date range.
	 *
	 * @param string $reason The reason the date range is invalid.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function invalidDateRange( string $reason ): static
	{
		return new static( __( 'Invalid date range: :reason', [ 'reason' => $reason ] ) );
	}

	/**
	 * Create an exception for an invalid filter.
	 *
	 * @param string $filterName The name of the invalid filter.
	 * @param string $reason     The reason the filter is invalid.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function invalidFilter( string $filterName, string $reason ): static
	{
		return new static( __( 'Invalid filter ":name": :reason', [
			'name'   => $filterName,
			'reason' => $reason,
		] ) );
	}

	/**
	 * Create an exception for an invalid metric.
	 *
	 * @param string $metricName The name of the invalid metric.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function invalidMetric( string $metricName ): static
	{
		return new static( __( 'Invalid or unsupported metric: :name', [ 'name' => $metricName ] ) );
	}

	/**
	 * Create an exception for an invalid dimension.
	 *
	 * @param string $dimensionName The name of the invalid dimension.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function invalidDimension( string $dimensionName ): static
	{
		return new static( __( 'Invalid or unsupported dimension: :name', [ 'name' => $dimensionName ] ) );
	}

	/**
	 * Create an exception for query timeout.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function queryTimeout(): static
	{
		return new static( __( 'Analytics query timed out. Try reducing the date range or simplifying the query.' ) );
	}
}
