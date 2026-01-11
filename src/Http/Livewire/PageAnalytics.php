<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Page Analytics Component.
 *
 * Displays analytics for a specific page, designed for
 * integration with visual editors and page-level stats.
 *
 * @since 1.0.0
 */
class PageAnalytics extends Component
{
	use WithAnalyticsWidget;

	/**
	 * The page path to display analytics for.
	 */
	public string $path = '/';

	/**
	 * The page analytics data.
	 *
	 * @var array<string, mixed>
	 */
	public array $analytics = [];

	/**
	 * Page views over time.
	 */
	public Collection $viewsOverTime;

	/**
	 * Whether to show inline chart.
	 */
	public bool $showChart = true;

	/**
	 * Whether to show as compact view.
	 */
	public bool $compact = false;

	/**
	 * Mount the component.
	 *
	 * @param string      $path            The page path.
	 * @param string|null $dateRangePreset The initial date range.
	 * @param int|null    $siteId          Site ID filter.
	 * @param bool        $showChart       Whether to show chart.
	 * @param bool        $compact         Whether to use compact view.
	 *
	 * @since 1.0.0
	 */
	public function mount(
		string $path = '/',
		?string $dateRangePreset = null,
		?int $siteId = null,
		bool $showChart = true,
		bool $compact = false,
	): void {
		$this->viewsOverTime = collect();
		$this->path          = $path;
		$this->showChart     = $showChart;
		$this->compact       = $compact;

		$this->initializeWidget( $dateRangePreset, $siteId );
		$this->loadPageAnalytics();
	}

	/**
	 * Load the page analytics data.
	 *
	 * @since 1.0.0
	 */
	public function loadPageAnalytics(): void
	{
		$this->isLoading = true;

		$range   = $this->getDateRange();
		$filters = $this->getFilters();

		$data = $this->getAnalyticsQuery()->getPageAnalytics(
			$this->path,
			$range,
			$filters,
		);

		$this->analytics     = $data;
		$this->viewsOverTime = collect( $data['over_time'] ?? [] );

		$this->isLoading = false;
	}

	/**
	 * Set the page path and reload.
	 *
	 * @param string $path The new page path.
	 *
	 * @since 1.0.0
	 */
	public function setPath( string $path ): void
	{
		$this->path = $path;
		$this->loadPageAnalytics();
	}

	/**
	 * Refresh the widget data.
	 *
	 * @since 1.0.0
	 */
	public function refreshData(): void
	{
		$this->loadPageAnalytics();
	}

	/**
	 * Get the inline chart data.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getChartData(): array
	{
		$labels = [];
		$data   = [];

		foreach ( $this->viewsOverTime as $item ) {
			$date     = \Carbon\Carbon::parse( $item['date'] ?? '' );
			$labels[] = $date->format( 'M j' );
			$data[]   = $item['pageviews'] ?? 0;
		}

		return [
			'labels'   => $labels,
			'datasets' => [
				[
					'data'            => $data,
					'borderColor'     => 'rgb(59, 130, 246)',
					'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
					'fill'            => true,
					'tension'         => 0.4,
					'pointRadius'     => 0,
				],
			],
		];
	}

	/**
	 * Get the sparkline chart configuration.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getSparklineConfig(): array
	{
		return [
			'type'    => 'line',
			'data'    => $this->getChartData(),
			'options' => [
				'responsive'          => true,
				'maintainAspectRatio' => false,
				'plugins'             => [
					'legend' => [
						'display' => false,
					],
					'tooltip' => [
						'enabled' => true,
					],
				],
				'scales' => [
					'x' => [
						'display' => false,
					],
					'y' => [
						'display'     => false,
						'beginAtZero' => true,
					],
				],
				'elements' => [
					'line' => [
						'borderWidth' => 2,
					],
				],
			],
		];
	}

	/**
	 * Get the page views count.
	 *
	 * @since 1.0.0
	 */
	public function getPageViews(): int
	{
		return $this->analytics['pageviews'] ?? 0;
	}

	/**
	 * Get the visitors count.
	 *
	 * @since 1.0.0
	 */
	public function getVisitors(): int
	{
		return $this->analytics['visitors'] ?? 0;
	}

	/**
	 * Get the bounce rate.
	 *
	 * @since 1.0.0
	 */
	public function getBounceRate(): float
	{
		return $this->analytics['bounce_rate'] ?? 0.0;
	}

	/**
	 * Get formatted page views.
	 *
	 * @since 1.0.0
	 */
	public function getFormattedPageViews(): string
	{
		return $this->formatNumber( $this->getPageViews() );
	}

	/**
	 * Get formatted visitors.
	 *
	 * @since 1.0.0
	 */
	public function getFormattedVisitors(): string
	{
		return $this->formatNumber( $this->getVisitors() );
	}

	/**
	 * Get formatted bounce rate.
	 *
	 * @since 1.0.0
	 */
	public function getFormattedBounceRate(): string
	{
		return $this->formatPercentage( $this->getBounceRate() );
	}

	/**
	 * Get the view for the component.
	 *
	 * @since 1.0.0
	 */
	public function render(): \Illuminate\Contracts\View\View
	{
		return view( 'artisanpack-analytics::livewire.page-analytics' );
	}
}
