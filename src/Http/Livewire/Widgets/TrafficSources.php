<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Traffic Sources Widget.
 *
 * Displays traffic sources with optional chart visualization.
 *
 * @since 1.0.0
 */
class TrafficSources extends Component
{
	use WithAnalyticsWidget;

	/**
	 * The traffic sources data.
	 *
	 * @var Collection<int, array{source: string, medium: string, sessions: int, visitors: int}>
	 */
	public Collection $sources;

	/**
	 * Maximum number of sources to display.
	 */
	public int $limit = 10;

	/**
	 * Chart type to display.
	 */
	public string $chartType = 'pie';

	/**
	 * Whether to show the chart.
	 */
	public bool $showChart = true;

	/**
	 * Mount the component.
	 *
	 * @param string|null $dateRangePreset The initial date range.
	 * @param int|null    $siteId          Site ID filter.
	 * @param int         $limit           Maximum sources to show.
	 * @param string      $chartType       Chart type ('pie', 'bar', 'doughnut').
	 * @param bool        $showChart       Whether to show chart.
	 *
	 * @since 1.0.0
	 */
	public function mount(
		?string $dateRangePreset = null,
		?int $siteId = null,
		int $limit = 10,
		string $chartType = 'pie',
		bool $showChart = true,
	): void {
		$this->sources = collect();
		$this->initializeWidget( $dateRangePreset, $siteId );
		$this->limit     = $limit;
		$this->chartType = $chartType;
		$this->showChart = $showChart;
		$this->loadSources();
	}

	/**
	 * Load the traffic sources data.
	 *
	 * @since 1.0.0
	 */
	public function loadSources(): void
	{
		$this->isLoading = true;

		$this->sources = $this->getAnalyticsQuery()->getTrafficSources(
			$this->getDateRange(),
			$this->limit,
			$this->getFilters(),
		);

		$this->isLoading = false;
	}

	/**
	 * Refresh the widget data.
	 *
	 * @since 1.0.0
	 */
	#[On( 'refresh-analytics-widgets' )]
	public function refreshData(): void
	{
		$this->loadSources();
	}

	/**
	 * Get the chart data for Chart.js.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getChartData(): array
	{
		$labels = [];
		$data   = [];
		$colors = $this->getChartColors();

		foreach ( $this->sources as $index => $source ) {
			$labels[] = $source['source'] ?? __( 'Direct' );
			$data[]   = $source['sessions'] ?? 0;
		}

		return [
			'labels'   => $labels,
			'datasets' => [
				[
					'data'            => $data,
					'backgroundColor' => array_slice( $colors, 0, count( $data ) ),
					'borderWidth'     => 0,
				],
			],
		];
	}

	/**
	 * Get chart configuration for Chart.js.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getChartConfig(): array
	{
		return [
			'type'    => $this->chartType,
			'data'    => $this->getChartData(),
			'options' => [
				'responsive'          => true,
				'maintainAspectRatio' => false,
				'plugins'             => [
					'legend' => [
						'position' => 'bottom',
						'labels'   => [
							'usePointStyle' => true,
							'padding'       => 15,
						],
					],
					'tooltip' => [
						'usePercentageLabel' => true,
					],
				],
			],
		];
	}

	/**
	 * Get the total sessions.
	 *
	 * @since 1.0.0
	 */
	public function getTotalSessions(): int
	{
		return $this->sources->sum( 'sessions' );
	}

	/**
	 * Get the view for the component.
	 *
	 * @since 1.0.0
	 */
	public function render(): \Illuminate\Contracts\View\View
	{
		return view( 'artisanpack-analytics::livewire.widgets.traffic-sources' );
	}

	/**
	 * Get the chart colors.
	 *
	 * @return array<string>
	 *
	 * @since 1.0.0
	 */
	protected function getChartColors(): array
	{
		return [
			'rgb(59, 130, 246)',   // Blue
			'rgb(16, 185, 129)',   // Green
			'rgb(245, 158, 11)',   // Yellow
			'rgb(239, 68, 68)',    // Red
			'rgb(139, 92, 246)',   // Purple
			'rgb(236, 72, 153)',   // Pink
			'rgb(20, 184, 166)',   // Teal
			'rgb(249, 115, 22)',   // Orange
			'rgb(99, 102, 241)',   // Indigo
			'rgb(107, 114, 128)',  // Gray
		];
	}
}
