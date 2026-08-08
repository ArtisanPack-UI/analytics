<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Anonymous Traffic Widget.
 *
 * Surfaces the page views collected before consent by anonymous mode: the
 * anonymous share of total page views, the pages and referring hosts behind
 * it, the device-class split and a trend.
 *
 * Everything here is a count of page views. Anonymous rows carry no visitor,
 * session or fingerprint, so there is deliberately no visitor, session,
 * bounce or duration figure on this widget — those numbers do not exist for
 * this traffic and showing a zero would imply they did.
 *
 * @since   1.5.0
 *
 * @package ArtisanPackUI\Analytics\Http\Livewire\Widgets
 */
class AnonymousTraffic extends Component
{
	use WithAnalyticsWidget;

	/**
	 * Whether anonymous mode is enabled.
	 */
	public bool $enabled = false;

	/**
	 * Anonymous page views for the current range.
	 */
	public int $anonymousPageviews = 0;

	/**
	 * Identified page views for the current range.
	 */
	public int $identifiedPageviews = 0;

	/**
	 * Total page views (identified and anonymous) for the current range.
	 */
	public int $totalPageviews = 0;

	/**
	 * Anonymous share of total page views as a percentage.
	 */
	public float $anonymousPercentage = 0.0;

	/**
	 * The top pages by anonymous page views.
	 *
	 * @var Collection<int, array{path: string, title: string, views: int}>
	 */
	public Collection $topPages;

	/**
	 * The top referring hosts by anonymous page views.
	 *
	 * @var Collection<int, array{host: string, views: int}>
	 */
	public Collection $referringHosts;

	/**
	 * The anonymous device-class split.
	 *
	 * @var Collection<int, array{device_type: string, views: int, percentage: float}>
	 */
	public Collection $deviceBreakdown;

	/**
	 * The anonymous page-view trend.
	 *
	 * @var array<int, array{date: string, pageviews: int}>
	 */
	public array $trend = [];

	/**
	 * Maximum number of pages and hosts to display.
	 */
	public int $limit = 10;

	/**
	 * Mount the component.
	 *
	 * @param string|null $dateRangePreset The initial date range.
	 * @param int|null    $siteId          Site ID filter.
	 * @param int         $limit           Maximum pages and hosts to show.
	 *
	 * @since 1.5.0
	 */
	public function mount(
		?string $dateRangePreset = null,
		?int $siteId = null,
		int $limit = 10,
	): void {
		$this->topPages        = collect();
		$this->referringHosts  = collect();
		$this->deviceBreakdown = collect();

		$this->initializeWidget( $dateRangePreset, $siteId );
		$this->limit = max( 1, min( $limit, 100 ) );
		$this->loadAnonymousStats();
	}

	/**
	 * Load the anonymous traffic statistics.
	 *
	 * @since 1.5.0
	 */
	public function loadAnonymousStats(): void
	{
		$this->isLoading = true;

		$stats = $this->getAnalyticsQuery()->getAnonymousStats(
			$this->getDateRange(),
			$this->limit,
			'day',
			$this->getFilters(),
		);

		$this->enabled             = $stats['enabled'];
		$this->anonymousPageviews  = $stats['anonymous_pageviews'];
		$this->identifiedPageviews = $stats['identified_pageviews'];
		$this->totalPageviews      = $stats['total_pageviews'];
		$this->anonymousPercentage = $stats['anonymous_percentage'];
		$this->topPages            = collect( $stats['top_pages'] );
		$this->referringHosts      = collect( $stats['referring_hosts'] );
		$this->deviceBreakdown     = collect( $stats['device_breakdown'] );
		$this->trend               = $stats['trend'];

		$this->isLoading = false;
	}

	/**
	 * Refresh the widget data.
	 *
	 * @since 1.5.0
	 */
	#[On( 'refresh-analytics-widgets' )]
	public function refreshData(): void
	{
		$this->loadAnonymousStats();
	}

	/**
	 * Get the maximum page-view count across the trend points.
	 *
	 * Used to scale the sparkline bars. Returns at least 1 to avoid division
	 * by zero.
	 *
	 * @return int The largest page-view count, or 1 when the trend is empty.
	 *
	 * @since 1.5.0
	 */
	public function getTrendMax(): int
	{
		$max = 0;

		foreach ( $this->trend as $point ) {
			if ( $point['pageviews'] > $max ) {
				$max = $point['pageviews'];
			}
		}

		return max( 1, $max );
	}

	/**
	 * Get the view for the component.
	 *
	 * @return \Illuminate\Contracts\View\View The component view.
	 *
	 * @since 1.5.0
	 */
	public function render(): \Illuminate\Contracts\View\View
	{
		return view( 'artisanpack-analytics::livewire.widgets.anonymous-traffic' );
	}
}
