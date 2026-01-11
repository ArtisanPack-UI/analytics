<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Str;

/**
 * Session management service.
 *
 * Handles session lifecycle including creation, extension, and finalization
 * with engagement metrics calculation.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class SessionManager
{
	/**
	 * Get or create a session.
	 *
	 * @param string  $sessionId The client-provided session ID.
	 * @param Visitor $visitor   The visitor.
	 * @param int|null $siteId   The site ID.
	 *
	 * @return Session
	 *
	 * @since 1.0.0
	 */
	public function getOrCreate( string $sessionId, Visitor $visitor, ?int $siteId = null ): Session
	{
		$session = $this->findActive( $sessionId, $siteId );

		if ( null !== $session ) {
			// Touch the session to keep it alive
			$session->update( [
				'last_activity_at' => now(),
			] );

			return $session;
		}

		// Create a new session
		return $this->createFromSessionId( $sessionId, $visitor, $siteId );
	}

	/**
	 * Create a new session from session data.
	 *
	 * @param SessionData  $data    The session data.
	 * @param Visitor      $visitor The visitor.
	 * @param int|null     $siteId  The site ID.
	 *
	 * @return Session
	 *
	 * @since 1.0.0
	 */
	public function create( SessionData $data, Visitor $visitor, ?int $siteId = null ): Session
	{
		$referrerDomain = null;

		if ( null !== $data->referrer ) {
			$parsed         = parse_url( $data->referrer );
			$referrerDomain = $parsed['host'] ?? null;
		}

		return Session::create( [
			'id'                 => Str::uuid()->toString(),
			'site_id'            => $siteId,
			'visitor_id'         => $visitor->id,
			'session_id'         => Str::uuid()->toString(),
			'started_at'         => now(),
			'last_activity_at'   => now(),
			'duration'           => 0,
			'entry_page'         => $data->entryPath ?? '/',
			'page_count'         => 0,
			'is_bounce'          => true,
			'referrer'           => $data->referrer,
			'referrer_domain'    => $referrerDomain,
			'referrer_type'      => $this->determineReferrerType( $data->referrer, $data->utmMedium ),
			'utm_source'         => $data->utmSource,
			'utm_medium'         => $data->utmMedium,
			'utm_campaign'       => $data->utmCampaign,
			'utm_term'           => $data->utmTerm,
			'utm_content'        => $data->utmContent,
			'tenant_id'          => $data->tenantId,
		] );
	}

	/**
	 * Extend a session (heartbeat).
	 *
	 * @param string   $sessionId The session ID.
	 * @param int|null $siteId    The site ID.
	 *
	 * @return bool True if the session was extended.
	 *
	 * @since 1.0.0
	 */
	public function extend( string $sessionId, ?int $siteId = null ): bool
	{
		$session = $this->findActive( $sessionId, $siteId );

		if ( null === $session ) {
			return false;
		}

		$session->update( [
			'last_activity_at' => now(),
			'duration'         => $session->calculateDuration(),
		] );

		return true;
	}

	/**
	 * End a session with final metrics.
	 *
	 * @param string               $sessionId The session ID.
	 * @param array<string, mixed> $data      Optional final data (exit_page, etc.).
	 * @param int|null             $siteId    The site ID.
	 *
	 * @return bool True if the session was ended.
	 *
	 * @since 1.0.0
	 */
	public function end( string $sessionId, array $data = [], ?int $siteId = null ): bool
	{
		$session = $this->findBySessionId( $sessionId, $siteId );

		if ( null === $session ) {
			return false;
		}

		$metrics = $this->calculateMetrics( $session );

		$session->update( array_merge( $metrics, [
			'ended_at'  => now(),
			'exit_page' => $data['exit_page'] ?? $data['path'] ?? null,
		] ) );

		return true;
	}

	/**
	 * Check if a session has expired.
	 *
	 * @param Session $session The session to check.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isExpired( Session $session ): bool
	{
		$timeout = $this->getSessionTimeout();

		return null !== $session->ended_at ||
			null === $session->last_activity_at ||
			$session->last_activity_at->lt( now()->subMinutes( $timeout ) );
	}

	/**
	 * Find an active session by session_id.
	 *
	 * @param string   $sessionId The session ID.
	 * @param int|null $siteId    The site ID.
	 *
	 * @return Session|null
	 *
	 * @since 1.0.0
	 */
	public function findActive( string $sessionId, ?int $siteId = null ): ?Session
	{
		$session = $this->findBySessionId( $sessionId, $siteId );

		if ( null === $session ) {
			return null;
		}

		if ( $this->isExpired( $session ) ) {
			return null;
		}

		return $session;
	}

	/**
	 * Find a session by session_id.
	 *
	 * @param string   $sessionId The session ID.
	 * @param int|null $siteId    The site ID.
	 *
	 * @return Session|null
	 *
	 * @since 1.0.0
	 */
	public function findBySessionId( string $sessionId, ?int $siteId = null ): ?Session
	{
		$query = Session::where( 'session_id', $sessionId );

		if ( null !== $siteId ) {
			$query->where( 'site_id', $siteId );
		}

		return $query->first();
	}

	/**
	 * Find a session by its internal ID.
	 *
	 * @param string   $id     The internal session ID.
	 * @param int|null $siteId The site ID.
	 *
	 * @return Session|null
	 *
	 * @since 1.0.0
	 */
	public function findById( string $id, ?int $siteId = null ): ?Session
	{
		$query = Session::where( 'id', $id );

		if ( null !== $siteId ) {
			$query->where( 'site_id', $siteId );
		}

		return $query->first();
	}

	/**
	 * Record a page view for a session.
	 *
	 * @param Session $session  The session.
	 * @param string  $path     The page path.
	 * @param string|null $title The page title.
	 *
	 * @since 1.0.0
	 */
	public function recordPageView( Session $session, string $path, ?string $title = null ): void
	{
		$pageCount = $session->page_count + 1;

		$updates = [
			'last_activity_at' => now(),
			'page_count'       => $pageCount,
			'exit_page'        => $path,
			'is_bounce'        => $pageCount <= 1,
			'duration'         => $session->calculateDuration(),
		];

		// Set entry page and title if this is the first page view
		if ( 1 === $pageCount ) {
			$updates['entry_page'] = $path;

			if ( null !== $title ) {
				$updates['landing_page_title'] = $title;
			}
		}

		$session->update( $updates );
	}

	/**
	 * Create a session from a client-provided session ID.
	 *
	 * @param string   $sessionId The client session ID.
	 * @param Visitor  $visitor   The visitor.
	 * @param int|null $siteId    The site ID.
	 *
	 * @return Session
	 *
	 * @since 1.0.0
	 */
	protected function createFromSessionId( string $sessionId, Visitor $visitor, ?int $siteId = null ): Session
	{
		return Session::create( [
			'id'                 => Str::uuid()->toString(),
			'site_id'            => $siteId,
			'visitor_id'         => $visitor->id,
			'session_id'         => $sessionId,
			'started_at'         => now(),
			'last_activity_at'   => now(),
			'duration'           => 0,
			'entry_page'         => '/',
			'page_count'         => 0,
			'is_bounce'          => true,
			'referrer_type'      => 'direct',
			'tenant_id'          => $visitor->tenant_id,
		] );
	}

	/**
	 * Calculate session metrics.
	 *
	 * @param Session $session The session.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	protected function calculateMetrics( Session $session ): array
	{
		$pageCount = $session->pageViews()->count();

		return [
			'duration'   => $session->calculateDuration(),
			'page_count' => $pageCount,
			'is_bounce'  => $pageCount <= 1,
		];
	}

	/**
	 * Determine the referrer type.
	 *
	 * @param string|null $referrer  The referrer URL.
	 * @param string|null $utmMedium The UTM medium parameter.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function determineReferrerType( ?string $referrer, ?string $utmMedium ): string
	{
		// Check UTM medium first
		if ( null !== $utmMedium ) {
			$medium = strtolower( $utmMedium );

			if ( str_contains( $medium, 'cpc' ) || str_contains( $medium, 'paid' ) ) {
				return 'paid';
			}

			if ( str_contains( $medium, 'social' ) ) {
				return 'social';
			}

			if ( str_contains( $medium, 'email' ) ) {
				return 'email';
			}

			if ( str_contains( $medium, 'organic' ) ) {
				return 'organic';
			}
		}

		// No referrer = direct
		if ( null === $referrer || '' === $referrer ) {
			return 'direct';
		}

		$parsed = parse_url( $referrer );
		$host   = $parsed['host'] ?? '';

		// Check for search engines
		$searchEngines = [ 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex' ];

		foreach ( $searchEngines as $engine ) {
			if ( str_contains( strtolower( $host ), $engine ) ) {
				return 'organic';
			}
		}

		// Check for social networks
		$socialNetworks = [ 'facebook', 'twitter', 'linkedin', 'instagram', 'pinterest', 'reddit', 'youtube', 'tiktok' ];

		foreach ( $socialNetworks as $network ) {
			if ( str_contains( strtolower( $host ), $network ) ) {
				return 'social';
			}
		}

		return 'referral';
	}

	/**
	 * Get the session timeout in minutes.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	protected function getSessionTimeout(): int
	{
		return (int) config( 'artisanpack.analytics.session.timeout', 30 );
	}
}
