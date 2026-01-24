<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Data;

use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Data Transfer Object for session initialization.
 *
 * Encapsulates all data needed to start a new analytics session.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Data
 */
readonly class SessionData
{
	/**
	 * Create a new SessionData instance.
	 *
	 * @param string          $visitorId   The visitor identifier.
	 * @param string|null     $sessionId   The session identifier (client-provided).
	 * @param string|null     $entryPath   The entry page path.
	 * @param string|null     $referrer    The referrer URL.
	 * @param string|null     $ipAddress   The visitor's IP address.
	 * @param string|null     $userAgent   The visitor's user agent.
	 * @param string|null     $utmSource   The UTM source parameter.
	 * @param string|null     $utmMedium   The UTM medium parameter.
	 * @param string|null     $utmCampaign The UTM campaign parameter.
	 * @param string|null     $utmTerm     The UTM term parameter.
	 * @param string|null     $utmContent  The UTM content parameter.
	 * @param int|string|null $tenantId    The tenant identifier for multi-tenant apps.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public string $visitorId,
		public ?string $sessionId = null,
		public ?string $entryPath = null,
		public ?string $referrer = null,
		public ?string $ipAddress = null,
		public ?string $userAgent = null,
		public ?string $utmSource = null,
		public ?string $utmMedium = null,
		public ?string $utmCampaign = null,
		public ?string $utmTerm = null,
		public ?string $utmContent = null,
		public string|int|null $tenantId = null,
	) {
	}

	/**
	 * Create from an HTTP request.
	 *
	 * @param Request              $request The HTTP request.
	 * @param array<string, mixed> $data    Additional data from the request body.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function fromRequest( Request $request, array $data = [] ): static
	{
		$visitorId = $data['visitor_id'] ?? '';
		if ( empty( $visitorId ) ) {
			throw new InvalidArgumentException( 'Visitor ID is required' );
		}

		return new static(
			visitorId: $visitorId,
			sessionId: $data['session_id'] ?? null,
			entryPath: $data['path'] ?? $request->path(),
			referrer: $data['referrer'] ?? $request->header( 'Referer' ),
			ipAddress: $request->ip(),
			userAgent: $request->userAgent(),
			utmSource: $data['utm_source'] ?? $request->query( 'utm_source' ),
			utmMedium: $data['utm_medium'] ?? $request->query( 'utm_medium' ),
			utmCampaign: $data['utm_campaign'] ?? $request->query( 'utm_campaign' ),
			utmTerm: $data['utm_term'] ?? $request->query( 'utm_term' ),
			utmContent: $data['utm_content'] ?? $request->query( 'utm_content' ),
			tenantId: $data['tenant_id'] ?? null,
		);
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function toArray(): array
	{
		return array_filter( [
			'visitor_id'   => $this->visitorId,
			'session_id'   => $this->sessionId,
			'entry_path'   => $this->entryPath,
			'referrer'     => $this->referrer,
			'ip_address'   => $this->ipAddress,
			'user_agent'   => $this->userAgent,
			'utm_source'   => $this->utmSource,
			'utm_medium'   => $this->utmMedium,
			'utm_campaign' => $this->utmCampaign,
			'utm_term'     => $this->utmTerm,
			'utm_content'  => $this->utmContent,
			'tenant_id'    => $this->tenantId,
		], fn ( $value ) => null !== $value );
	}
}
