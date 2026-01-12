<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Site selector Livewire component.
 *
 * Provides a dropdown for selecting between multiple sites
 * in multi-tenant mode.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Livewire
 */
class SiteSelector extends Component
{
	/**
	 * The currently selected site ID.
	 *
	 * @var int|null
	 */
	public ?int $selectedSiteId = null;

	/**
	 * Whether the dropdown is open.
	 *
	 * @var bool
	 */
	public bool $isOpen = false;

	/**
	 * Mount the component.
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$tenantManager = app( TenantManager::class );

		if ( $tenantManager->hasCurrent() ) {
			$this->selectedSiteId = $tenantManager->currentId();
		} else {
			// Select the first accessible site
			$sites = $this->getAccessibleSites();

			if ( $sites->isNotEmpty() ) {
				$this->selectedSiteId = $sites->first()->id;
				$this->updateTenantContext();
			}
		}
	}

	/**
	 * Select a site.
	 *
	 * @param int $siteId The site ID to select.
	 *
	 * @return void
	 */
	public function selectSite( int $siteId ): void
	{
		if ( ! $this->canAccessSite( $siteId ) ) {
			return;
		}

		$this->selectedSiteId = $siteId;
		$this->isOpen         = false;

		$this->updateTenantContext();

		$this->dispatch( 'site-changed', siteId: $siteId );
	}

	/**
	 * Toggle the dropdown.
	 *
	 * @return void
	 */
	public function toggleDropdown(): void
	{
		$this->isOpen = ! $this->isOpen;
	}

	/**
	 * Close the dropdown.
	 *
	 * @return void
	 */
	public function closeDropdown(): void
	{
		$this->isOpen = false;
	}

	/**
	 * Get accessible sites for the current user.
	 *
	 * @return Collection<int, Site>
	 */
	#[Computed]
	public function getAccessibleSites(): Collection
	{
		$query = Site::query()->where( 'is_active', true );

		// Apply tenant filtering if user has a tenant association
		$user = Auth::user();

		if ( null !== $user && method_exists( $user, 'getTenantModel' ) ) {
			$tenant = $user->getTenantModel();

			if ( null !== $tenant ) {
				$query->forTenant( $tenant );
			}
		}

		// Allow customization via event/filter
		$query = $this->applyAccessFilter( $query );

		return $query->orderBy( 'name' )->get();
	}

	/**
	 * Get the currently selected site.
	 *
	 * @return Site|null
	 */
	#[Computed]
	public function selectedSite(): ?Site
	{
		if ( null === $this->selectedSiteId ) {
			return null;
		}

		return Site::find( $this->selectedSiteId );
	}

	/**
	 * Check if the user can access a site.
	 *
	 * @param int $siteId The site ID to check.
	 *
	 * @return bool
	 */
	public function canAccessSite( int $siteId ): bool
	{
		return $this->getAccessibleSites()->contains( 'id', $siteId );
	}

	/**
	 * Render the component.
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'artisanpack-analytics::livewire.site-selector', [
			'sites'        => $this->getAccessibleSites(),
			'selectedSite' => $this->selectedSite(),
		] );
	}

	/**
	 * Apply access filter to the query.
	 *
	 * Override this method or use hooks to customize site access.
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query The query builder.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	protected function applyAccessFilter( $query )
	{
		// Use the hooks package if available
		if ( function_exists( 'applyFilters' ) ) {
			return applyFilters( 'ap.analytics.site_selector.query', $query, Auth::user() );
		}

		return $query;
	}

	/**
	 * Update the tenant manager context.
	 *
	 * @return void
	 */
	protected function updateTenantContext(): void
	{
		$tenantManager = app( TenantManager::class );
		$site          = Site::find( $this->selectedSiteId );

		$tenantManager->setCurrent( $site );
	}
}
