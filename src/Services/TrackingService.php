<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Data\VisitorData;
use ArtisanPackUI\Analytics\Jobs\ProcessBatchTracking;
use ArtisanPackUI\Analytics\Jobs\ProcessEvent;
use ArtisanPackUI\Analytics\Jobs\ProcessPageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Main tracking orchestration service.
 *
 * Coordinates between visitor resolution, session management, and job dispatching
 * for processing analytics tracking data.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class TrackingService
{
	/**
	 * Create a new TrackingService instance.
	 *
	 * @param VisitorResolver $visitorResolver The visitor resolver service.
	 * @param SessionManager  $sessionManager  The session manager service.
	 * @param DeviceDetector  $deviceDetector  The device detector service.
	 * @param IpAnonymizer    $ipAnonymizer    The IP anonymizer service.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		protected VisitorResolver $visitorResolver,
		protected SessionManager $sessionManager,
		protected DeviceDetector $deviceDetector,
		protected IpAnonymizer $ipAnonymizer,
	) {
	}

	/**
	 * Process a page view tracking request.
	 *
	 * @param PageViewData $data    The page view data.
	 * @param Request      $request The HTTP request.
	 * @param int|null     $siteId  The site ID.
	 *
	 * @since 1.0.0
	 */
	public function trackPageView( PageViewData $data, Request $request, ?int $siteId = null ): void
	{
		try {
			// Enrich data with device info
			$enrichedData = $this->enrichPageViewData( $data, $request );

			// Resolve or create visitor
			$visitorData = $this->createVisitorDataFromRequest( $request, $data->toArray() );
			$visitor     = $this->visitorResolver->resolve( $visitorData, $siteId );

			// Get or create session
			$sessionId = $data->sessionId ?? '';
			$session   = null;

			if ( '' !== $sessionId ) {
				$session = $this->sessionManager->getOrCreate( $sessionId, $visitor, $siteId );
				$this->sessionManager->recordPageView( $session, $enrichedData->path, $enrichedData->title );
			}

			// Increment visitor counters
			$this->visitorResolver->incrementCounter( $visitor, 'pageviews' );

			// Dispatch job for processing
			$this->dispatchPageView( $enrichedData, $siteId );
		} catch ( Throwable $e ) {
			Log::error( 'Analytics tracking error (pageview)', [
				'error' => $e->getMessage(),
				'path'  => $data->path,
			] );
		}
	}

	/**
	 * Update an existing page view with engagement metrics.
	 *
	 * @param string               $sessionId The session ID.
	 * @param string               $path      The page path.
	 * @param array<string, mixed> $data      The engagement data.
	 * @param int|null             $siteId    The site ID.
	 *
	 * @since 1.0.0
	 */
	public function updatePageView( string $sessionId, string $path, array $data, ?int $siteId = null ): void
	{
		try {
			$session = $this->sessionManager->findActive( $sessionId, $siteId );

			if ( null === $session ) {
				return;
			}

			// Find the page view and update it
			$pageView = $session->pageViews()
				->where( 'path', $path )
				->latest()
				->first();

			if ( null === $pageView ) {
				return;
			}

			$updates = [];

			if ( isset( $data['time_on_page'] ) ) {
				$updates['time_on_page'] = (int) $data['time_on_page'];
			}

			if ( isset( $data['engaged_time'] ) ) {
				$updates['engaged_time'] = (int) $data['engaged_time'];
			}

			if ( isset( $data['scroll_depth'] ) ) {
				$updates['scroll_depth'] = min( 100, max( 0, (int) $data['scroll_depth'] ) );
			}

			if ( ! empty( $updates ) ) {
				$pageView->update( $updates );
			}
		} catch ( Throwable $e ) {
			Log::error( 'Analytics tracking error (update pageview)', [
				'error'      => $e->getMessage(),
				'session_id' => $sessionId,
				'path'       => $path,
			] );
		}
	}

	/**
	 * Process an event tracking request.
	 *
	 * @param EventData $data    The event data.
	 * @param Request   $request The HTTP request.
	 * @param int|null  $siteId  The site ID.
	 *
	 * @since 1.0.0
	 */
	public function trackEvent( EventData $data, Request $request, ?int $siteId = null ): void
	{
		try {
			// Resolve visitor if we have an ID
			if ( null !== $data->visitorId ) {
				$visitor = $this->visitorResolver->resolveById( $data->visitorId, $siteId );

				if ( null !== $visitor ) {
					$this->visitorResolver->incrementCounter( $visitor, 'events' );
				}
			}

			// Extend session if we have one
			if ( null !== $data->sessionId ) {
				$this->sessionManager->extend( $data->sessionId, $siteId );
			}

			// Dispatch job for processing
			$this->dispatchEvent( $data, $siteId );
		} catch ( Throwable $e ) {
			Log::error( 'Analytics tracking error (event)', [
				'error' => $e->getMessage(),
				'event' => $data->name,
			] );
		}
	}

	/**
	 * Process a batch of tracking items.
	 *
	 * @param array<int, array<string, mixed>> $items   The batch items.
	 * @param Request                          $request The HTTP request.
	 * @param int|null                         $siteId  The site ID.
	 *
	 * @since 1.0.0
	 */
	public function processBatch( array $items, Request $request, ?int $siteId = null ): void
	{
		try {
			if ( $this->shouldQueue() ) {
				ProcessBatchTracking::dispatch(
					$items,
					$request->ip(),
					$request->userAgent(),
					$this->getTenantId( $request ),
					$siteId,
				)->onQueue( $this->getQueueName() );

				return;
			}

			// Process synchronously
			foreach ( $items as $item ) {
				$this->processItem( $item, $request, $siteId );
			}
		} catch ( Throwable $e ) {
			Log::error( 'Analytics tracking error (batch)', [
				'error' => $e->getMessage(),
				'count' => count( $items ),
			] );
		}
	}

	/**
	 * Process a single batch item.
	 *
	 * @param array<string, mixed> $item    The item data.
	 * @param Request              $request The HTTP request.
	 * @param int|null             $siteId  The site ID.
	 *
	 * @since 1.0.0
	 */
	public function processItem( array $item, Request $request, ?int $siteId = null ): void
	{
		$type = $item['type'] ?? '';
		$data = $item['data'] ?? [];

		match ( $type ) {
			'pageview' => $this->trackPageView(
				PageViewData::fromRequest( $request, $data ),
				$request,
				$siteId,
			),
			'event' => $this->trackEvent(
				EventData::fromRequest( $request, $data ),
				$request,
				$siteId,
			),
			default => null,
		};
	}

	/**
	 * Start a new session.
	 *
	 * @param SessionData $data    The session data.
	 * @param Request     $request The HTTP request.
	 * @param int|null    $siteId  The site ID.
	 *
	 * @return Session
	 *
	 * @since 1.0.0
	 */
	public function startSession( SessionData $data, Request $request, ?int $siteId = null ): Session
	{
		// Resolve or create visitor
		$visitorData = $this->createVisitorDataFromRequest( $request, $data->toArray() );
		$visitor     = $this->visitorResolver->resolve( $visitorData, $siteId );

		// Increment session count
		$this->visitorResolver->incrementCounter( $visitor, 'sessions' );

		return $this->sessionManager->create( $data, $visitor, $siteId );
	}

	/**
	 * End an existing session.
	 *
	 * @param string               $sessionId The session ID.
	 * @param array<string, mixed> $data      Optional final data.
	 * @param int|null             $siteId    The site ID.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function endSession( string $sessionId, array $data = [], ?int $siteId = null ): bool
	{
		return $this->sessionManager->end( $sessionId, $data, $siteId );
	}

	/**
	 * Extend a session (heartbeat).
	 *
	 * @param string   $sessionId The session ID.
	 * @param int|null $siteId    The site ID.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function extendSession( string $sessionId, ?int $siteId = null ): bool
	{
		return $this->sessionManager->extend( $sessionId, $siteId );
	}

	/**
	 * Resolve a visitor from request data.
	 *
	 * @param Request              $request The HTTP request.
	 * @param array<string, mixed> $data    Additional data.
	 * @param int|null             $siteId  The site ID.
	 *
	 * @return Visitor
	 *
	 * @since 1.0.0
	 */
	public function resolveVisitor( Request $request, array $data = [], ?int $siteId = null ): Visitor
	{
		$visitorData = $this->createVisitorDataFromRequest( $request, $data );

		return $this->visitorResolver->resolve( $visitorData, $siteId );
	}

	/**
	 * Check if tracking is allowed for this request.
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function canTrack( Request $request ): bool
	{
		// Check if analytics is enabled
		if ( ! config( 'artisanpack.analytics.enabled', true ) ) {
			return false;
		}

		// Check DNT header
		if ( config( 'artisanpack.analytics.privacy.respect_dnt', true ) ) {
			$dnt = $request->header( 'DNT' ) ?? $request->header( 'Sec-GPC' );

			if ( '1' === $dnt ) {
				return false;
			}
		}

		// Check excluded IPs
		$excludedIps = config( 'artisanpack.analytics.privacy.excluded_ips', [] );

		if ( in_array( $request->ip(), $excludedIps, true ) ) {
			return false;
		}

		// Check excluded user agents
		$excludedAgents = config( 'artisanpack.analytics.privacy.excluded_user_agents', [] );
		$userAgent      = $request->userAgent() ?? '';

		foreach ( $excludedAgents as $pattern ) {
			if ( preg_match( $pattern, $userAgent ) ) {
				return false;
			}
		}

		// Check for bots
		if ( $this->deviceDetector->isBot( $userAgent ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Enrich page view data with device information.
	 *
	 * @param PageViewData $data    The page view data.
	 * @param Request      $request The HTTP request.
	 *
	 * @return PageViewData
	 *
	 * @since 1.0.0
	 */
	protected function enrichPageViewData( PageViewData $data, Request $request ): PageViewData
	{
		$deviceInfo = $this->deviceDetector->parse( $request->userAgent() );

		return new PageViewData(
			path: $data->path,
			title: $data->title,
			referrer: $data->referrer ?? $request->header( 'Referer' ),
			sessionId: $data->sessionId,
			visitorId: $data->visitorId,
			ipAddress: $this->ipAnonymizer->anonymize( $request->ip() ),
			userAgent: $request->userAgent(),
			country: $data->country,
			deviceType: $data->deviceType ?? $deviceInfo->deviceType,
			browser: $data->browser ?? $deviceInfo->browser,
			browserVersion: $data->browserVersion ?? $deviceInfo->browserVersion,
			os: $data->os ?? $deviceInfo->os,
			osVersion: $data->osVersion ?? $deviceInfo->osVersion,
			screenWidth: $data->screenWidth,
			screenHeight: $data->screenHeight,
			viewportWidth: $data->viewportWidth,
			viewportHeight: $data->viewportHeight,
			utmSource: $data->utmSource ?? $request->query( 'utm_source' ),
			utmMedium: $data->utmMedium ?? $request->query( 'utm_medium' ),
			utmCampaign: $data->utmCampaign ?? $request->query( 'utm_campaign' ),
			utmTerm: $data->utmTerm ?? $request->query( 'utm_term' ),
			utmContent: $data->utmContent ?? $request->query( 'utm_content' ),
			loadTime: $data->loadTime,
			customData: $data->customData,
			tenantId: $data->tenantId ?? $this->getTenantId( $request ),
		);
	}

	/**
	 * Create visitor data from request.
	 *
	 * @param Request              $request The HTTP request.
	 * @param array<string, mixed> $data    Additional data.
	 *
	 * @return VisitorData
	 *
	 * @since 1.0.0
	 */
	protected function createVisitorDataFromRequest( Request $request, array $data = [] ): VisitorData
	{
		$screenResolution = null;

		if ( isset( $data['screen_width'], $data['screen_height'] ) ) {
			$screenResolution = $data['screen_width'] . 'x' . $data['screen_height'];
		}

		return new VisitorData(
			userAgent: $request->userAgent(),
			ipAddress: $request->ip(),
			screenResolution: $screenResolution,
			timezone: $data['timezone'] ?? null,
			language: $data['language'] ?? $request->getPreferredLanguage(),
			country: $data['country'] ?? null,
			deviceType: $data['device_type'] ?? null,
			browser: $data['browser'] ?? null,
			browserVersion: $data['browser_version'] ?? null,
			os: $data['os'] ?? null,
			osVersion: $data['os_version'] ?? null,
			existingId: $data['visitor_id'] ?? null,
			tenantId: $data['tenant_id'] ?? $this->getTenantId( $request ),
		);
	}

	/**
	 * Dispatch a page view job.
	 *
	 * @param PageViewData $data   The page view data.
	 * @param int|null     $siteId The site ID.
	 *
	 * @since 1.0.0
	 */
	protected function dispatchPageView( PageViewData $data, ?int $siteId = null ): void
	{
		if ( $this->shouldQueue() ) {
			ProcessPageView::dispatch( $data )
				->onQueue( $this->getQueueName() );

			return;
		}

		// Process synchronously using the local provider
		app( \ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider::class )
			->storePageView( $data );
	}

	/**
	 * Dispatch an event job.
	 *
	 * @param EventData $data   The event data.
	 * @param int|null  $siteId The site ID.
	 *
	 * @since 1.0.0
	 */
	protected function dispatchEvent( EventData $data, ?int $siteId = null ): void
	{
		if ( $this->shouldQueue() ) {
			ProcessEvent::dispatch( $data )
				->onQueue( $this->getQueueName() );

			return;
		}

		// Process synchronously using the local provider
		app( \ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider::class )
			->storeEvent( $data );
	}

	/**
	 * Check if processing should be queued.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function shouldQueue(): bool
	{
		return config( 'artisanpack.analytics.local.queue_processing', true );
	}

	/**
	 * Get the queue name.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function getQueueName(): string
	{
		return config( 'artisanpack.analytics.local.queue_name', 'analytics' );
	}

	/**
	 * Get the tenant ID from the request.
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return int|string|null
	 *
	 * @since 1.0.0
	 */
	protected function getTenantId( Request $request ): string|int|null
	{
		if ( ! config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return null;
		}

		// Check if a resolver is set
		$resolver = config( 'artisanpack.analytics.multi_tenant.resolver' );

		if ( null !== $resolver && class_exists( $resolver ) ) {
			return app( $resolver )->resolve( $request );
		}

		return null;
	}
}
