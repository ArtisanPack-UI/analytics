<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Controllers;

use ArtisanPackUI\Analytics\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Consent management controller.
 *
 * Handles consent status queries and updates for GDPR/privacy compliance.
 *
 * @since   1.0.0
 */
class ConsentController extends Controller
{
	/**
	 * Create a new ConsentController instance.
	 *
	 * @param ConsentService $consentService The consent service.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		protected ConsentService $consentService,
	) {
	}

	/**
	 * Get consent status for a visitor.
	 *
	 * GET /api/analytics/consent/status
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function status( Request $request ): JsonResponse
	{
		$visitorId = $request->input( 'visitor_id' );

		if ( empty( $visitorId ) ) {
			return response()->json( [
				'error'   => __( 'Visitor ID is required.' ),
				'success' => false,
			], 400 );
		}

		return response()->json( [
			'success'          => true,
			'consent_required' => config( 'artisanpack.analytics.privacy.consent_required', false ),
			'categories'       => $this->consentService->getConsentStatus( $visitorId ),
		] );
	}

	/**
	 * Update consent preferences for a visitor.
	 *
	 * POST /api/analytics/consent/update
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function update( Request $request ): JsonResponse
	{
		$validated = $request->validate( [
			'visitor_id'   => 'required|string|max:255',
			'categories'   => 'required|array',
			'categories.*' => 'boolean',
		] );

		$granted = [];
		$revoked = [];

		foreach ( $validated['categories'] as $category => $isGranted ) {
			if ( $isGranted ) {
				$granted[] = $category;
			} else {
				$revoked[] = $category;
			}
		}

		if ( ! empty( $granted ) ) {
			$this->consentService->grantConsent( $validated['visitor_id'], $granted, $request );
		}

		if ( ! empty( $revoked ) ) {
			$this->consentService->revokeConsent( $validated['visitor_id'], $revoked );
		}

		return response()->json( [
			'success'    => true,
			'categories' => $this->consentService->getConsentStatus( $validated['visitor_id'] ),
		] );
	}

	/**
	 * Grant consent for all categories.
	 *
	 * POST /api/analytics/consent/accept-all
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function acceptAll( Request $request ): JsonResponse
	{
		$visitorId = $request->input( 'visitor_id' );

		if ( empty( $visitorId ) ) {
			return response()->json( [
				'error'   => __( 'Visitor ID is required.' ),
				'success' => false,
			], 400 );
		}

		$categories = array_keys( config( 'artisanpack.analytics.privacy.consent_categories', [] ) );

		$this->consentService->grantConsent( $visitorId, $categories, $request );

		return response()->json( [
			'success'    => true,
			'categories' => $this->consentService->getConsentStatus( $visitorId ),
		] );
	}

	/**
	 * Revoke consent for all non-required categories.
	 *
	 * POST /api/analytics/consent/reject-all
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function rejectAll( Request $request ): JsonResponse
	{
		$visitorId = $request->input( 'visitor_id' );

		if ( empty( $visitorId ) ) {
			return response()->json( [
				'error'   => __( 'Visitor ID is required.' ),
				'success' => false,
			], 400 );
		}

		$categories = config( 'artisanpack.analytics.privacy.consent_categories', [] );

		// Revoke all non-required categories
		$toRevoke = [];
		$toGrant  = [];

		foreach ( $categories as $key => $category ) {
			if ( $category['required'] ?? false ) {
				$toGrant[] = $key;
			} else {
				$toRevoke[] = $key;
			}
		}

		if ( ! empty( $toGrant ) ) {
			$this->consentService->grantConsent( $visitorId, $toGrant, $request );
		}

		if ( ! empty( $toRevoke ) ) {
			$this->consentService->revokeConsent( $visitorId, $toRevoke );
		}

		return response()->json( [
			'success'    => true,
			'categories' => $this->consentService->getConsentStatus( $visitorId ),
		] );
	}
}
