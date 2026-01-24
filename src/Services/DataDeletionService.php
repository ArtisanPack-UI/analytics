<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Data deletion service for GDPR compliance.
 *
 * Implements GDPR Article 17 (Right to Erasure) by providing
 * the ability to delete or anonymize all data associated with a visitor.
 *
 * @since   1.0.0
 */
class DataDeletionService
{
	/**
	 * Delete all data for a visitor.
	 *
	 * This method performs a cascade deletion of all visitor data
	 * in the correct order to respect foreign key constraints.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return array<string, mixed> The deletion result.
	 *
	 * @since 1.0.0
	 */
	public function deleteVisitorData( string $visitorFingerprint ): array
	{
		$visitor = Visitor::query()->where( 'fingerprint', $visitorFingerprint )->first();

		if ( null === $visitor ) {
			Log::info( __( '[Analytics] Deletion request for non-existent visitor: :fingerprint', [
				'fingerprint' => substr( $visitorFingerprint, 0, 8 ) . '...',
			] ) );

			return [
				'success'    => false,
				'error'      => __( 'Visitor not found.' ),
				'deleted_at' => now()->toIso8601String(),
			];
		}

		$counts = [
			'page_views'  => 0,
			'events'      => 0,
			'conversions' => 0,
			'sessions'    => 0,
			'consents'    => 0,
		];

		try {
			DB::transaction( function () use ( $visitor, &$counts ): void {
				// Get session IDs for the visitor
				$sessionIds = Session::query()
					->where( 'visitor_id', $visitor->id )
					->pluck( 'id' );

				// Delete page views
				$counts['page_views'] = PageView::query()
					->whereIn( 'session_id', $sessionIds )
					->delete();

				// Delete events
				$counts['events'] = Event::query()
					->whereIn( 'session_id', $sessionIds )
					->delete();

				// Delete conversions
				$counts['conversions'] = Conversion::query()
					->whereIn( 'session_id', $sessionIds )
					->delete();

				// Delete sessions
				$counts['sessions'] = Session::query()
					->where( 'visitor_id', $visitor->id )
					->delete();

				// Delete consents
				$counts['consents'] = Consent::query()
					->where( 'visitor_id', $visitor->id )
					->delete();

				// Delete the visitor record
				$visitor->delete();
			} );

			Log::info( __( '[Analytics] Deleted all data for visitor: :fingerprint', [
				'fingerprint' => substr( $visitorFingerprint, 0, 8 ) . '...',
			] ), $counts );

			return [
				'success'    => true,
				'deleted'    => $counts,
				'deleted_at' => now()->toIso8601String(),
			];
		} catch ( Throwable $e ) {
			Log::error( __( '[Analytics] Failed to delete visitor data: :message', [
				'message' => $e->getMessage(),
			] ) );

			return [
				'success'    => false,
				'error'      => __( 'Failed to delete visitor data.' ),
				'deleted_at' => now()->toIso8601String(),
			];
		}
	}

	/**
	 * Anonymize all data for a visitor.
	 *
	 * This method anonymizes visitor data instead of deleting it,
	 * which may be preferred for maintaining aggregate statistics
	 * while removing personally identifiable information.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return array<string, mixed> The anonymization result.
	 *
	 * @since 1.0.0
	 */
	public function anonymizeVisitorData( string $visitorFingerprint ): array
	{
		$visitor = Visitor::query()->where( 'fingerprint', $visitorFingerprint )->first();

		if ( null === $visitor ) {
			Log::info( __( '[Analytics] Anonymization request for non-existent visitor: :fingerprint', [
				'fingerprint' => substr( $visitorFingerprint, 0, 8 ) . '...',
			] ) );

			return [
				'success'       => false,
				'error'         => __( 'Visitor not found.' ),
				'anonymized_at' => now()->toIso8601String(),
			];
		}

		$counts = [
			'sessions_anonymized' => 0,
			'consents_deleted'    => 0,
		];

		try {
			DB::transaction( function () use ( $visitor, &$counts ): void {
				// Generate a new anonymous fingerprint
				$anonymousFingerprint = 'anonymous_' . bin2hex( random_bytes( 16 ) );

				// Anonymize visitor record
				$visitor->update( [
					'fingerprint'     => $anonymousFingerprint,
					'ip_address'      => null,
					'user_agent'      => null,
					'country'         => null,
					'region'          => null,
					'city'            => null,
					'browser'         => null,
					'browser_version' => null,
					'os'              => null,
					'os_version'      => null,
					'device_type'     => 'other', // Reset to default (NOT NULL)
					'screen_width'    => null,
					'screen_height'   => null,
					'viewport_width'  => null,
					'viewport_height' => null,
					'language'        => null,
					'timezone'        => null,
				] );

				// Anonymize sessions (remove referrer and UTM tracking data)
				$counts['sessions_anonymized'] = Session::query()
					->where( 'visitor_id', $visitor->id )
					->update( [
						'referrer'        => null,
						'referrer_domain' => null,
						'utm_source'      => null,
						'utm_medium'      => null,
						'utm_campaign'    => null,
						'utm_term'        => null,
						'utm_content'     => null,
					] );

				// Delete consent records (they contain IP/UA and are no longer valid)
				$counts['consents_deleted'] = Consent::query()
					->where( 'visitor_id', $visitor->id )
					->delete();
			} );

			Log::info( __( '[Analytics] Anonymized all data for visitor: :fingerprint', [
				'fingerprint' => substr( $visitorFingerprint, 0, 8 ) . '...',
			] ), $counts );

			return [
				'success'       => true,
				'anonymized'    => $counts,
				'anonymized_at' => now()->toIso8601String(),
			];
		} catch ( Throwable $e ) {
			Log::error( __( '[Analytics] Failed to anonymize visitor data: :message', [
				'message' => $e->getMessage(),
			] ) );

			return [
				'success'       => false,
				'error'         => __( 'Failed to anonymize visitor data.' ),
				'anonymized_at' => now()->toIso8601String(),
			];
		}
	}

	/**
	 * Delete data for multiple visitors.
	 *
	 * @param array<string> $visitorFingerprints The visitor fingerprints.
	 *
	 * @return array<string, mixed> The deletion results.
	 *
	 * @since 1.0.0
	 */
	public function deleteMultipleVisitors( array $visitorFingerprints ): array
	{
		$results = [
			'total'      => count( $visitorFingerprints ),
			'successful' => 0,
			'failed'     => 0,
			'details'    => [],
		];

		foreach ( $visitorFingerprints as $fingerprint ) {
			$result                             = $this->deleteVisitorData( $fingerprint );
			$results['details'][ $fingerprint ] = $result;

			if ( $result['success'] ) {
				$results['successful']++;
			} else {
				$results['failed']++;
			}
		}

		return $results;
	}

	/**
	 * Check if a visitor has any data.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return bool True if the visitor has data.
	 *
	 * @since 1.0.0
	 */
	public function hasVisitorData( string $visitorFingerprint ): bool
	{
		return Visitor::query()->where( 'fingerprint', $visitorFingerprint )->exists();
	}

	/**
	 * Get a summary of data to be deleted for a visitor.
	 *
	 * Useful for showing the user what will be deleted before confirmation.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return array<string, mixed> The data summary.
	 *
	 * @since 1.0.0
	 */
	public function getDataSummary( string $visitorFingerprint ): array
	{
		$visitor = Visitor::query()->where( 'fingerprint', $visitorFingerprint )->first();

		if ( null === $visitor ) {
			return [
				'exists' => false,
			];
		}

		$sessionIds = Session::query()
			->where( 'visitor_id', $visitor->id )
			->pluck( 'id' );

		return [
			'exists'           => true,
			'first_seen'       => $visitor->first_seen_at?->toIso8601String(),
			'last_seen'        => $visitor->last_seen_at?->toIso8601String(),
			'session_count'    => Session::query()->where( 'visitor_id', $visitor->id )->count(),
			'page_view_count'  => PageView::query()->whereIn( 'session_id', $sessionIds )->count(),
			'event_count'      => Event::query()->whereIn( 'session_id', $sessionIds )->count(),
			'conversion_count' => Conversion::query()->whereIn( 'session_id', $sessionIds )->count(),
			'consent_count'    => Consent::query()->where( 'visitor_id', $visitor->id )->count(),
		];
	}
}
