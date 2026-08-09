<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Stats Cards Widget.
 *
 * Displays key analytics metrics in a card grid format
 * with comparison to previous period.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Livewire\Widgets
 */
class StatsCards extends Component
{
	use WithAnalyticsWidget;

	/**
	 * The statistics data.
	 *
	 * @var array<string, mixed>
	 */
	public array $stats = [];

	/**
	 * Whether to show comparison data.
	 */
	public bool $showComparison = true;

	/**
	 * Which stats to display.
	 *
	 * @var array<string>
	 */
	public array $visibleStats = [
		'pageviews',
		'visitors',
		'sessions',
		'bounce_rate',
	];

	/**
	 * Mount the component.
	 *
	 * @param string|null  $dateRangePreset The initial date range.
	 * @param int|null     $siteId          Site ID filter.
	 * @param bool         $showComparison  Whether to show comparisons.
	 * @param array<string>|null $visibleStats   Which stats to show.
	 *
	 * @since 1.0.0
	 */
	public function mount(
		?string $dateRangePreset = null,
		?int $siteId = null,
		bool $showComparison = true,
		?array $visibleStats = null,
	): void {
		$this->initializeWidget( $dateRangePreset, $siteId );
		$this->showComparison = $showComparison;

		if ( null !== $visibleStats ) {
			$this->visibleStats = $visibleStats;
		}

		$this->loadStats();
	}

	/**
	 * Load the statistics data.
	 *
	 * @since 1.0.0
	 */
	public function loadStats(): void
	{
		$this->isLoading = true;

		$this->stats = $this->getAnalyticsQuery()->getStats(
			$this->getDateRange(),
			$this->showComparison,
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
		$this->loadStats();
	}

	/**
	 * Get the stat cards configuration.
	 *
	 * `anonymous_aware` marks the metrics that can include anonymous traffic.
	 * Everything else is derived from a visitor or a session, which anonymous
	 * rows do not have, so those cards are labelled rather than left to look
	 * as though the toggle applied to them too.
	 *
	 * @return array<string, array{label: string, key: string, format: string, inverse: bool, icon: string, anonymous_aware: bool}>
	 *
	 * @since 1.0.0
	 */
	public function getStatCardsConfig(): array
	{
		return [
			'pageviews' => [
				'label'           => __( 'Page Views' ),
				'key'             => 'pageviews',
				'format'          => 'number',
				'inverse'         => false,
				'icon'            => 'eye',
				'anonymous_aware' => true,
			],
			'visitors' => [
				'label'           => __( 'Visitors' ),
				'key'             => 'visitors',
				'format'          => 'number',
				'inverse'         => false,
				'icon'            => 'users',
				'anonymous_aware' => false,
			],
			'sessions' => [
				'label'           => __( 'Sessions' ),
				'key'             => 'sessions',
				'format'          => 'number',
				'inverse'         => false,
				'icon'            => 'chart-bar',
				'anonymous_aware' => false,
			],
			'bounce_rate' => [
				'label'           => __( 'Bounce Rate' ),
				'key'             => 'bounce_rate',
				'format'          => 'percentage',
				'inverse'         => true,
				'icon'            => 'arrow-left-on-rectangle',
				'anonymous_aware' => false,
			],
			'avg_session_duration' => [
				'label'           => __( 'Avg. Session Duration' ),
				'key'             => 'avg_session_duration',
				'format'          => 'duration',
				'inverse'         => false,
				'icon'            => 'clock',
				'anonymous_aware' => false,
			],
			'pages_per_session' => [
				'label'           => __( 'Pages / Session' ),
				'key'             => 'pages_per_session',
				'format'          => 'decimal',
				'inverse'         => false,
				'icon'            => 'document-duplicate',
				'anonymous_aware' => false,
			],
			'realtime_visitors' => [
				'label'           => __( 'Active Now' ),
				'key'             => 'realtime_visitors',
				'format'          => 'number',
				'inverse'         => false,
				'icon'            => 'signal',
				'anonymous_aware' => false,
			],
		];
	}

	/**
	 * Get the scope note for a stat card.
	 *
	 * Returns an empty string when anonymous traffic is not in play, so the
	 * cards stay uncluttered on the ordinary consented-only dashboard.
	 *
	 * @param array<string, mixed> $config The stat card configuration.
	 *
	 * @return string The note to display beneath the value, or an empty string.
	 *
	 * @since 1.5.0
	 */
	public function getStatScopeNote( array $config ): string
	{
		if ( 'include' !== ( $this->stats['anonymous_mode'] ?? 'exclude' ) ) {
			return '';
		}

		return true === ( $config['anonymous_aware'] ?? false )
			? __( 'Includes anonymous traffic' )
			: __( 'Consented visitors only' );
	}

	/**
	 * Format a stat value for display.
	 *
	 * @param mixed  $value  The value to format.
	 * @param string $format The format type.
	 *
	 * @return string The formatted value.
	 *
	 * @since 1.0.0
	 */
	public function formatStatValue( mixed $value, string $format ): string
	{
		return match ( $format ) {
			'number'     => $this->formatNumber( (int) $value ),
			'percentage' => $this->formatPercentage( (float) $value ),
			'duration'   => $this->formatDuration( (int) $value ),
			'decimal'    => number_format( (float) $value, 1 ),
			default      => (string) $value,
		};
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
		return view( 'artisanpack-analytics::livewire.widgets.stats-cards' );
	}
}
