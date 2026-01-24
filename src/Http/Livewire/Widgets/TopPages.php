<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Widgets;

use ArtisanPackUI\Analytics\Http\Livewire\Concerns\WithAnalyticsWidget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Top Pages Widget.
 *
 * Displays a sortable list of top pages by views.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Livewire\Widgets
 */
class TopPages extends Component
{
	use WithAnalyticsWidget;

	/**
	 * The pages data.
	 *
	 * @var Collection<int, array{path: string, title: string, views: int, unique_views: int}>
	 */
	public Collection $pages;

	/**
	 * Maximum number of pages to display.
	 */
	public int $limit = 10;

	/**
	 * Current sort column.
	 */
	public string $sortBy = 'views';

	/**
	 * Sort direction.
	 */
	public string $sortDirection = 'desc';

	/**
	 * Mount the component.
	 *
	 * @param string|null $dateRangePreset The initial date range.
	 * @param int|null    $siteId          Site ID filter.
	 * @param int         $limit           Maximum pages to show.
	 *
	 * @since 1.0.0
	 */
	public function mount(
		?string $dateRangePreset = null,
		?int $siteId = null,
		int $limit = 10,
	): void {
		$this->pages = collect();
		$this->initializeWidget( $dateRangePreset, $siteId );
		$this->limit = $limit;
		$this->loadPages();
	}

	/**
	 * Load the pages data.
	 *
	 * @since 1.0.0
	 */
	public function loadPages(): void
	{
		$this->isLoading = true;

		$this->pages = $this->getAnalyticsQuery()->getTopPages(
			$this->getDateRange(),
			$this->limit,
			$this->getFilters(),
		);

		$this->sortPages();
		$this->isLoading = false;
	}

	/**
	 * Sort by a column.
	 *
	 * @param string $column The column to sort by.
	 *
	 * @since 1.0.0
	 */
	public function sortByColumn( string $column ): void
	{
		if ( $this->sortBy === $column ) {
			$this->sortDirection = 'asc' === $this->sortDirection ? 'desc' : 'asc';
		} else {
			$this->sortBy        = $column;
			$this->sortDirection = 'desc';
		}

		$this->sortPages();
	}

	/**
	 * Refresh the widget data.
	 *
	 * @since 1.0.0
	 */
	#[On( 'refresh-analytics-widgets' )]
	public function refreshData(): void
	{
		$this->loadPages();
	}

	/**
	 * Get the columns configuration.
	 *
	 * @return array<string, array{label: string, sortable: bool}>
	 *
	 * @since 1.0.0
	 */
	public function getColumns(): array
	{
		return [
			'path' => [
				'label'    => __( 'Page' ),
				'sortable' => true,
			],
			'views' => [
				'label'    => __( 'Views' ),
				'sortable' => true,
			],
			'unique_views' => [
				'label'    => __( 'Unique Views' ),
				'sortable' => true,
			],
		];
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
		return view( 'artisanpack-analytics::livewire.widgets.top-pages' );
	}

	/**
	 * Sort pages by current sort settings.
	 *
	 * @since 1.0.0
	 */
	protected function sortPages(): void
	{
		$sortFlags = 'path' === $this->sortBy ? SORT_STRING : SORT_NUMERIC;

		$this->pages = $this->pages->sortBy(
			fn ( array $page ) => $page[ $this->sortBy ] ?? ( 'path' === $this->sortBy ? '' : 0 ),
			$sortFlags,
			'desc' === $this->sortDirection,
		)->values();
	}
}
