<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Analytics Dashboard Component.
 *
 * Main dashboard component that combines all analytics widgets
 * with period selection, tab navigation, and export functionality.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Livewire
 */
class AnalyticsDashboard extends Component
{
	use WithAnalyticsWidget;

	/**
	 * The currently active tab.
	 */
	public string $activeTab = 'overview';

	/**
	 * Statistics data for overview.
	 *
	 * @var array<string, mixed>
	 */
	public array $stats = [];

	/**
	 * Page views over time data.
	 *
	 * @var array<string, mixed>
	 */
	public array $chartData = [];

	/**
	 * Top pages data.
	 */
	public Collection $topPages;

	/**
	 * Traffic sources data.
	 */
	public Collection $trafficSources;

	/**
	 * Device breakdown data.
	 */
	public Collection $deviceBreakdown;

	/**
	 * Browser breakdown data.
	 */
	public Collection $browserBreakdown;

	/**
	 * Country breakdown data.
	 */
	public Collection $countryBreakdown;

	/**
	 * Mount the component.
	 *
	 * @param string|null $dateRangePreset The initial date range.
	 * @param int|null    $siteId          Site ID filter.
	 * @param string      $activeTab       Initial active tab.
	 *
	 * @since 1.0.0
	 */
	public function mount(
		?string $dateRangePreset = null,
		?int $siteId = null,
		string $activeTab = 'overview',
	): void {
		$this->topPages         = collect();
		$this->trafficSources   = collect();
		$this->deviceBreakdown  = collect();
		$this->browserBreakdown = collect();
		$this->countryBreakdown = collect();

		$this->initializeWidget( $dateRangePreset, $siteId );
		$this->activeTab = $activeTab;
		$this->loadAllData();
	}

	/**
	 * Load all dashboard data.
	 *
	 * @since 1.0.0
	 */
	public function loadAllData(): void
	{
		$this->isLoading = true;

		$range   = $this->getDateRange();
		$filters = $this->getFilters();
		$query   = $this->getAnalyticsQuery();

		// Load stats with comparison
		$this->stats = $query->getStats( $range, true, $filters );

		// Load chart data
		$chartRawData    = $query->getPageViews( $range, 'day', $filters );
		$this->chartData = $this->formatChartData( $chartRawData );

		// Load tab-specific data
		$this->topPages         = $query->getTopPages( $range, 10, $filters );
		$this->trafficSources   = $query->getTrafficSources( $range, 10, $filters );
		$this->deviceBreakdown  = $query->getDeviceBreakdown( $range, $filters );
		$this->browserBreakdown = $query->getBrowserBreakdown( $range, 10, $filters );
		$this->countryBreakdown = $query->getCountryBreakdown( $range, 10, $filters );

		$this->isLoading = false;
	}

	/**
	 * Switch to a different tab.
	 *
	 * @param string $tab The tab to switch to.
	 *
	 * @since 1.0.0
	 */
	public function switchTab( string $tab ): void
	{
		$this->activeTab = $tab;
	}

	/**
	 * Refresh the dashboard data.
	 *
	 * @since 1.0.0
	 */
	public function refreshData(): void
	{
		$this->loadAllData();

		// Dispatch event to refresh all child widget components
		$this->dispatch( 'refresh-analytics-widgets' );
	}

	/**
	 * Get the available tabs.
	 *
	 * @return array<string, array{label: string, icon: string}>
	 *
	 * @since 1.0.0
	 */
	public function getTabs(): array
	{
		return [
			'overview' => [
				'label' => __( 'Overview' ),
				'icon'  => 'chart-bar',
			],
			'pages' => [
				'label' => __( 'Pages' ),
				'icon'  => 'document-text',
			],
			'traffic' => [
				'label' => __( 'Traffic' ),
				'icon'  => 'arrow-trending-up',
			],
			'audience' => [
				'label' => __( 'Audience' ),
				'icon'  => 'users',
			],
		];
	}

	/**
	 * Export dashboard data as CSV.
	 *
	 * @return StreamedResponse The CSV file download response.
	 *
	 * @since 1.0.0
	 */
	public function exportCsv(): StreamedResponse
	{
		$filename = 'analytics-export-' . now()->format( 'Y-m-d' ) . '.csv';

		return response()->streamDownload( function (): void {
			$handle = fopen( 'php://output', 'w' );

			if ( false === $handle ) {
				return;
			}

			try {
				// Write headers
				fputcsv( $handle, [
					__( 'Metric' ),
					__( 'Value' ),
					__( 'Previous Period' ),
					__( 'Change %' ),
				] );

				// Write stats
				$statsLabels = [
					'pageviews'            => __( 'Page Views' ),
					'visitors'             => __( 'Visitors' ),
					'sessions'             => __( 'Sessions' ),
					'bounce_rate'          => __( 'Bounce Rate' ),
					'avg_session_duration' => __( 'Avg. Session Duration' ),
				];

				foreach ( $statsLabels as $key => $label ) {
					$value    = $this->stats[ $key ] ?? 0;
					$previous = $this->stats['comparison'][ $key ]['value'] ?? 0;
					$change   = $this->stats['comparison'][ $key ]['change'] ?? 0;

					fputcsv( $handle, [
						$label,
						$value,
						$previous,
						$change . '%',
					] );
				}

				// Add separator
				fputcsv( $handle, [] );
				fputcsv( $handle, [ __( 'Top Pages' ) ] );
				fputcsv( $handle, [ __( 'Path' ), __( 'Title' ), __( 'Views' ), __( 'Unique Views' ) ] );

				foreach ( $this->topPages as $page ) {
					fputcsv( $handle, [
						$page['path'] ?? '',
						$page['title'] ?? '',
						$page['views'] ?? 0,
						$page['unique_views'] ?? 0,
					] );
				}

				// Traffic sources
				fputcsv( $handle, [] );
				fputcsv( $handle, [ __( 'Traffic Sources' ) ] );
				fputcsv( $handle, [ __( 'Source' ), __( 'Medium' ), __( 'Sessions' ), __( 'Visitors' ) ] );

				foreach ( $this->trafficSources as $source ) {
					fputcsv( $handle, [
						$source['source'] ?? '',
						$source['medium'] ?? '',
						$source['sessions'] ?? 0,
						$source['visitors'] ?? 0,
					] );
				}
			} finally {
				fclose( $handle );
			}
		}, $filename, [
			'Content-Type' => 'text/csv',
		] );
	}

	/**
	 * Export dashboard data as JSON.
	 *
	 * @return StreamedResponse The JSON file download response.
	 *
	 * @since 1.0.0
	 */
	public function exportJson(): StreamedResponse
	{
		$filename = 'analytics-export-' . now()->format( 'Y-m-d' ) . '.json';

		$data = [
			'exported_at'       => now()->toIso8601String(),
			'date_range'        => [
				'preset' => $this->dateRangePreset,
				'start'  => $this->getDateRange()->startDate->toDateString(),
				'end'    => $this->getDateRange()->endDate->toDateString(),
			],
			'stats'             => $this->stats,
			'top_pages'         => $this->topPages->toArray(),
			'traffic_sources'   => $this->trafficSources->toArray(),
			'device_breakdown'  => $this->deviceBreakdown->toArray(),
			'browser_breakdown' => $this->browserBreakdown->toArray(),
			'country_breakdown' => $this->countryBreakdown->toArray(),
		];

		return response()->streamDownload( function () use ( $data ): void {
			echo json_encode( $data, JSON_PRETTY_PRINT );
		}, $filename, [
			'Content-Type' => 'application/json',
		] );
	}

	/**
	 * Get the view for the component.
	 *
	 * @return \Illuminate\Contracts\View\View The component view.
	 *
	 * @since 1.0.0
	 */
	public function render(): \Illuminate\Contracts\View\View
	{
		return view( 'artisanpack-analytics::livewire.analytics-dashboard' );
	}

	/**
	 * Format chart data for Chart.js.
	 *
	 * @param Collection<int, array{date: string, pageviews: int, visitors: int}> $data The raw data.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	protected function formatChartData( Collection $data ): array
	{
		$labels    = [];
		$pageviews = [];
		$visitors  = [];

		foreach ( $data as $item ) {
			$date        = \Carbon\Carbon::parse( $item['date'] ?? '' );
			$labels[]    = $date->format( 'M j' );
			$pageviews[] = $item['pageviews'] ?? 0;
			$visitors[]  = $item['visitors'] ?? 0;
		}

		return [
			'labels'   => $labels,
			'datasets' => [
				[
					'label'           => __( 'Page Views' ),
					'data'            => $pageviews,
					'borderColor'     => 'rgb(59, 130, 246)',
					'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
					'fill'            => true,
					'tension'         => 0.4,
				],
				[
					'label'           => __( 'Visitors' ),
					'data'            => $visitors,
					'borderColor'     => 'rgb(16, 185, 129)',
					'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
					'fill'            => true,
					'tension'         => 0.4,
				],
			],
		];
	}
}
