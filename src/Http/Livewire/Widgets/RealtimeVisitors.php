<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Livewire\Attributes\On;
use Livewire\Attributes\Polling;
use Livewire\Component;

/**
 * Realtime Visitors Widget.
 *
 * Displays the current number of active visitors with live updates.
 *
 * @since 1.0.0
 */
class RealtimeVisitors extends Component
{
	use WithAnalyticsWidget;

	/**
	 * The current visitor count.
	 */
	public int $visitorCount = 0;

	/**
	 * The previous visitor count for animation.
	 */
	public int $previousCount = 0;

	/**
	 * Minutes to consider as "active".
	 */
	public int $activeMinutes = 5;

	/**
	 * Polling interval in milliseconds.
	 */
	public int $pollingInterval = 10000;

	/**
	 * Whether polling is enabled.
	 */
	public bool $pollingEnabled = true;

	/**
	 * Last update timestamp.
	 */
	public string $lastUpdated = '';

	/**
	 * Mount the component.
	 *
	 * @param int|null $siteId          Site ID filter.
	 * @param int      $activeMinutes   Minutes to consider active.
	 * @param int      $pollingInterval Polling interval in ms.
	 * @param bool     $pollingEnabled  Whether to enable polling.
	 *
	 * @since 1.0.0
	 */
	public function mount(
		?int $siteId = null,
		int $activeMinutes = 5,
		int $pollingInterval = 10000,
		bool $pollingEnabled = true,
	): void {
		$this->siteId          = $siteId;
		$this->activeMinutes   = $activeMinutes;
		$this->pollingInterval = $pollingInterval;
		$this->pollingEnabled  = $pollingEnabled;
		$this->isLoading       = false;

		$this->loadRealtimeData();
	}

	/**
	 * Load the realtime visitor count.
	 *
	 * @since 1.0.0
	 */
	public function loadRealtimeData(): void
	{
		$this->previousCount = $this->visitorCount;

		$realtimeData = $this->getAnalyticsQuery()->getRealtime( $this->activeMinutes );

		$this->visitorCount = $realtimeData['active_visitors'] ?? 0;
		$this->lastUpdated  = $realtimeData['timestamp'] ?? now()->toIso8601String();
	}

	/**
	 * Poll for updates.
	 *
	 * This method is called automatically by Livewire polling.
	 *
	 * @since 1.0.0
	 */
	#[Polling( interval: '10s' )]
	public function poll(): void
	{
		if ( $this->pollingEnabled ) {
			$this->loadRealtimeData();
		}
	}

	/**
	 * Toggle polling.
	 *
	 * @since 1.0.0
	 */
	public function togglePolling(): void
	{
		$this->pollingEnabled = ! $this->pollingEnabled;
	}

	/**
	 * Get the trend indicator.
	 *
	 * @since 1.0.0
	 */
	public function getTrend(): string
	{
		if ( $this->visitorCount > $this->previousCount ) {
			return 'up';
		}

		if ( $this->visitorCount < $this->previousCount ) {
			return 'down';
		}

		return 'stable';
	}

	/**
	 * Get the trend difference.
	 *
	 * @since 1.0.0
	 */
	public function getTrendDifference(): int
	{
		return abs( $this->visitorCount - $this->previousCount );
	}

	/**
	 * Get the status indicator class.
	 *
	 * @since 1.0.0
	 */
	public function getStatusClass(): string
	{
		if ( $this->visitorCount > 0 ) {
			return 'text-success';
		}

		return 'text-gray-400';
	}

	/**
	 * Get the pulse animation class.
	 *
	 * @since 1.0.0
	 */
	public function getPulseClass(): string
	{
		return $this->visitorCount > 0 ? 'animate-pulse' : '';
	}

	/**
	 * Refresh the widget data.
	 *
	 * @since 1.0.0
	 */
	#[On( 'refresh-analytics-widgets' )]
	public function refreshData(): void
	{
		$this->loadRealtimeData();
	}

	/**
	 * Get the view for the component.
	 *
	 * @since 1.0.0
	 */
	public function render(): \Illuminate\Contracts\View\View
	{
		return view( 'artisanpack-analytics::livewire.widgets.realtime-visitors' );
	}
}
