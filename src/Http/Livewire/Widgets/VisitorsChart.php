<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Visitors Chart Widget.
 *
 * Displays a line chart of page views and visitors over time.
 * Designed for integration with Chart.js.
 *
 * @since 1.0.0
 */
class VisitorsChart extends Component
{
	use WithAnalyticsWidget;

	/**
	 * The chart data.
	 *
	 * @var array<string, mixed>
	 */
	public array $chartData = [];

	/**
	 * The time granularity.
	 */
	public string $granularity = 'day';

	/**
	 * Which metrics to display.
	 *
	 * @var array<string>
	 */
	public array $metrics = [ 'pageviews', 'visitors' ];

	/**
	 * Chart height in pixels.
	 */
	public int $height = 300;

	/**
	 * Mount the component.
	 *
	 * @param string|null    $dateRangePreset The initial date range.
	 * @param int|null       $siteId          Site ID filter.
	 * @param string         $granularity     Time granularity.
	 * @param array<string>|null $metrics        Which metrics to show.
	 * @param int            $height          Chart height.
	 *
	 * @since 1.0.0
	 */
	public function mount(
		?string $dateRangePreset = null,
		?int $siteId = null,
		string $granularity = 'day',
		?array $metrics = null,
		int $height = 300,
	): void {
		$this->initializeWidget( $dateRangePreset, $siteId );
		$this->granularity = $granularity;
		$this->height      = $height;

		if ( null !== $metrics ) {
			$this->metrics = $metrics;
		}

		$this->loadChartData();
	}

	/**
	 * Load the chart data.
	 *
	 * @since 1.0.0
	 */
	public function loadChartData(): void
	{
		$this->isLoading = true;

		$data = $this->getAnalyticsQuery()->getPageViews(
			$this->getDateRange(),
			$this->granularity,
			$this->getFilters(),
		);

		$this->chartData = $this->formatChartData( $data );
		$this->isLoading = false;
	}

	/**
	 * Set the granularity and refresh.
	 *
	 * @param string $granularity The time granularity.
	 *
	 * @since 1.0.0
	 */
	public function setGranularity( string $granularity ): void
	{
		$this->granularity = $granularity;
		$this->loadChartData();
	}

	/**
	 * Refresh the widget data.
	 *
	 * @since 1.0.0
	 */
	#[On( 'refresh-analytics-widgets' )]
	public function refreshData(): void
	{
		$this->loadChartData();
	}

	/**
	 * Get the Chart.js configuration.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getChartConfig(): array
	{
		return [
			'type'    => 'line',
			'data'    => $this->chartData,
			'options' => [
				'responsive'          => true,
				'maintainAspectRatio' => false,
				'interaction'         => [
					'intersect' => false,
					'mode'      => 'index',
				],
				'plugins' => [
					'legend' => [
						'position' => 'top',
					],
					'tooltip' => [
						'enabled' => true,
					],
				],
				'scales' => [
					'y' => [
						'beginAtZero' => true,
						'grid'        => [
							'display' => true,
						],
					],
					'x' => [
						'grid' => [
							'display' => false,
						],
					],
				],
			],
		];
	}

	/**
	 * Get the view for the component.
	 *
	 * @since 1.0.0
	 */
	public function render(): \Illuminate\Contracts\View\View
	{
		return view( 'artisanpack-analytics::livewire.widgets.visitors-chart' );
	}

	/**
	 * Format the data for Chart.js.
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
		$datasets  = [];

		// Initialize datasets
		$pageviewsData = [];
		$visitorsData  = [];

		foreach ( $data as $item ) {
			$labels[]        = $this->formatLabel( $item['date'] ?? '' );
			$pageviewsData[] = $item['pageviews'] ?? 0;
			$visitorsData[]  = $item['visitors'] ?? 0;
		}

		if ( in_array( 'pageviews', $this->metrics, true ) ) {
			$datasets[] = [
				'label'           => __( 'Page Views' ),
				'data'            => $pageviewsData,
				'borderColor'     => 'rgb(59, 130, 246)',
				'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
				'fill'            => true,
				'tension'         => 0.4,
			];
		}

		if ( in_array( 'visitors', $this->metrics, true ) ) {
			$datasets[] = [
				'label'           => __( 'Visitors' ),
				'data'            => $visitorsData,
				'borderColor'     => 'rgb(16, 185, 129)',
				'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
				'fill'            => true,
				'tension'         => 0.4,
			];
		}

		return [
			'labels'   => $labels,
			'datasets' => $datasets,
		];
	}

	/**
	 * Format a date label based on granularity.
	 *
	 * @param string $date The date string.
	 *
	 * @since 1.0.0
	 */
	protected function formatLabel( string $date ): string
	{
		if ( empty( $date ) ) {
			return '';
		}

		$carbon = \Carbon\Carbon::parse( $date );

		return match ( $this->granularity ) {
			'hour'  => $carbon->format( 'H:i' ),
			'day'   => $carbon->format( 'M j' ),
			'week'  => __( 'Week of ' ) . $carbon->format( 'M j' ),
			'month' => $carbon->format( 'M Y' ),
			default => $carbon->format( 'M j' ),
		};
	}
}
