<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Visitor;

/**
 * Data export service for GDPR compliance.
 *
 * Implements GDPR Article 15 (Right of Access) by providing
 * the ability to export all data associated with a visitor.
 *
 * @since   1.0.0
 */
class DataExportService
{
	/**
	 * Export all data for a visitor.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return array<string, mixed> The exported data.
	 *
	 * @since 1.0.0
	 */
	public function exportVisitorData( string $visitorFingerprint ): array
	{
		$visitor = Visitor::with( [
			'sessions.pageViews',
			'sessions.events',
			'consents',
		] )->where( 'fingerprint', $visitorFingerprint )->first();

		if ( null === $visitor ) {
			return [
				'error'       => __( 'Visitor not found.' ),
				'exported_at' => now()->toIso8601String(),
			];
		}

		return [
			'visitor'     => $this->formatVisitor( $visitor ),
			'sessions'    => $this->formatSessions( $visitor ),
			'consents'    => $this->formatConsents( $visitor ),
			'exported_at' => now()->toIso8601String(),
		];
	}

	/**
	 * Export visitor data as CSV.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return string The CSV content.
	 *
	 * @since 1.0.0
	 */
	public function exportAsCsv( string $visitorFingerprint ): string
	{
		$data = $this->exportVisitorData( $visitorFingerprint );

		if ( isset( $data['error'] ) ) {
			return __( 'Error' ) . ": {$data['error']}\n";
		}

		$csv = __( 'Analytics Data Export' ) . "\n";
		$csv .= __( 'Exported' ) . ": {$data['exported_at']}\n\n";

		// Visitor Info
		$csv .= '=== ' . __( 'Visitor Information' ) . " ===\n";
		foreach ( $data['visitor'] as $key => $value ) {
			$csv .= "{$key}," . $this->escapeCsvValue( $value ) . "\n";
		}

		// Sessions
		$csv .= "\n=== " . __( 'Sessions' ) . " ===\n";
		$csv .= implode( ',', [
			__( 'Started' ),
			__( 'Ended' ),
			__( 'Duration (seconds)' ),
			__( 'Entry Page' ),
			__( 'Exit Page' ),
			__( 'Pages Viewed' ),
			__( 'Referrer' ),
		] ) . "\n";

		foreach ( $data['sessions'] as $session ) {
			$csv .= implode( ',', [
				$session['started_at'],
				$session['ended_at'] ?? '',
				$session['duration_seconds'],
				$this->escapeCsvValue( $session['entry_page'] ),
				$this->escapeCsvValue( $session['exit_page'] ?? '' ),
				$session['page_count'],
				$this->escapeCsvValue( $session['referrer'] ?? '' ),
			] ) . "\n";
		}

		// Page Views
		$csv .= "\n=== " . __( 'Page Views' ) . " ===\n";
		$csv .= implode( ',', [
			__( 'Timestamp' ),
			__( 'Path' ),
			__( 'Title' ),
			__( 'Time on Page (seconds)' ),
		] ) . "\n";

		foreach ( $data['sessions'] as $session ) {
			foreach ( $session['page_views'] as $pageView ) {
				$csv .= implode( ',', [
					$pageView['timestamp'],
					$this->escapeCsvValue( $pageView['path'] ),
					$this->escapeCsvValue( $pageView['title'] ?? '' ),
					$pageView['time_on_page'] ?? '',
				] ) . "\n";
			}
		}

		// Events
		$csv .= "\n=== " . __( 'Events' ) . " ===\n";
		$csv .= implode( ',', [
			__( 'Timestamp' ),
			__( 'Name' ),
			__( 'Category' ),
			__( 'Properties' ),
		] ) . "\n";

		foreach ( $data['sessions'] as $session ) {
			foreach ( $session['events'] as $event ) {
				$csv .= implode( ',', [
					$event['timestamp'],
					$this->escapeCsvValue( $event['name'] ),
					$this->escapeCsvValue( $event['category'] ?? '' ),
					$this->escapeCsvValue( json_encode( $event['properties'] ?? [] ) ),
				] ) . "\n";
			}
		}

		// Consents
		$csv .= "\n=== " . __( 'Consent History' ) . " ===\n";
		$csv .= implode( ',', [
			__( 'Category' ),
			__( 'Granted' ),
			__( 'Granted At' ),
			__( 'Revoked At' ),
		] ) . "\n";

		foreach ( $data['consents'] as $consent ) {
			$csv .= implode( ',', [
				$consent['category'],
				$consent['granted'] ? __( 'Yes' ) : __( 'No' ),
				$consent['granted_at'] ?? '',
				$consent['revoked_at'] ?? '',
			] ) . "\n";
		}

		return $csv;
	}

	/**
	 * Export visitor data as JSON.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return string The JSON content.
	 *
	 * @since 1.0.0
	 */
	public function exportAsJson( string $visitorFingerprint ): string
	{
		$data = $this->exportVisitorData( $visitorFingerprint );

		return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Format visitor data for export.
	 *
	 * @param Visitor $visitor The visitor model.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	protected function formatVisitor( Visitor $visitor ): array
	{
		return [
			'id'          => $visitor->fingerprint,
			'first_seen'  => $visitor->first_seen_at?->toIso8601String(),
			'last_seen'   => $visitor->last_seen_at?->toIso8601String(),
			'country'     => $visitor->country,
			'region'      => $visitor->region,
			'city'        => $visitor->city,
			'device_type' => $visitor->device_type,
			'browser'     => $visitor->browser,
			'os'          => $visitor->os,
			'language'    => $visitor->language,
			'timezone'    => $visitor->timezone,
		];
	}

	/**
	 * Format sessions for export.
	 *
	 * @param Visitor $visitor The visitor model.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	protected function formatSessions( Visitor $visitor ): array
	{
		return $visitor->sessions->map( function ( $session ) {
			return [
				'started_at'       => $session->started_at?->toIso8601String(),
				'ended_at'         => $session->ended_at?->toIso8601String(),
				'duration_seconds' => $session->duration,
				'entry_page'       => $session->entry_page,
				'exit_page'        => $session->exit_page,
				'page_count'       => $session->page_count,
				'referrer'         => $session->referrer,
				'utm'              => [
					'source'   => $session->utm_source,
					'medium'   => $session->utm_medium,
					'campaign' => $session->utm_campaign,
					'term'     => $session->utm_term,
					'content'  => $session->utm_content,
				],
				'page_views' => $session->pageViews->map( fn ( $pv ) => [
					'path'         => $pv->path,
					'title'        => $pv->title,
					'time_on_page' => $pv->time_on_page,
					'timestamp'    => $pv->created_at?->toIso8601String(),
				] )->toArray(),
				'events' => $session->events->map( fn ( $e ) => [
					'name'       => $e->name,
					'category'   => $e->category,
					'properties' => $e->properties,
					'value'      => $e->value,
					'timestamp'  => $e->created_at?->toIso8601String(),
				] )->toArray(),
			];
		} )->toArray();
	}

	/**
	 * Format consents for export.
	 *
	 * @param Visitor $visitor The visitor model.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	protected function formatConsents( Visitor $visitor ): array
	{
		return $visitor->consents->map( fn ( $c ) => [
			'category'   => $c->category,
			'granted'    => $c->granted,
			'granted_at' => $c->granted_at?->toIso8601String(),
			'revoked_at' => $c->revoked_at?->toIso8601String(),
			'expires_at' => $c->expires_at?->toIso8601String(),
		] )->toArray();
	}

	/**
	 * Escape a value for CSV.
	 *
	 * @param mixed $value The value to escape.
	 *
	 * @return string The escaped value.
	 *
	 * @since 1.0.0
	 */
	protected function escapeCsvValue( mixed $value ): string
	{
		if ( null === $value ) {
			return '';
		}

		$value = (string) $value;

		// If value contains comma, quote, or newline, wrap in quotes and escape quotes
		if ( preg_match( '/[,"\n\r]/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
