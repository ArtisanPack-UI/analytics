<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Data\VisitorData;
use ArtisanPackUI\Analytics\Events\PageViewTracked;
use ArtisanPackUI\Analytics\Jobs\ProcessBatchTracking;
use ArtisanPackUI\Analytics\Jobs\ProcessEvent;
use ArtisanPackUI\Analytics\Jobs\ProcessPageView;
use ArtisanPackUI\Analytics\Models\AnonymousPageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider;
use ArtisanPackUI\Core\MultiTenancy\SiteContext;
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
 */
class TrackingService
{
    /**
     * Longest path stored on an anonymous page view.
     *
     * @var int
     */
    protected const MAX_ANONYMOUS_PATH_LENGTH = 512;

    /**
     * Create a new TrackingService instance.
     *
     * @param  VisitorResolver  $visitorResolver  The visitor resolver service.
     * @param  SessionManager  $sessionManager  The session manager service.
     * @param  DeviceDetector  $deviceDetector  The device detector service.
     * @param  IpAnonymizer  $ipAnonymizer  The IP anonymizer service.
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
     * @param  PageViewData  $data  The page view data.
     * @param  Request  $request  The HTTP request.
     * @param  int|null  $siteId  The site ID.
     *
     * @since 1.0.0
     */
    public function trackPageView( PageViewData $data, Request $request, ?int $siteId = null ): void
    {
        try {
            // Excluded-path filtering happens here rather than in PrivacyFilter
            // so the decision is made once per tracked page view. A batch beacon
            // carries many paths in one HTTP request, so the middleware has no
            // single path it can evaluate on the request's behalf.
            if ( $this->isExcludedPath( $data->path ) ) {
                return;
            }

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

            // Update enrichedData with resolved visitor and session IDs for storage
            $finalData = new PageViewData(
                path: $enrichedData->path,
                title: $enrichedData->title,
                referrer: $enrichedData->referrer,
                sessionId: null !== $session ? $session->id : $enrichedData->sessionId,
                visitorId: $visitor->id,
                ipAddress: $enrichedData->ipAddress,
                userAgent: $enrichedData->userAgent,
                country: $enrichedData->country,
                deviceType: $enrichedData->deviceType,
                browser: $enrichedData->browser,
                browserVersion: $enrichedData->browserVersion,
                os: $enrichedData->os,
                osVersion: $enrichedData->osVersion,
                screenWidth: $enrichedData->screenWidth,
                screenHeight: $enrichedData->screenHeight,
                viewportWidth: $enrichedData->viewportWidth,
                viewportHeight: $enrichedData->viewportHeight,
                utmSource: $enrichedData->utmSource,
                utmMedium: $enrichedData->utmMedium,
                utmCampaign: $enrichedData->utmCampaign,
                utmTerm: $enrichedData->utmTerm,
                utmContent: $enrichedData->utmContent,
                loadTime: $enrichedData->loadTime,
                customData: $enrichedData->customData,
                fingerprint: $enrichedData->fingerprint,
                tenantId: $enrichedData->tenantId,
                siteId: $siteId,
            );

            // Dispatch job for processing
            $this->dispatchPageView( $finalData, $siteId );
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
     * @param  string  $sessionId  The session ID.
     * @param  string  $path  The page path.
     * @param  array<string, mixed>  $data  The engagement data.
     * @param  int|null  $siteId  The site ID.
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
     * @param  EventData  $data  The event data.
     * @param  Request  $request  The HTTP request.
     * @param  int|null  $siteId  The site ID.
     *
     * @since 1.0.0
     */
    public function trackEvent( EventData $data, Request $request, ?int $siteId = null ): void
    {
        try {
            // See trackPageView() — one exclusion decision per tracked item.
            if ( $this->isExcludedPath( $data->path ) ) {
                return;
            }

            // Resolve or create visitor from request
            $visitorData = $this->createVisitorDataFromRequest( $request, $data->toArray() );
            $visitor     = $this->visitorResolver->resolve( $visitorData, $siteId );

            // Increment visitor counter
            $this->visitorResolver->incrementCounter( $visitor, 'events' );

            // Get or create session if we have a session ID
            $sessionId = $data->sessionId ?? '';
            $session   = null;

            if ( '' !== $sessionId ) {
                $session = $this->sessionManager->getOrCreate( $sessionId, $visitor, $siteId );
            }

            // Create final EventData with resolved visitor and session IDs
            $finalData = new EventData(
                name: $data->name,
                properties: $data->properties,
                sessionId: null !== $session ? $session->id : $data->sessionId,
                visitorId: $visitor->id,
                path: $data->path,
                ipAddress: $this->ipAnonymizer->anonymize( $request->ip() ),
                userAgent: $request->userAgent(),
                value: $data->value,
                category: $data->category,
                action: $data->action,
                label: $data->label,
                sourcePackage: $data->sourcePackage,
                pageViewId: $data->pageViewId,
                tenantId: $data->tenantId ?? $this->getTenantId( $request ),
                siteId: $siteId,
            );

            // Dispatch job for processing
            $this->dispatchEvent( $finalData, $siteId );
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
     * @param  array<int, array<string, mixed>>  $items  The batch items.
     * @param  Request  $request  The HTTP request.
     * @param  int|null  $siteId  The site ID.
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
     * @param  array<string, mixed>  $item  The item data.
     * @param  Request  $request  The HTTP request.
     * @param  int|null  $siteId  The site ID.
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
     * Record a page view from a visitor who has not granted consent.
     *
     * Writes to `analytics_anonymous_page_views`, which has no columns capable
     * of identifying anyone. Nothing here resolves a visitor or touches a
     * session, so no cookie is set and no fingerprint is computed — the row is
     * a count, not a person.
     *
     * Deliberately still honours `canTrack()` (Do Not Track / Global Privacy
     * Control, excluded IPs, excluded user agents, bot detection) and the
     * excluded-path list. Anonymous mode is a lawful-basis argument about
     * *identifiability*, not a licence to ignore an explicit opt-out.
     *
     * @param  array{path: string, title?: string|null, referrer_host?: string|null}  $data  Validated payload.
     * @param  Request  $request  The HTTP request.
     * @param  int|null  $siteId  The site ID.
     *
     * @since 1.5.0
     */
    public function trackAnonymousPageView( array $data, Request $request, ?int $siteId = null ): void
    {
        try {
            if ( ! $this->isAnonymousModeEnabled() ) {
                return;
            }

            if ( ! $this->canTrack( $request ) ) {
                return;
            }

            $path = (string) ( $data['path'] ?? '' );

            if ( '' === $path || $this->isExcludedPath( $path ) ) {
                return;
            }

            AnonymousPageView::create( [
                'site_id'       => $siteId,
                'path'          => $this->normalizeAnonymousPath( $path ),
                'title'         => $data['title'] ?? null,
                'referrer_host' => $this->normalizeReferrerHost( $data['referrer_host'] ?? null ),
                // Device *class* only — 'desktop' / 'mobile' / 'tablet'. The
                // user agent string itself is never stored here; it is a
                // meaningful component of a browser fingerprint.
                'device_type' => $this->deviceDetector->getDeviceType( $request->userAgent() ),
                'tenant_id'   => $this->getTenantId( $request ),
                'created_at'  => now(),
            ] );
        } catch ( Throwable $e ) {
            Log::error( 'Analytics tracking error (anonymous pageview)', [
                'error' => $e->getMessage(),
                'path'  => $data['path'] ?? null,
            ] );
        }
    }

    /**
     * Whether pre-consent anonymous tracking is enabled.
     *
     * @since 1.5.0
     */
    public function isAnonymousModeEnabled(): bool
    {
        return (bool) config( 'artisanpack.analytics.privacy.anonymous_mode', false );
    }

    /**
     * Start a new session.
     *
     * @param  SessionData  $data  The session data.
     * @param  Request  $request  The HTTP request.
     * @param  int|null  $siteId  The site ID.
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
     * @param  string  $sessionId  The session ID.
     * @param  array<string, mixed>  $data  Optional final data.
     * @param  int|null  $siteId  The site ID.
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
     * @param  string  $sessionId  The session ID.
     * @param  int|null  $siteId  The site ID.
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
     * @param  Request  $request  The HTTP request.
     * @param  array<string, mixed>  $data  Additional data.
     * @param  int|null  $siteId  The site ID.
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
     * @param  Request  $request  The HTTP request.
     *
     * @since 1.0.0
     */
    public function canTrack( Request $request ): bool
    {
        // Check if analytics is enabled
        if ( ! config( 'artisanpack.analytics.enabled', true ) ) {
            return false;
        }

        // Check DNT and GPC headers. Independently: a browser sending `DNT: 0`
        // alongside `Sec-GPC: 1` is not withdrawing its GPC signal, and GPC is
        // the one carrying legal weight under CCPA.
        if ( config( 'artisanpack.analytics.privacy.respect_dnt', true ) ) {
            if ( '1' === $request->header( 'DNT' ) || '1' === $request->header( 'Sec-GPC' ) ) {
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
     * Check whether a tracked path is excluded from tracking.
     *
     * Takes the path of the page being tracked — never the URI of the
     * ingest endpoint the beacon was posted to. Those are different
     * things, and conflating them is what made every batched beacon
     * match the `/api/*` exclusion that ships in the default config.
     *
     * A null or empty path is not treated as excluded; there is nothing
     * to match against, and silently dropping such an item would repeat
     * the same class of invisible data loss.
     *
     * @param  string|null  $path  The path of the tracked page.
     *
     * @since 1.5.0
     */
    public function isExcludedPath( ?string $path ): bool
    {
        if ( null === $path || '' === $path ) {
            return false;
        }

        $excludedPaths = config( 'artisanpack.analytics.privacy.excluded_paths', [] );

        if ( empty( $excludedPaths ) ) {
            return false;
        }

        foreach ( $excludedPaths as $excludedPath ) {
            if ( $this->pathMatches( $path, (string) $excludedPath ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduce a path to the part that identifies the page.
     *
     * The anonymous table has no column meant to hold an identifier, but `path`
     * is free text, and a mis-wired client posting `/reset?token=…` would store
     * that token verbatim — in the one dataset whose whole promise is that it
     * carries nothing identifying. The query string and fragment are dropped
     * server-side, so the promise does not depend on the client behaving; this
     * is also what `pathMatches()` already compares exclusions against.
     *
     * The result is capped well inside the column so a hostile beacon cannot
     * make the INSERT itself fail against a Postgres btree index, whose tuples
     * cap out around 2704 bytes — reachable with 2048 multi-byte characters.
     *
     * @param  string  $path  The path as posted by the client.
     *
     * @return string The path, without query string or fragment.
     *
     * @since 1.5.0
     */
    protected function normalizeAnonymousPath( string $path ): string
    {
        $normalized = parse_url( $path, PHP_URL_PATH );

        if ( ! is_string( $normalized ) || '' === $normalized ) {
            // parse_url() returns false for a seriously malformed path and null
            // for one that is only a query or fragment. Neither names a page.
            $normalized = '/';
        }

        return mb_substr( $normalized, 0, self::MAX_ANONYMOUS_PATH_LENGTH );
    }

    /**
     * Reduce a referrer to its host.
     *
     * Clients are asked to send a host, but a full URL arriving here would
     * otherwise be stored verbatim along with whatever its query string
     * carries — search terms, share identifiers. Reducing it server-side means
     * the guarantee does not depend on the client behaving.
     *
     * @since 1.5.0
     */
    protected function normalizeReferrerHost( ?string $referrer ): ?string
    {
        if ( null === $referrer || '' === trim( $referrer ) ) {
            return null;
        }

        $referrer = trim( $referrer );
        $host     = parse_url( $referrer, PHP_URL_HOST );

        if ( is_string( $host ) && '' !== $host ) {
            return $host;
        }

        // Not a URL. Accept a bare host, but never anything with a path,
        // query or fragment hanging off it.
        if ( 1 === preg_match( '/^[A-Za-z0-9.\-]+$/', $referrer ) ) {
            return $referrer;
        }

        return null;
    }

    /**
     * Check if a path matches an exclusion pattern (supports wildcards).
     *
     * @param  string  $path  The path to check.
     * @param  string  $pattern  The exclusion pattern.
     *
     * @since 1.5.0
     */
    protected function pathMatches( string $path, string $pattern ): bool
    {
        // Compare path only — a tracked path may arrive with a query string
        // or fragment attached, and neither should affect exclusion.
        $path = (string) parse_url( $path, PHP_URL_PATH );

        // Normalize paths
        $path    = '/' . ltrim( $path, '/' );
        $pattern = '/' . ltrim( $pattern, '/' );

        // Exact match
        if ( $path === $pattern ) {
            return true;
        }

        // Wildcard match
        if ( str_contains( $pattern, '*' ) ) {
            // Escape regex metacharacters first, then convert escaped wildcards to regex
            $escaped = preg_quote( $pattern, '/' );
            $regex   = '/^' . str_replace( '\\*', '.*', $escaped ) . '$/';

            return 1 === preg_match( $regex, $path );
        }

        return false;
    }

    /**
     * Enrich page view data with device information.
     *
     * @param  PageViewData  $data  The page view data.
     * @param  Request  $request  The HTTP request.
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
            fingerprint: $data->fingerprint,
            tenantId: $data->tenantId ?? $this->getTenantId( $request ),
        );
    }

    /**
     * Create visitor data from request.
     *
     * @param  Request  $request  The HTTP request.
     * @param  array<string, mixed>  $data  Additional data.
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
     * @param  PageViewData  $data  The page view data.
     * @param  int|null  $siteId  The site ID.
     *
     * @since 1.0.0
     */
    protected function dispatchPageView( PageViewData $data, ?int $siteId = null ): void
    {
        if ( $this->shouldQueue() ) {
            // The job forwards to secondary providers itself, after it has
            // stored the row — so a forwarder never holds a page view the
            // local database is missing, and the forwarding call runs on the
            // worker rather than the ingest request.
            ProcessPageView::dispatch( $data, $siteId, true )
                ->onQueue( $this->getQueueName() );

            return;
        }

        // Process synchronously using the local provider, then forward to any
        // secondary active provider (e.g. the GA4 Measurement Protocol
        // forwarder) now that the row is stored.
        app( LocalAnalyticsProvider::class )
            ->storePageView( $data );

        // The Analytics::trackPageView() facade never routes through this
        // method, so facade callers keep their single fan-out with no double
        // send. The listener skips the local provider, which stored the row.
        PageViewTracked::dispatch( $data, $siteId );
    }

    /**
     * Dispatch an event job.
     *
     * @param  EventData  $data  The event data.
     * @param  int|null  $siteId  The site ID.
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
        app( LocalAnalyticsProvider::class )
            ->storeEvent( $data );
    }

    /**
     * Check if processing should be queued.
     *
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
     * The shared site context answers first, mirroring the `TenantResolver`
     * middleware, so a request that resolved a site for every other package
     * writes that same site here. The deprecated single-resolver setting is
     * only consulted when nothing is in context.
     *
     * @param  Request  $request  The HTTP request.
     *
     * @since 1.0.0
     */
    protected function getTenantId( Request $request ): string|int|null
    {
        if ( ! analyticsMultiTenancyEnabled() ) {
            return null;
        }

        $siteId = app( SiteContext::class )->currentSiteId();

        if ( null !== $siteId ) {
            return $siteId;
        }

        // Check if a resolver is set
        $resolver = config( 'artisanpack.analytics.multi_tenant.resolver' );

        if ( null === $resolver || ! is_string( $resolver ) || ! class_exists( $resolver ) ) {
            return null;
        }

        $resolved = app( $resolver )->resolve( $request );

        // The deprecated setting predates the contract, so what comes back may
        // be a Site model rather than an identifier. Both are usable; anything
        // else would be a TypeError swallowed by the caller's try/catch, losing
        // the tenant on every single page view while only logging noise.
        if ( $resolved instanceof Site ) {
            return $resolved->id;
        }

        if ( null !== $resolved && ! is_string( $resolved ) && ! is_int( $resolved ) ) {
            Log::warning( '[Analytics] The deprecated "multi_tenant.resolver" returned an unusable value.', [
                'resolver' => $resolver,
                'returned' => get_debug_type( $resolved ),
            ]);

            return null;
        }

        return $resolved;
    }
}
