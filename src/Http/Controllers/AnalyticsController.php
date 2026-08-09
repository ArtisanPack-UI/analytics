<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Controllers;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Http\Requests\EndSessionRequest;
use ArtisanPackUI\Analytics\Http\Requests\StartSessionRequest;
use ArtisanPackUI\Analytics\Http\Requests\TrackAnonymousPageViewRequest;
use ArtisanPackUI\Analytics\Http\Requests\TrackBatchRequest;
use ArtisanPackUI\Analytics\Http\Requests\TrackEventRequest;
use ArtisanPackUI\Analytics\Http\Requests\TrackPageViewRequest;
use ArtisanPackUI\Analytics\Http\Requests\TrackPageViewUpdateRequest;
use ArtisanPackUI\Analytics\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analytics tracking controller.
 *
 * Handles incoming tracking requests from the JavaScript tracker
 * and delegates processing to the TrackingService.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Controllers
 */
class AnalyticsController extends Controller
{
	/**
	 * Create a new AnalyticsController instance.
	 *
	 * @param TrackingService $trackingService The tracking service.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		protected TrackingService $trackingService,
	) {
	}

	/**
	 * Track a page view.
	 *
	 * POST /api/analytics/pageview
	 *
	 * @param TrackPageViewRequest $request The validated request.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function pageview( TrackPageViewRequest $request ): Response
	{
		try {
			if ( ! $this->trackingService->canTrack( $request ) ) {
				return $this->noContentResponse();
			}

			$data = PageViewData::fromRequest( $request, $request->validated() );

			$this->trackingService->trackPageView( $data, $request, $this->getSiteId( $request ) );
		} catch ( Throwable $e ) {
			Log::error( 'Analytics pageview error', [
				'error' => $e->getMessage(),
				'path'  => $request->input( 'path' ),
			] );
		}

		return $this->noContentResponse();
	}

	/**
	 * Track a page view from a visitor who has not granted consent.
	 *
	 * POST /api/analytics/anonymous/pageview
	 *
	 * Accepts no identifiers by design — see
	 * {@see TrackAnonymousPageViewRequest}. Returns 204 whether or not the row
	 * was written, matching the other ingest endpoints: the browser has
	 * nothing useful to do with the difference, and anonymous mode being
	 * disabled is not an error worth reporting to it.
	 *
	 * @param TrackAnonymousPageViewRequest $request The validated request.
	 *
	 * @return Response
	 *
	 * @since 1.5.0
	 */
	public function anonymousPageview( TrackAnonymousPageViewRequest $request ): Response
	{
		try {
			$this->trackingService->trackAnonymousPageView(
				$request->validated(),
				$request,
				$this->getSiteId( $request ),
			);
		} catch ( Throwable $e ) {
			Log::error( 'Analytics anonymous pageview error', [
				'error' => $e->getMessage(),
				'path'  => $request->input( 'path' ),
			] );
		}

		return $this->noContentResponse();
	}

	/**
	 * Update a page view with engagement data.
	 *
	 * POST /api/analytics/pageview/update
	 *
	 * @param TrackPageViewUpdateRequest $request The validated request.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function updatePageview( TrackPageViewUpdateRequest $request ): Response
	{
		try {
			if ( ! $this->trackingService->canTrack( $request ) ) {
				return $this->noContentResponse();
			}

			$validated = $request->validated();

			$this->trackingService->updatePageView(
				$validated['session_id'],
				$validated['path'],
				array_intersect_key( $validated, array_flip( [ 'time_on_page', 'engaged_time', 'scroll_depth' ] ) ),
				$this->getSiteId( $request ),
			);
		} catch ( Throwable $e ) {
			Log::error( 'Analytics pageview update error', [
				'error' => $e->getMessage(),
			] );
		}

		return $this->noContentResponse();
	}

	/**
	 * Track a custom event.
	 *
	 * POST /api/analytics/event
	 *
	 * @param TrackEventRequest $request The validated request.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function event( TrackEventRequest $request ): Response
	{
		try {
			if ( ! $this->trackingService->canTrack( $request ) ) {
				return $this->noContentResponse();
			}

			$data = EventData::fromRequest( $request, $request->validated() );

			$this->trackingService->trackEvent( $data, $request, $this->getSiteId( $request ) );
		} catch ( Throwable $e ) {
			Log::error( 'Analytics event error', [
				'error' => $e->getMessage(),
				'name'  => $request->input( 'name' ),
			] );
		}

		return $this->noContentResponse();
	}

	/**
	 * Track multiple items in a batch.
	 *
	 * POST /api/analytics/batch
	 *
	 * @param TrackBatchRequest $request The validated request.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function batch( TrackBatchRequest $request ): Response
	{
		try {
			if ( ! $this->trackingService->canTrack( $request ) ) {
				return $this->noContentResponse();
			}

			$items = $request->validated()['items'] ?? [];

			$this->trackingService->processBatch( $items, $request, $this->getSiteId( $request ) );
		} catch ( Throwable $e ) {
			Log::error( 'Analytics batch error', [
				'error' => $e->getMessage(),
				'count' => count( Arr::wrap( $request->input( 'items', [] ) ) ),
			] );
		}

		return $this->noContentResponse();
	}

	/**
	 * Start a new session.
	 *
	 * POST /api/analytics/session/start
	 *
	 * Unlike other tracking endpoints which return 204 No Content,
	 * this endpoint returns 201 Created with JSON containing the
	 * session_id and site_id for client-side usage.
	 *
	 * @param StartSessionRequest $request The validated request.
	 *
	 * @return JsonResponse|Response Returns 201 JSON on success, 204 on failure/skip.
	 *
	 * @since 1.0.0
	 */
	public function startSession( StartSessionRequest $request ): Response|JsonResponse
	{
		try {
			if ( ! $this->trackingService->canTrack( $request ) ) {
				return $this->noContentResponse();
			}

			$validated = $request->validated();

			$data = new SessionData(
				visitorId: $validated['visitor_id'],
				sessionId: $validated['session_id'],
				entryPath: $validated['entry_page'] ?? null,
				referrer: $validated['referrer'] ?? null,
				ipAddress: $request->ip(),
				userAgent: $request->userAgent(),
				utmSource: $validated['utm_source'] ?? null,
				utmMedium: $validated['utm_medium'] ?? null,
				utmCampaign: $validated['utm_campaign'] ?? null,
				utmTerm: $validated['utm_term'] ?? null,
				utmContent: $validated['utm_content'] ?? null,
			);

			$this->trackingService->startSession( $data, $request, $this->getSiteId( $request ) );

			return response()->json( [
				'session_id' => $validated['session_id'],
				'site_id'    => $this->getSiteId( $request ),
			], 201 );
		} catch ( Throwable $e ) {
			Log::error( 'Analytics session start error', [
				'error' => $e->getMessage(),
			] );
		}

		return $this->noContentResponse();
	}

	/**
	 * End an existing session.
	 *
	 * POST /api/analytics/session/end
	 *
	 * @param EndSessionRequest $request The validated request.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function endSession( EndSessionRequest $request ): Response
	{
		try {
			if ( ! $this->trackingService->canTrack( $request ) ) {
				return $this->noContentResponse();
			}

			$validated = $request->validated();

			$this->trackingService->endSession(
				$validated['session_id'],
				[
					'exit_page'    => $validated['exit_page'] ?? null,
					'time_on_page' => $validated['time_on_page'] ?? null,
					'scroll_depth' => $validated['scroll_depth'] ?? null,
				],
				$this->getSiteId( $request ),
			);
		} catch ( Throwable $e ) {
			Log::error( 'Analytics session end error', [
				'error' => $e->getMessage(),
			] );
		}

		return $this->noContentResponse();
	}

	/**
	 * Extend a session (heartbeat).
	 *
	 * POST /api/analytics/session/extend
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function extendSession( Request $request ): Response
	{
		try {
			if ( ! $this->trackingService->canTrack( $request ) ) {
				return $this->noContentResponse();
			}

			$sessionId = $request->input( 'session_id' );

			// Ensure session_id is a non-empty string to prevent TypeError
			if ( is_string( $sessionId ) && '' !== $sessionId ) {
				$this->trackingService->extendSession(
					$sessionId,
					$this->getSiteId( $request ),
				);
			}
		} catch ( Throwable $e ) {
			Log::error( 'Analytics session extend error', [
				'error' => $e->getMessage(),
			] );
		}

		return $this->noContentResponse();
	}

	/**
	 * Create a 204 No Content response.
	 *
	 * Most tracking endpoints return 204 No Content to avoid revealing
	 * whether tracking was successful or not to the client.
	 *
	 * Note: The startSession() endpoint is an exception - it returns
	 * 201 Created with JSON data on success, falling back to 204 on
	 * failure or when tracking is skipped.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	protected function noContentResponse(): Response
	{
		return response()->noContent();
	}

	/**
	 * Get the site ID from the request.
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return int|null
	 *
	 * @since 1.0.0
	 */
	protected function getSiteId( Request $request ): ?int
	{
		$siteId = $request->input( 'site_id' ) ?? $request->header( 'X-Analytics-Site-Id' );

		if ( null === $siteId ) {
			return null;
		}

		// Request input can be an array — `?site_id[]=1` — and casting one to a
		// string is a warning and the literal "Array", so reject anything that
		// is not scalar before going near a cast.
		if ( ! is_scalar( $siteId ) ) {
			return null;
		}

		// Deliberately stricter than is_numeric(), which accepts "12.5", "1e3"
		// and " 12" — each of which casts to an int naming a different site, or
		// none. Same guard as TenantManager::currentId(). Anchored with \z
		// rather than $, which in PCRE also matches before a trailing newline.
		$siteIdString = is_string( $siteId ) ? trim( $siteId ) : (string) $siteId;

		if ( 1 !== preg_match( '/^\d+\z/', $siteIdString ) ) {
			return null;
		}

		return (int) $siteIdString;
	}
}
