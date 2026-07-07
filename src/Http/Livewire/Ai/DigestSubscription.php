<?php

/**
 * DigestSubscription Livewire component.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Livewire\Ai;

use ArtisanPackUI\Ai\Contracts\FeatureRegistry;
use ArtisanPackUI\Analytics\Models\AnalyticsDigestPreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Per-user opt-in UI for the AI analytics digest email.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class DigestSubscription extends Component
{
	public string $cadence = AnalyticsDigestPreference::CADENCE_OFF;

	public ?string $status = null;

	/**
	 * Mount the component with the current user's preference.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$user = Auth::user();

		if ( null === $user ) {
			return;
		}

		$preference = AnalyticsDigestPreference::query()->where( 'user_id', $user->getAuthIdentifier() )->first();

		if ( null !== $preference ) {
			$this->cadence = $preference->cadence;
		}
	}

	/**
	 * Persist the selected cadence.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function save(): void
	{
		$this->status = null;

		$user = Auth::user();

		if ( null === $user ) {
			$this->status = __( 'You must be signed in to change your digest preference.' );
			return;
		}

		$allowed = [
			AnalyticsDigestPreference::CADENCE_OFF,
			AnalyticsDigestPreference::CADENCE_WEEKLY,
			AnalyticsDigestPreference::CADENCE_MONTHLY,
		];

		if ( ! in_array( $this->cadence, $allowed, true ) ) {
			$this->cadence = AnalyticsDigestPreference::CADENCE_OFF;
		}

		AnalyticsDigestPreference::query()->updateOrCreate(
			[ 'user_id' => $user->getAuthIdentifier() ],
			[ 'cadence' => $this->cadence ],
		);

		$this->status = AnalyticsDigestPreference::CADENCE_OFF === $this->cadence
			? __( 'Digest emails turned off.' )
			: __( 'Digest preference saved.' );
	}

	/**
	 * Determine whether this feature is enabled.
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	public function getIsEnabledProperty(): bool
	{
		$registry = app( FeatureRegistry::class );
		$key      = 'analytics.digest_email';

		if ( null === $registry->get( $key ) ) {
			return false;
		}

		return $registry->isToggleOn( $key );
	}

	/**
	 * Render the view.
	 *
	 * @since 1.3.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'artisanpack-analytics::livewire.ai.digest-subscription' );
	}
}
