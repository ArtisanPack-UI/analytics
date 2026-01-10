<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Data;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Data Transfer Object for date range queries.
 *
 * Represents a date range for analytics queries with helper methods
 * for common date range operations.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Data
 */
readonly class DateRange
{
	/**
	 * Create a new DateRange instance.
	 *
	 * @param CarbonInterface $startDate The start date of the range.
	 * @param CarbonInterface $endDate   The end date of the range.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public CarbonInterface $startDate,
		public CarbonInterface $endDate,
	) {
	}

	/**
	 * Create a date range for the last N days.
	 *
	 * @param int $days Number of days to include.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function lastDays( int $days ): static
	{
		return new static(
			Carbon::now()->subDays( $days )->startOfDay(),
			Carbon::now()->endOfDay(),
		);
	}

	/**
	 * Create a date range for today.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function today(): static
	{
		return new static(
			Carbon::today()->startOfDay(),
			Carbon::today()->endOfDay(),
		);
	}

	/**
	 * Create a date range for yesterday.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function yesterday(): static
	{
		return new static(
			Carbon::yesterday()->startOfDay(),
			Carbon::yesterday()->endOfDay(),
		);
	}

	/**
	 * Create a date range for this week.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function thisWeek(): static
	{
		return new static(
			Carbon::now()->startOfWeek(),
			Carbon::now()->endOfWeek(),
		);
	}

	/**
	 * Create a date range for last week.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function lastWeek(): static
	{
		return new static(
			Carbon::now()->subWeek()->startOfWeek(),
			Carbon::now()->subWeek()->endOfWeek(),
		);
	}

	/**
	 * Create a date range for this month.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function thisMonth(): static
	{
		return new static(
			Carbon::now()->startOfMonth(),
			Carbon::now()->endOfMonth(),
		);
	}

	/**
	 * Create a date range for last month.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function lastMonth(): static
	{
		return new static(
			Carbon::now()->subMonth()->startOfMonth(),
			Carbon::now()->subMonth()->endOfMonth(),
		);
	}

	/**
	 * Create a date range for this year.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function thisYear(): static
	{
		return new static(
			Carbon::now()->startOfYear(),
			Carbon::now()->endOfYear(),
		);
	}

	/**
	 * Create a date range from string dates.
	 *
	 * @param string $startDate The start date string.
	 * @param string $endDate   The end date string.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function fromStrings( string $startDate, string $endDate ): static
	{
		return new static(
			Carbon::parse( $startDate )->startOfDay(),
			Carbon::parse( $endDate )->endOfDay(),
		);
	}

	/**
	 * Get the number of days in the range.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function getDays(): int
	{
		return (int) $this->startDate->diffInDays( $this->endDate ) + 1;
	}

	/**
	 * Get a cache key for this date range.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function toKey(): string
	{
		return $this->startDate->format( 'Y-m-d' ) . '_' . $this->endDate->format( 'Y-m-d' );
	}

	/**
	 * Get the previous period of the same length.
	 *
	 * Useful for period-over-period comparisons.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function getPreviousPeriod(): static
	{
		$days = $this->getDays();

		return new static(
			$this->startDate->copy()->subDays( $days ),
			$this->startDate->copy()->subDay()->endOfDay(),
		);
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array{start_date: string, end_date: string}
	 *
	 * @since 1.0.0
	 */
	public function toArray(): array
	{
		return [
			'start_date' => $this->startDate->toDateTimeString(),
			'end_date'   => $this->endDate->toDateTimeString(),
		];
	}
}
