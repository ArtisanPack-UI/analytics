<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\VisitorData;
use ArtisanPackUI\Analytics\Models\Visitor;

/**
 * Visitor resolution service.
 *
 * Identifies returning visitors using fingerprinting and visitor IDs,
 * and creates or updates Visitor models accordingly.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class VisitorResolver
{
	/**
	 * Create a new VisitorResolver instance.
	 *
	 * @param DeviceDetector $deviceDetector The device detector service.
	 * @param IpAnonymizer   $ipAnonymizer   The IP anonymizer service.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		protected DeviceDetector $deviceDetector,
		protected IpAnonymizer $ipAnonymizer,
	) {
	}

	/**
	 * Resolve or create a visitor from tracking data.
	 *
	 * @param VisitorData $data    The visitor data.
	 * @param int|null    $siteId  The site ID.
	 *
	 * @return Visitor
	 *
	 * @since 1.0.0
	 */
	public function resolve( VisitorData $data, ?int $siteId = null ): Visitor
	{
		// Try to find existing visitor
		$visitor = $this->findExisting( $data, $siteId );

		if ( null !== $visitor ) {
			return $this->updateVisitor( $visitor, $data );
		}

		return $this->createVisitor( $data, $siteId );
	}

	/**
	 * Resolve a visitor by their ID.
	 *
	 * @param string   $visitorId The visitor ID.
	 * @param int|null $siteId    The site ID.
	 *
	 * @return Visitor|null
	 *
	 * @since 1.0.0
	 */
	public function resolveById( string $visitorId, ?int $siteId = null ): ?Visitor
	{
		$query = Visitor::where( 'id', $visitorId );

		if ( null !== $siteId ) {
			$query->where( 'site_id', $siteId );
		}

		return $query->first();
	}

	/**
	 * Generate a privacy-preserving fingerprint from visitor data.
	 *
	 * The fingerprint is generated from:
	 * - User agent
	 * - Screen resolution
	 * - Timezone
	 * - Language
	 *
	 * This provides a stable identifier without using canvas/WebGL fingerprinting.
	 *
	 * @param VisitorData $data The visitor data.
	 *
	 * @return string|null The fingerprint hash, or null if insufficient data.
	 *
	 * @since 1.0.0
	 */
	public function generateFingerprint( VisitorData $data ): ?string
	{
		$fingerprintData = $data->getFingerprintData();

		if ( empty( $fingerprintData ) ) {
			return null;
		}

		// Sort to ensure consistent ordering
		ksort( $fingerprintData );

		// Create a stable string from the data
		$fingerprintString = json_encode( $fingerprintData );

		if ( false === $fingerprintString ) {
			return null;
		}

		// Generate SHA-256 hash and truncate to 16 characters
		return substr( hash( 'sha256', $fingerprintString ), 0, 16 );
	}

	/**
	 * Increment visitor counters.
	 *
	 * @param Visitor $visitor The visitor.
	 * @param string  $type    The counter type (sessions, pageviews, events).
	 * @param int     $count   The amount to increment.
	 *
	 * @since 1.0.0
	 */
	public function incrementCounter( Visitor $visitor, string $type, int $count = 1 ): void
	{
		$column = match ( $type ) {
			'sessions'  => 'total_sessions',
			'pageviews' => 'total_pageviews',
			'events'    => 'total_events',
			default     => null,
		};

		if ( null !== $column ) {
			$visitor->increment( $column, $count );
		}
	}

	/**
	 * Find an existing visitor by ID or fingerprint.
	 *
	 * @param VisitorData $data   The visitor data.
	 * @param int|null    $siteId The site ID.
	 *
	 * @return Visitor|null
	 *
	 * @since 1.0.0
	 */
	protected function findExisting( VisitorData $data, ?int $siteId = null ): ?Visitor
	{
		// First, try to find by existing ID
		if ( null !== $data->existingId && '' !== $data->existingId ) {
			$visitor = $this->resolveById( $data->existingId, $siteId );

			if ( null !== $visitor ) {
				return $visitor;
			}
		}

		// Then, try to find by fingerprint
		$fingerprint = $this->generateFingerprint( $data );

		if ( null !== $fingerprint ) {
			$query = Visitor::where( 'fingerprint', $fingerprint );

			if ( null !== $siteId ) {
				$query->where( 'site_id', $siteId );
			}

			return $query->first();
		}

		return null;
	}

	/**
	 * Create a new visitor record.
	 *
	 * @param VisitorData $data   The visitor data.
	 * @param int|null    $siteId The site ID.
	 *
	 * @return Visitor
	 *
	 * @since 1.0.0
	 */
	protected function createVisitor( VisitorData $data, ?int $siteId = null ): Visitor
	{
		$deviceInfo = $this->deviceDetector->parse( $data->userAgent );

		// Parse screen resolution if provided
		$screenWidth  = null;
		$screenHeight = null;

		if ( null !== $data->screenResolution ) {
			$parts = explode( 'x', strtolower( $data->screenResolution ) );

			if ( 2 === count( $parts ) ) {
				$screenWidth  = (int) $parts[0];
				$screenHeight = (int) $parts[1];
			}
		}

		return Visitor::create( [
			'site_id'         => $siteId,
			'fingerprint'     => $this->generateFingerprint( $data ),
			'first_seen_at'   => now(),
			'last_seen_at'    => now(),
			'ip_address'      => $this->ipAnonymizer->anonymize( $data->ipAddress ),
			'user_agent'      => $data->userAgent,
			'country'         => $data->country,
			'device_type'     => $data->deviceType ?? $deviceInfo->deviceType,
			'browser'         => $data->browser ?? $deviceInfo->browser,
			'browser_version' => $data->browserVersion ?? $deviceInfo->browserVersion,
			'os'              => $data->os ?? $deviceInfo->os,
			'os_version'      => $data->osVersion ?? $deviceInfo->osVersion,
			'screen_width'    => $screenWidth,
			'screen_height'   => $screenHeight,
			'language'        => $data->language,
			'timezone'        => $data->timezone,
			'total_sessions'  => 0,
			'total_pageviews' => 0,
			'total_events'    => 0,
			'tenant_id'       => $data->tenantId,
		] );
	}

	/**
	 * Update an existing visitor with the latest data.
	 *
	 * @param Visitor     $visitor The existing visitor.
	 * @param VisitorData $data    The visitor data.
	 *
	 * @return Visitor
	 *
	 * @since 1.0.0
	 */
	protected function updateVisitor( Visitor $visitor, VisitorData $data ): Visitor
	{
		$updates = [
			'last_seen_at' => now(),
		];

		// Update IP if anonymization settings allow
		if ( null !== $data->ipAddress ) {
			$updates['ip_address'] = $this->ipAnonymizer->anonymize( $data->ipAddress );
		}

		// Update user agent if provided
		if ( null !== $data->userAgent ) {
			$updates['user_agent'] = $data->userAgent;

			// Re-detect device info if user agent changed
			if ( $data->userAgent !== $visitor->user_agent ) {
				$deviceInfo                 = $this->deviceDetector->parse( $data->userAgent );
				$updates['device_type']     = $deviceInfo->deviceType;
				$updates['browser']         = $deviceInfo->browser;
				$updates['browser_version'] = $deviceInfo->browserVersion;
				$updates['os']              = $deviceInfo->os;
				$updates['os_version']      = $deviceInfo->osVersion;
			}
		}

		// Update other fields if provided
		if ( null !== $data->country && null === $visitor->country ) {
			$updates['country'] = $data->country;
		}

		if ( null !== $data->language ) {
			$updates['language'] = $data->language;
		}

		if ( null !== $data->timezone ) {
			$updates['timezone'] = $data->timezone;
		}

		$visitor->update( $updates );

		return $visitor;
	}
}
