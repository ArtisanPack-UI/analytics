<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire;

use ArtisanPackUI\Analytics\Services\CrossTenantReporting;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Platform Dashboard Livewire component.
 *
 * Provides platform-wide analytics across all sites for admin users.
 * Uses CrossTenantReporting service for aggregate metrics.
 *
 * @property-read array<string, mixed>      $platformStats
 * @property-read Collection<int, mixed>    $topSites
 * @property-read Collection<int, mixed>    $sitesWithGrowth
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Livewire
 */
class PlatformDashboard extends Component
{
	/**
	 * Allowed date range values.
	 *
	 * @var array<int, string>
	 */
	protected const ALLOWED_DATE_RANGES = [
		'today',
		'24h',
		'7d',
		'30d',
		'90d',
		'thisYear',
		'lastYear',
	];

	/**
	 * Allowed export formats.
	 *
	 * Note: xlsx is not supported as it requires the PhpSpreadsheet library.
	 *
	 * @var array<int, string>
	 */
	protected const ALLOWED_EXPORT_FORMATS = ['csv', 'json'];

	/**
	 * The selected date range.
	 *
	 * @var string
	 */
	public string $dateRange = '7d';

	/**
	 * Number of top sites to display.
	 *
	 * @var int
	 */
	public int $topSitesLimit = 10;

	/**
	 * The CrossTenantReporting service.
	 *
	 * @var CrossTenantReporting
	 */
	protected CrossTenantReporting $reporting;

	/**
	 * Boot the component.
	 *
	 * @param CrossTenantReporting $reporting The reporting service.
	 *
	 * @return void
	 */
	public function boot( CrossTenantReporting $reporting ): void
	{
		$this->reporting = $reporting;
	}

	/**
	 * Get the platform-wide statistics.
	 *
	 * @return array<string, mixed>
	 */
	#[Computed]
	public function platformStats(): array
	{
		[ $startDate, $endDate ] = $this->getDateRange();

		return $this->reporting->getPlatformStats( $startDate, $endDate );
	}

	/**
	 * Get the top sites by traffic.
	 *
	 * @return Collection<int, mixed>
	 */
	#[Computed]
	public function topSites(): Collection
	{
		[ $startDate, $endDate ] = $this->getDateRange();

		return $this->reporting->getTopSitesByTraffic( $startDate, $endDate, $this->topSitesLimit );
	}

	/**
	 * Get sites with growth metrics.
	 *
	 * Compares the current period to the previous period of equal length.
	 *
	 * @return Collection<int, mixed>
	 */
	#[Computed]
	public function sitesWithGrowth(): Collection
	{
		[ $startDate, $endDate ] = $this->getDateRange();

		return $this->reporting->getSitesWithGrowth( $startDate, $endDate );
	}

	/**
	 * Update the date range.
	 *
	 * Only allowed date range values are accepted; invalid values are ignored.
	 *
	 * @param string $range The new date range.
	 *
	 * @return void
	 */
	public function setDateRange( string $range ): void
	{
		if ( ! in_array( $range, self::ALLOWED_DATE_RANGES, true ) ) {
			return;
		}

		$this->dateRange = $range;

		// Clear computed property cache
		unset( $this->platformStats, $this->topSites, $this->sitesWithGrowth );
	}

	/**
	 * Export the platform report.
	 *
	 * Only allowed export formats are accepted; defaults to CSV for invalid formats.
	 *
	 * @param string $format The export format (csv or json).
	 *
	 * @return \Symfony\Component\HttpFoundation\StreamedResponse
	 */
	public function exportReport( string $format = 'csv' ): \Symfony\Component\HttpFoundation\StreamedResponse
	{
		// Validate and sanitize format
		if ( ! in_array( $format, self::ALLOWED_EXPORT_FORMATS, true ) ) {
			$format = 'csv';
		}

		[ $startDate, $endDate ] = $this->getDateRange();

		$export = $this->reporting->exportPlatformReport( $startDate, $endDate, $format );

		return response()->streamDownload(
			function () use ( $export ): void {
				echo $export['content'];
			},
			$export['filename'],
			[
				'Content-Type' => $export['content_type'],
			],
		);
	}

	/**
	 * Render the component.
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'artisanpack-analytics::livewire.platform-dashboard', [
			'dateRangeOptions' => $this->getDateRangeOptions(),
		] );
	}

	/**
	 * Get the date range based on the selected option.
	 *
	 * For the 24h option, uses the current time as end to be consistent
	 * with the rolling 24-hour window semantics.
	 *
	 * @return array{0: Carbon, 1: Carbon}
	 */
	protected function getDateRange(): array
	{
		// For 24h, use current time as end; otherwise use end of day
		$endDate = '24h' === $this->dateRange
			? Carbon::now()
			: Carbon::now()->endOfDay();

		$startDate = match ( $this->dateRange ) {
			'today'    => Carbon::today()->startOfDay(),
			'24h'      => Carbon::now()->subHours( 24 ),
			'7d'       => Carbon::now()->subDays( 7 )->startOfDay(),
			'30d'      => Carbon::now()->subDays( 30 )->startOfDay(),
			'90d'      => Carbon::now()->subDays( 90 )->startOfDay(),
			'thisYear' => Carbon::now()->startOfYear(),
			'lastYear' => Carbon::now()->subYear()->startOfYear(),
			default    => Carbon::now()->subDays( 7 )->startOfDay(),
		};

		if ( 'lastYear' === $this->dateRange ) {
			$endDate = Carbon::now()->subYear()->endOfYear();
		}

		return [ $startDate, $endDate ];
	}

	/**
	 * Get available date range options.
	 *
	 * @return array<string, string>
	 */
	protected function getDateRangeOptions(): array
	{
		return [
			'today'    => __( 'Today' ),
			'24h'      => __( 'Last 24 Hours' ),
			'7d'       => __( 'Last 7 Days' ),
			'30d'      => __( 'Last 30 Days' ),
			'90d'      => __( 'Last 90 Days' ),
			'thisYear' => __( 'This Year' ),
			'lastYear' => __( 'Last Year' ),
		];
	}
}
