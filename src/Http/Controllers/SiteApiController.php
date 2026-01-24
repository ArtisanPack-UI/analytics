<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Controllers;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\SiteSettingsService;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * API Controller for site management.
 *
 * Provides endpoints for managing site settings and information
 * when authenticated via API key.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Controllers
 */
class SiteApiController extends Controller
{

	/**
	 * Allowed setting keys that can be updated via API.
	 *
	 * @var array<int, string>
	 */
	protected const ALLOWED_SETTINGS = [
		'tracking.enabled',
		'tracking.respect_dnt',
		'tracking.anonymize_ip',
		'tracking.track_hash_changes',
		'dashboard.default_date_range',
		'dashboard.realtime_enabled',
		'dashboard.show_conversions',
		'dashboard.show_goals',
		'privacy.consent_required',
		'privacy.consent_cookie_lifetime',
		'privacy.excluded_paths',
		'privacy.excluded_ips',
		'features.events',
		'features.goals',
		'features.conversions',
		'features.funnels',
	];

	/**
	 * Maximum number of days for stats queries.
	 *
	 * @var int
	 */
	protected const MAX_STATS_DAYS = 365;

	/**
	 * The tenant manager.
	 *
	 * @var TenantManager
	 */
	protected TenantManager $tenantManager;

	/**
	 * The site settings service.
	 *
	 * @var SiteSettingsService
	 */
	protected SiteSettingsService $settings;

	/**
	 * Create a new controller instance.
	 *
	 * @param TenantManager       $tenantManager The tenant manager.
	 * @param SiteSettingsService $settings      The site settings service.
	 */
	public function __construct( TenantManager $tenantManager, SiteSettingsService $settings )
	{
		$this->tenantManager = $tenantManager;
		$this->settings      = $settings;
	}

	/**
	 * Get the current site information.
	 *
	 * @param Request $request The request.
	 *
	 * @return JsonResponse
	 */
	public function show( Request $request ): JsonResponse
	{
		$site = $this->getCurrentSite();

		if ( null === $site ) {
			return response()->json( [
				'error'   => __( 'Site not found' ),
				'message' => __( 'No site is associated with this API key.' ),
			], 404 );
		}

		return response()->json( [
			'data' => [
				'id'               => $site->id,
				'uuid'             => $site->uuid,
				'name'             => $site->name,
				'domain'           => $site->domain,
				'timezone'         => $site->timezone,
				'currency'         => $site->currency,
				'is_active'        => $site->is_active,
				'tracking_enabled' => $site->tracking_enabled,
				'public_dashboard' => $site->public_dashboard,
				'settings'         => $this->settings->all( $site ),
				'created_at'       => $site->created_at?->toIso8601String(),
				'updated_at'       => $site->updated_at?->toIso8601String(),
			],
		] );
	}

	/**
	 * Update the current site settings.
	 *
	 * Only settings in the ALLOWED_SETTINGS allowlist can be updated via API.
	 *
	 * @param Request $request The request.
	 *
	 * @return JsonResponse
	 */
	public function updateSettings( Request $request ): JsonResponse
	{
		$site = $this->getCurrentSite();

		if ( null === $site ) {
			return response()->json( [
				'error'   => __( 'Site not found' ),
				'message' => __( 'No site is associated with this API key.' ),
			], 404 );
		}

		$validated = $request->validate( [
			'settings'                => 'sometimes|array',
			'settings.tracking'       => 'sometimes|array',
			'settings.dashboard'      => 'sometimes|array',
			'settings.privacy'        => 'sometimes|array',
			'settings.features'       => 'sometimes|array',
			'timezone'                => 'sometimes|string|timezone',
			'currency'                => 'sometimes|string|size:3',
			'tracking_enabled'        => 'sometimes|boolean',
			'public_dashboard'        => 'sometimes|boolean',
		] );

		// Update site model fields
		$siteFields = [];

		if ( isset( $validated['timezone'] ) ) {
			$siteFields['timezone'] = $validated['timezone'];
		}

		if ( isset( $validated['currency'] ) ) {
			$siteFields['currency'] = $validated['currency'];
		}

		if ( isset( $validated['tracking_enabled'] ) ) {
			$siteFields['tracking_enabled'] = $validated['tracking_enabled'];
		}

		if ( isset( $validated['public_dashboard'] ) ) {
			$siteFields['public_dashboard'] = $validated['public_dashboard'];
		}

		if ( ! empty( $siteFields ) ) {
			$site->update( $siteFields );
		}

		// Update settings (only allowed keys)
		if ( isset( $validated['settings'] ) ) {
			$this->updateAllowedSettings( $validated['settings'], $site );
		}

		// Refresh site to get updated values
		$site->refresh();

		return response()->json( [
			'message' => __( 'Settings updated successfully.' ),
			'data'    => [
				'id'               => $site->id,
				'timezone'         => $site->timezone,
				'currency'         => $site->currency,
				'tracking_enabled' => $site->tracking_enabled,
				'public_dashboard' => $site->public_dashboard,
				'settings'         => $this->settings->all( $site ),
			],
		] );
	}

	/**
	 * Get site statistics summary.
	 *
	 * @param Request $request The request.
	 *
	 * @return JsonResponse
	 */
	public function stats( Request $request ): JsonResponse
	{
		$site = $this->getCurrentSite();

		if ( null === $site ) {
			return response()->json( [
				'error'   => __( 'Site not found' ),
				'message' => __( 'No site is associated with this API key.' ),
			], 404 );
		}

		// Validate and clamp days parameter
		$days = min(
			max( 1, (int) $request->input( 'days', 7 ) ),
			self::MAX_STATS_DAYS,
		);

		$startDate = now()->subDays( $days )->startOfDay();
		$endDate   = now()->endOfDay();

		return response()->json( [
			'data' => [
				'site_id'     => $site->id,
				'site_name'   => $site->name,
				'period'      => [
					'start' => $startDate->toIso8601String(),
					'end'   => $endDate->toIso8601String(),
					'days'  => $days,
				],
				'visitors'    => $site->visitors()
					->whereBetween( 'created_at', [ $startDate, $endDate ] )
					->count(),
				'sessions'    => $site->sessions()
					->whereBetween( 'created_at', [ $startDate, $endDate ] )
					->count(),
				'page_views'  => $site->pageViews()
					->whereBetween( 'created_at', [ $startDate, $endDate ] )
					->count(),
				'events'      => $site->events()
					->whereBetween( 'created_at', [ $startDate, $endDate ] )
					->count(),
				'conversions' => $site->conversions()
					->whereBetween( 'created_at', [ $startDate, $endDate ] )
					->count(),
			],
		] );
	}

	/**
	 * Get site goals.
	 *
	 * @param Request $request The request.
	 *
	 * @return JsonResponse
	 */
	public function goals( Request $request ): JsonResponse
	{
		$site = $this->getCurrentSite();

		if ( null === $site ) {
			return response()->json( [
				'error'   => __( 'Site not found' ),
				'message' => __( 'No site is associated with this API key.' ),
			], 404 );
		}

		$goals = $site->goals()
			->when( $request->boolean( 'active_only', true ), function ( $query ): void {
				$query->where( 'is_active', true );
			} )
			->get( [ 'id', 'name', 'description', 'type', 'is_active', 'created_at' ] );

		return response()->json( [
			'data' => $goals,
		] );
	}

	/**
	 * Rotate the API key.
	 *
	 * @param Request $request The request.
	 *
	 * @return JsonResponse
	 */
	public function rotateApiKey( Request $request ): JsonResponse
	{
		$site = $this->getCurrentSite();

		if ( null === $site ) {
			return response()->json( [
				'error'   => __( 'Site not found' ),
				'message' => __( 'No site is associated with this API key.' ),
			], 404 );
		}

		$newApiKey = $site->rotateApiKey();

		return response()->json( [
			'message' => __( 'API key rotated successfully. Please update your integration with the new key.' ),
			'data'    => [
				'api_key' => $newApiKey,
			],
		] );
	}

	/**
	 * Update only allowed settings keys.
	 *
	 * @param array<string, mixed> $settings The settings to update.
	 * @param Site                 $site     The site to update settings for.
	 *
	 * @return void
	 */
	protected function updateAllowedSettings( array $settings, Site $site ): void
	{
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $subKey => $subValue ) {
					$fullKey = "{$key}.{$subKey}";

					if ( in_array( $fullKey, self::ALLOWED_SETTINGS, true ) ) {
						$this->settings->set( $fullKey, $subValue, $site );
					}
				}
			} elseif ( in_array( $key, self::ALLOWED_SETTINGS, true ) ) {
				$this->settings->set( $key, $value, $site );
			}
		}
	}

	/**
	 * Get the current authenticated site.
	 *
	 * @return Site|null
	 */
	protected function getCurrentSite(): ?Site
	{
		// Check if the auth guard returned a Site
		$user = Auth::guard( 'analytics-api' )->user();

		if ( $user instanceof Site ) {
			return $user;
		}

		// Fall back to TenantManager
		if ( $this->tenantManager->hasCurrent() ) {
			return $this->tenantManager->current();
		}

		return null;
	}
}
