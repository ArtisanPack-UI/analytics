<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use Illuminate\Support\Facades\Log;

/**
 * Privacy package integration service.
 *
 * Provides integration hooks for the future ArtisanPack UI Privacy package.
 * This service registers filter and action hooks that allow the privacy
 * package to trigger data export, deletion, and consent management
 * operations in the analytics package.
 *
 * @since   1.0.0
 */
class PrivacyIntegration
{
	/**
	 * The data export service.
	 */
	protected DataExportService $exportService;

	/**
	 * The data deletion service.
	 */
	protected DataDeletionService $deletionService;

	/**
	 * The consent service.
	 */
	protected ConsentService $consentService;

	/**
	 * Create a new PrivacyIntegration instance.
	 *
	 * @param DataExportService   $exportService   The data export service.
	 * @param DataDeletionService $deletionService The data deletion service.
	 * @param ConsentService      $consentService  The consent service.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		DataExportService $exportService,
		DataDeletionService $deletionService,
		ConsentService $consentService,
	) {
		$this->exportService   = $exportService;
		$this->deletionService = $deletionService;
		$this->consentService  = $consentService;
	}

	/**
	 * Register all privacy integration hooks.
	 *
	 * This method should be called during service provider boot
	 * to register all hooks for privacy package integration.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function register(): void
	{
		// Only register hooks if the hooks package is available
		if ( ! function_exists( 'addFilter' ) || ! function_exists( 'addAction' ) ) {
			return;
		}

		$this->registerExportHooks();
		$this->registerDeletionHooks();
		$this->registerConsentHooks();
	}

	/**
	 * Check if the privacy package is available.
	 *
	 * @return bool True if the privacy package is installed.
	 *
	 * @since 1.0.0
	 */
	public function isPrivacyPackageAvailable(): bool
	{
		return app()->bound( 'privacy' );
	}

	/**
	 * Register data export hooks.
	 *
	 * These hooks allow the privacy package to request
	 * analytics data for GDPR Article 15 (Right of Access).
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function registerExportHooks(): void
	{
		// Filter to add analytics data to privacy export
		addFilter( 'privacy.export-data', function ( array $exportData, string $identifier ) {
			$analyticsData = $this->exportService->exportVisitorData( $identifier );

			if ( ! isset( $analyticsData['error'] ) ) {
				$exportData['analytics'] = $analyticsData;
			}

			return $exportData;
		}, 10 );

		// Filter to provide export formats
		addFilter( 'privacy.export-formats', function ( array $formats ) {
			$formats['analytics_csv']  = __( 'Analytics Data (CSV)' );
			$formats['analytics_json'] = __( 'Analytics Data (JSON)' );

			return $formats;
		}, 10 );

		// Action to handle format-specific exports
		addAction( 'privacy.export-format', function ( string $format, string $identifier ) {
			if ( 'analytics_csv' === $format ) {
				return $this->exportService->exportAsCsv( $identifier );
			}

			if ( 'analytics_json' === $format ) {
				return $this->exportService->exportAsJson( $identifier );
			}

			return null;
		}, 10 );
	}

	/**
	 * Register data deletion hooks.
	 *
	 * These hooks allow the privacy package to trigger
	 * analytics data deletion for GDPR Article 17 (Right to Erasure).
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function registerDeletionHooks(): void
	{
		// Action to delete analytics data
		addAction( 'privacy.delete-data', function ( string $identifier ): void {
			$result = $this->deletionService->deleteVisitorData( $identifier );

			if ( $result['success'] ) {
				Log::info( __( '[Analytics] Privacy package triggered data deletion for: :identifier', [
					'identifier' => substr( $identifier, 0, 8 ) . '...',
				] ) );
			}
		}, 10 );

		// Action to anonymize analytics data (alternative to deletion)
		addAction( 'privacy.anonymize-data', function ( string $identifier ): void {
			$result = $this->deletionService->anonymizeVisitorData( $identifier );

			if ( $result['success'] ) {
				Log::info( __( '[Analytics] Privacy package triggered data anonymization for: :identifier', [
					'identifier' => substr( $identifier, 0, 8 ) . '...',
				] ) );
			}
		}, 10 );

		// Filter to check if visitor has data
		addFilter( 'privacy.has-data', function ( bool $hasData, string $identifier ) {
			if ( $this->deletionService->hasVisitorData( $identifier ) ) {
				return true;
			}

			return $hasData;
		}, 10 );

		// Filter to get data summary
		addFilter( 'privacy.data-summary', function ( array $summary, string $identifier ) {
			$analyticsSummary = $this->deletionService->getDataSummary( $identifier );

			if ( $analyticsSummary['exists'] ) {
				$summary['analytics'] = $analyticsSummary;
			}

			return $summary;
		}, 10 );
	}

	/**
	 * Register consent management hooks.
	 *
	 * These hooks allow the privacy package to manage
	 * consent preferences across all ArtisanPack UI packages.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function registerConsentHooks(): void
	{
		// Action when consent is granted via privacy package
		addAction( 'privacy.consent-granted', function ( string $identifier, array $categories ): void {
			$this->consentService->grantConsent( $identifier, $categories );

			Log::info( __( '[Analytics] Privacy package granted consent for: :identifier', [
				'identifier' => substr( $identifier, 0, 8 ) . '...',
			] ), [ 'categories' => $categories ] );
		}, 10 );

		// Action when consent is revoked via privacy package
		addAction( 'privacy.consent-revoked', function ( string $identifier, array $categories ): void {
			$this->consentService->revokeConsent( $identifier, $categories );

			Log::info( __( '[Analytics] Privacy package revoked consent for: :identifier', [
				'identifier' => substr( $identifier, 0, 8 ) . '...',
			] ), [ 'categories' => $categories ] );
		}, 10 );

		// Filter to get consent status
		addFilter( 'privacy.consent-status', function ( array $status, string $identifier ) {
			$analyticsStatus = $this->consentService->getConsentStatus( $identifier );

			foreach ( $analyticsStatus as $category => $categoryStatus ) {
				$status[ 'analytics_' . $category ] = $categoryStatus;
			}

			return $status;
		}, 10 );

		// Filter to provide available consent categories
		addFilter( 'privacy.consent-categories', function ( array $categories ) {
			$analyticsCategories = config( 'artisanpack.analytics.privacy.consent_categories', [] );

			foreach ( $analyticsCategories as $key => $category ) {
				$categories[ 'analytics_' . $key ] = [
					'name'        => __( $category['name'] ?? $key ),
					'description' => __( $category['description'] ?? '' ),
					'required'    => $category['required'] ?? false,
					'package'     => 'analytics',
				];
			}

			return $categories;
		}, 10 );
	}
}
