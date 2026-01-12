<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Consent;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Http\Request;

/**
 * Consent management service.
 *
 * Handles checking, granting, and revoking user consent for analytics tracking.
 * Integrates with the future privacy package when available.
 *
 * @since   1.0.0
 */
class ConsentService
{
	/**
	 * The IP anonymizer service.
	 */
	protected IpAnonymizer $ipAnonymizer;

	/**
	 * Create a new ConsentService instance.
	 *
	 * @param IpAnonymizer $ipAnonymizer The IP anonymizer service.
	 *
	 * @since 1.0.0
	 */
	public function __construct( IpAnonymizer $ipAnonymizer )
	{
		$this->ipAnonymizer = $ipAnonymizer;
	}

	/**
	 * Check if a visitor has active consent for a category.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 * @param string $category           The consent category.
	 *
	 * @return bool True if consent is granted.
	 *
	 * @since 1.0.0
	 */
	public function hasConsent( string $visitorFingerprint, string $category = 'analytics' ): bool
	{
		// Check with future privacy package first
		if ( $this->hasPrivacyPackage() ) {
			return app( 'privacy' )->hasConsent( $category );
		}

		// If consent not required, always allow
		if ( ! config( 'artisanpack.analytics.privacy.consent_required', false ) ) {
			return true;
		}

		// Check for DNT header
		if ( $this->shouldRespectDnt() && $this->isDntEnabled() ) {
			return false;
		}

		// Check stored consent
		$visitor = Visitor::query()->where( 'fingerprint', $visitorFingerprint )->first();

		if ( null === $visitor ) {
			return false;
		}

		return Consent::query()
			->where( 'visitor_id', $visitor->id )
			->forCategory( $category )
			->active()
			->exists();
	}

	/**
	 * Grant consent for specified categories.
	 *
	 * @param string        $visitorFingerprint The visitor's fingerprint.
	 * @param array<string> $categories         The categories to grant consent for.
	 * @param Request|null  $request            The HTTP request for IP/UA logging.
	 *
	 * @return Visitor The visitor record.
	 *
	 * @since 1.0.0
	 */
	public function grantConsent(
		string $visitorFingerprint,
		array $categories,
		?Request $request = null,
	): Visitor {
		$visitor = $this->resolveOrCreateVisitor( $visitorFingerprint );

		$expiresAt = now()->addDays(
			config( 'artisanpack.analytics.privacy.consent_cookie_lifetime', 365 ),
		);

		foreach ( $categories as $category ) {
			Consent::updateOrCreate(
				[
					'visitor_id' => $visitor->id,
					'category'   => $category,
				],
				[
					'site_id'    => $this->getSiteId(),
					'granted'    => true,
					'granted_at' => now(),
					'revoked_at' => null,
					'expires_at' => $expiresAt,
					'ip_address' => $request ? $this->ipAnonymizer->anonymize( $request->ip() ) : null,
					'user_agent' => $request?->userAgent(),
					'tenant_id'  => $this->getTenantId(),
				],
			);
		}

		return $visitor;
	}

	/**
	 * Revoke consent for specified categories.
	 *
	 * @param string        $visitorFingerprint The visitor's fingerprint.
	 * @param array<string> $categories         The categories to revoke consent for.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function revokeConsent( string $visitorFingerprint, array $categories ): void
	{
		$visitor = Visitor::query()->where( 'fingerprint', $visitorFingerprint )->first();

		if ( null === $visitor ) {
			return;
		}

		Consent::query()
			->where( 'visitor_id', $visitor->id )
			->whereIn( 'category', $categories )
			->active()
			->each( fn ( Consent $consent ) => $consent->revoke() );
	}

	/**
	 * Get the consent status for a visitor.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return array<string, array<string, mixed>> The consent status by category.
	 *
	 * @since 1.0.0
	 */
	public function getConsentStatus( string $visitorFingerprint ): array
	{
		$configuredCategories = config( 'artisanpack.analytics.privacy.consent_categories', [] );

		$visitor = Visitor::query()->where( 'fingerprint', $visitorFingerprint )->first();

		$activeConsents = [];
		if ( null !== $visitor ) {
			$activeConsents = Consent::query()
				->where( 'visitor_id', $visitor->id )
				->active()
				->get()
				->keyBy( 'category' );
		}

		$status = [];
		foreach ( $configuredCategories as $key => $category ) {
			$consent = $activeConsents[ $key ] ?? null;

			$status[ $key ] = [
				'name'        => __( $category['name'] ?? $key ),
				'description' => __( $category['description'] ?? '' ),
				'required'    => $category['required'] ?? false,
				'granted'     => null !== $consent,
				'granted_at'  => $consent?->granted_at?->toIso8601String(),
			];
		}

		return $status;
	}

	/**
	 * Revoke all consents for a visitor.
	 *
	 * @param string $visitorFingerprint The visitor's fingerprint.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function revokeAllConsents( string $visitorFingerprint ): void
	{
		$visitor = Visitor::query()->where( 'fingerprint', $visitorFingerprint )->first();

		if ( null === $visitor ) {
			return;
		}

		Consent::query()
			->where( 'visitor_id', $visitor->id )
			->active()
			->each( fn ( Consent $consent ) => $consent->revoke() );
	}

	/**
	 * Check if the privacy package is available.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function hasPrivacyPackage(): bool
	{
		return app()->bound( 'privacy' );
	}

	/**
	 * Check if DNT should be respected.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function shouldRespectDnt(): bool
	{
		return config( 'artisanpack.analytics.privacy.respect_dnt', true );
	}

	/**
	 * Check if DNT header is enabled in the current request.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function isDntEnabled(): bool
	{
		$dnt = request()->header( 'DNT' ) ?? request()->header( 'Sec-GPC' );

		return '1' === $dnt;
	}

	/**
	 * Resolve or create a visitor by fingerprint.
	 *
	 * @param string $fingerprint The visitor's fingerprint.
	 *
	 * @return Visitor The visitor record.
	 *
	 * @since 1.0.0
	 */
	protected function resolveOrCreateVisitor( string $fingerprint ): Visitor
	{
		return Visitor::firstOrCreate(
			[ 'fingerprint' => $fingerprint ],
			[
				'first_seen_at' => now(),
				'last_seen_at'  => now(),
				'site_id'       => $this->getSiteId(),
				'tenant_id'     => $this->getTenantId(),
			],
		);
	}

	/**
	 * Get the current site ID.
	 *
	 * @return int|null
	 *
	 * @since 1.0.0
	 */
	protected function getSiteId(): ?int
	{
		if ( ! config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return null;
		}

		return app()->bound( 'analytics.site' ) ? app( 'analytics.site' )?->id : null;
	}

	/**
	 * Get the current tenant ID.
	 *
	 * @return int|string|null
	 *
	 * @since 1.0.0
	 */
	protected function getTenantId(): int|string|null
	{
		if ( ! config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return null;
		}

		return app()->bound( 'analytics.tenant' ) ? app( 'analytics.tenant' ) : null;
	}
}
