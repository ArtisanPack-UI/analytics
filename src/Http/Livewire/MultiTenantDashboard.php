<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Multi-Tenant Dashboard Component.
 *
 * Wraps the analytics dashboard with site selection functionality
 * for multi-tenant environments.
 *
 * @since 1.0.0
 */
class MultiTenantDashboard extends Component
{
	/**
	 * The currently selected site ID.
	 *
	 * @var int|null
	 */
	public ?int $siteId = null;

	/**
	 * The currently selected date range preset.
	 *
	 * @var string
	 */
	public string $dateRangePreset = '30d';

	/**
	 * Whether multi-tenant mode is enabled.
	 *
	 * @var bool
	 */
	public bool $multiTenantEnabled = false;

	/**
	 * Mount the component.
	 *
	 * @param string|null $dateRangePreset Initial date range preset.
	 * @param int|null    $siteId          Initial site ID.
	 *
	 * @return void
	 */
	public function mount( ?string $dateRangePreset = null, ?int $siteId = null ): void
	{
		$this->multiTenantEnabled = config( 'artisanpack.analytics.multi_tenant.enabled', false );
		$this->dateRangePreset    = $dateRangePreset ?? '30d';

		$tenantManager = app( TenantManager::class );

		// Use provided siteId, or get from TenantManager, or fall back to first available
		if ( null !== $siteId ) {
			$this->siteId = $siteId;
			$site         = Site::find( $siteId );
			$tenantManager->setCurrent( $site );
		} elseif ( $tenantManager->hasCurrent() ) {
			$this->siteId = $tenantManager->currentId();
		} else {
			// Get first available site
			$site = Site::where( 'is_active', true )->first();

			if ( null !== $site ) {
				$this->siteId = $site->id;
				$tenantManager->setCurrent( $site );
			}
		}
	}

	/**
	 * Handle site change event from SiteSelector.
	 *
	 * @param int $siteId The new site ID.
	 *
	 * @return void
	 */
	#[On( 'site-changed' )]
	public function handleSiteChange( int $siteId ): void
	{
		$this->siteId = $siteId;

		// Update TenantManager context
		$tenantManager = app( TenantManager::class );
		$site          = Site::find( $siteId );
		$tenantManager->setCurrent( $site );

		// Dispatch event to refresh child components
		$this->dispatch( 'refreshAnalytics' );
	}

	/**
	 * Update date range preset.
	 *
	 * @param string $preset The new date range preset.
	 *
	 * @return void
	 */
	public function updateDateRange( string $preset ): void
	{
		$this->dateRangePreset = $preset;
		$this->dispatch( 'dateRangeChanged', preset: $preset );
	}

	/**
	 * Get the current site.
	 *
	 * @return Site|null
	 */
	public function getCurrentSite(): ?Site
	{
		if ( null === $this->siteId ) {
			return null;
		}

		return Site::find( $this->siteId );
	}

	/**
	 * Check if the dashboard should show the site selector.
	 *
	 * @return bool
	 */
	public function shouldShowSiteSelector(): bool
	{
		if ( ! $this->multiTenantEnabled ) {
			return false;
		}

		// Show if there are multiple sites accessible
		$siteCount = Site::where( 'is_active', true )->count();

		return $siteCount > 1;
	}

	/**
	 * Render the component.
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'artisanpack-analytics::livewire.multi-tenant-dashboard', [
			'currentSite'        => $this->getCurrentSite(),
			'showSiteSelector'   => $this->shouldShowSiteSelector(),
			'multiTenantEnabled' => $this->multiTenantEnabled,
		] );
	}
}
