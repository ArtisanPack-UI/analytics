<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bot Traffic Widget.
 *
 * Surfaces the bot traffic that is filtered out of the main dashboard by
 * default: total bot visits, the bot share of total traffic, the busiest
 * bot user agents, and a bot-only visit trend.
 *
 * @since   1.2.0
 *
 * @package ArtisanPackUI\Analytics\Http\Livewire\Widgets
 */
class BotTraffic extends Component
{
	use WithAnalyticsWidget;

	/**
	 * Total bot visits for the current range.
	 */
	public int $botVisits = 0;

	/**
	 * Total visits (human and bot) for the current range.
	 */
	public int $totalVisits = 0;

	/**
	 * Bot share of total traffic as a percentage.
	 */
	public float $botPercentage = 0.0;

	/**
	 * The top bot user agents.
	 *
	 * @var Collection<int, array{user_agent: string, visits: int}>
	 */
	public Collection $topAgents;

	/**
	 * The bot-only visit trend.
	 *
	 * @var array<int, array{date: string, visits: int}>
	 */
	public array $trend = [];

	/**
	 * Maximum number of bot user agents to display.
	 */
	public int $limit = 10;

	/**
	 * Mount the component.
	 *
	 * @param string|null $dateRangePreset The initial date range.
	 * @param int|null    $siteId          Site ID filter.
	 * @param int         $limit           Maximum bot user agents to show.
	 *
	 * @since 1.2.0
	 */
	public function mount(
		?string $dateRangePreset = null,
		?int $siteId = null,
		int $limit = 10,
	): void {
		$this->topAgents = collect();
		$this->initializeWidget( $dateRangePreset, $siteId );
		$this->limit = max( 1, min( $limit, 100 ) );
		$this->loadBotStats();
	}

	/**
	 * Load the bot traffic statistics.
	 *
	 * @since 1.2.0
	 */
	public function loadBotStats(): void
	{
		$this->isLoading = true;

		$stats = $this->getAnalyticsQuery()->getBotStats(
			$this->getDateRange(),
			$this->limit,
			'day',
			$this->getFilters(),
		);

		$this->botVisits     = $stats['bot_visits'];
		$this->totalVisits   = $stats['total_visits'];
		$this->botPercentage = $stats['bot_percentage'];
		$this->topAgents     = collect( $stats['top_agents'] );
		$this->trend         = $stats['trend'];

		$this->isLoading = false;
	}

	/**
	 * Refresh the widget data.
	 *
	 * @since 1.2.0
	 */
	#[On( 'refresh-analytics-widgets' )]
	public function refreshData(): void
	{
		$this->loadBotStats();
	}

	/**
	 * Get the maximum visit count across the trend points.
	 *
	 * Used to scale the sparkline bars. Returns at least 1 to avoid
	 * division by zero.
	 *
	 * @return int The largest visit count, or 1 when the trend is empty.
	 *
	 * @since 1.2.0
	 */
	public function getTrendMax(): int
	{
		$max = 0;

		foreach ( $this->trend as $point ) {
			if ( $point['visits'] > $max ) {
				$max = $point['visits'];
			}
		}

		return max( 1, $max );
	}

	/**
	 * Get the view for the component.
	 *
	 * @return \Illuminate\Contracts\View\View The component view.
	 *
	 * @since 1.2.0
	 */
	public function render(): \Illuminate\Contracts\View\View
	{
		return view( 'artisanpack-analytics::livewire.widgets.bot-traffic' );
	}
}
