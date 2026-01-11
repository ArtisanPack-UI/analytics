<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Concerns;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;

/**
 * Trait for analytics widgets with shared functionality.
 *
 * Provides common features like date range handling, loading states,
 * and analytics query access for Livewire widgets.
 *
 * @since 1.0.0
 */
trait WithAnalyticsWidget
{
	/**
	 * The current date range preset.
	 */
	public string $dateRangePreset = '30d';

	/**
	 * Whether the widget is currently loading.
	 */
	public bool $isLoading = true;

	/**
	 * Custom start date (for custom range).
	 */
	public ?string $customStartDate = null;

	/**
	 * Custom end date (for custom range).
	 */
	public ?string $customEndDate = null;

	/**
	 * Site ID filter for multi-site support.
	 */
	public ?int $siteId = null;

	/**
	 * Initialize the widget with default date range.
	 *
	 * Call this in your component's mount() method.
	 *
	 * @param string|null $dateRangePreset Default date range preset.
	 * @param int|null    $siteId          Site ID for filtering.
	 *
	 * @since 1.0.0
	 */
	public function initializeWidget( ?string $dateRangePreset = null, ?int $siteId = null ): void
	{
		$configValue           = config( 'artisanpack.analytics.dashboard.default_date_range', '30d' );
		$this->dateRangePreset = $dateRangePreset ?? ( is_string( $configValue ) ? $configValue : '30d' );
		$this->siteId          = $siteId;
		$this->isLoading       = false;
	}

	/**
	 * Set the date range preset and refresh data.
	 *
	 * @param string $preset The date range preset.
	 *
	 * @since 1.0.0
	 */
	public function setDateRange( string $preset ): void
	{
		$this->dateRangePreset = $preset;
		$this->refreshData();
	}

	/**
	 * Set a custom date range.
	 *
	 * @param string $startDate The start date.
	 * @param string $endDate   The end date.
	 *
	 * @since 1.0.0
	 */
	public function setCustomDateRange( string $startDate, string $endDate ): void
	{
		$this->dateRangePreset = 'custom';
		$this->customStartDate = $startDate;
		$this->customEndDate   = $endDate;
		$this->refreshData();
	}

	/**
	 * Refresh the widget data.
	 *
	 * Override this method in your component to load data.
	 *
	 * @since 1.0.0
	 */
	public function refreshData(): void
	{
		$this->isLoading = true;

		// Subclasses should implement their own data loading logic
		// and set isLoading = false when complete
	}

	/**
	 * Get available date range presets.
	 *
	 * @return array<string, string>
	 *
	 * @since 1.0.0
	 */
	public function getDateRangePresets(): array
	{
		return [
			'24h'   => __( 'Last 24 hours' ),
			'7d'    => __( 'Last 7 days' ),
			'30d'   => __( 'Last 30 days' ),
			'90d'   => __( 'Last 90 days' ),
			'1y'    => __( 'Last year' ),
			'today' => __( 'Today' ),
			'week'  => __( 'This week' ),
			'month' => __( 'This month' ),
			'year'  => __( 'This year' ),
		];
	}

	/**
	 * Get available date range options (alias for presets).
	 *
	 * @return array<string, string>
	 *
	 * @since 1.0.0
	 */
	public function getDateRangeOptions(): array
	{
		return $this->getDateRangePresets();
	}

	/**
	 * Get the label for the current date range preset.
	 *
	 * @since 1.0.0
	 */
	public function getDateRangeLabel(): string
	{
		$presets = $this->getDateRangePresets();

		return $presets[ $this->dateRangePreset ] ?? $this->dateRangePreset;
	}

	/**
	 * Get the analytics query service.
	 *
	 * @since 1.0.0
	 */
	protected function getAnalyticsQuery(): AnalyticsQuery
	{
		return app( AnalyticsQuery::class );
	}

	/**
	 * Get the current date range based on the preset.
	 *
	 * @since 1.0.0
	 */
	protected function getDateRange(): DateRange
	{
		return match ( $this->dateRangePreset ) {
			'24h'    => DateRange::lastDays( 1 ),
			'7d'     => DateRange::lastDays( 7 ),
			'30d'    => DateRange::lastDays( 30 ),
			'90d'    => DateRange::lastDays( 90 ),
			'1y'     => DateRange::lastDays( 365 ),
			'today'  => DateRange::today(),
			'week'   => DateRange::thisWeek(),
			'month'  => DateRange::thisMonth(),
			'year'   => DateRange::thisYear(),
			'custom' => $this->getCustomDateRange(),
			default  => DateRange::lastDays( 30 ),
		};
	}

	/**
	 * Get a custom date range from the custom dates.
	 *
	 * @since 1.0.0
	 */
	protected function getCustomDateRange(): DateRange
	{
		if ( $this->customStartDate && $this->customEndDate ) {
			return DateRange::fromStrings( $this->customStartDate, $this->customEndDate );
		}

		return DateRange::lastDays( 30 );
	}

	/**
	 * Get filters array including site ID if set.
	 *
	 * @param array<string, mixed> $additionalFilters Additional filters to merge.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	protected function getFilters( array $additionalFilters = [] ): array
	{
		$filters = [];

		if ( null !== $this->siteId ) {
			$filters['site_id'] = $this->siteId;
		}

		return array_merge( $filters, $additionalFilters );
	}

	/**
	 * Format a number for display.
	 *
	 * @param float|int $number The number to format.
	 *
	 * @since 1.0.0
	 */
	protected function formatNumber( int|float $number ): string
	{
		if ( $number >= 1000000 ) {
			return round( $number / 1000000, 1 ) . 'M';
		}

		if ( $number >= 1000 ) {
			return round( $number / 1000, 1 ) . 'K';
		}

		return number_format( $number );
	}

	/**
	 * Format a percentage for display.
	 *
	 * @param float $percentage The percentage to format.
	 *
	 * @since 1.0.0
	 */
	protected function formatPercentage( float $percentage ): string
	{
		return number_format( $percentage, 1 ) . '%';
	}

	/**
	 * Format a duration in seconds for display.
	 *
	 * @param int $seconds The duration in seconds.
	 *
	 * @since 1.0.0
	 */
	protected function formatDuration( int $seconds ): string
	{
		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		$minutes = floor( $seconds / 60 );
		$secs    = $seconds % 60;

		if ( $minutes < 60 ) {
			return $minutes . 'm ' . $secs . 's';
		}

		$hours = floor( $minutes / 60 );
		$mins  = $minutes % 60;

		return $hours . 'h ' . $mins . 'm';
	}

	/**
	 * Get the trend indicator class.
	 *
	 * @param float $change   The change percentage.
	 * @param bool  $positive Whether positive change is good.
	 *
	 * @since 1.0.0
	 */
	protected function getTrendClass( float $change, bool $positive = true ): string
	{
		if ( 0.0 === $change ) {
			return 'text-gray-500';
		}

		$isUp = $change > 0;

		if ( $positive ) {
			return $isUp ? 'text-success' : 'text-error';
		}

		return $isUp ? 'text-error' : 'text-success';
	}

	/**
	 * Get the trend indicator icon.
	 *
	 * @param float $change The change percentage.
	 *
	 * @since 1.0.0
	 */
	protected function getTrendIcon( float $change ): string
	{
		if ( 0.0 === $change ) {
			return 'minus';
		}

		return $change > 0 ? 'arrow-up' : 'arrow-down';
	}
}
