<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Facades;

use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Services\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * Consent facade.
 *
 * Provides a static interface to the ConsentService for
 * managing user consent for analytics tracking.
 *
 * @method static bool hasConsent(string $visitorFingerprint, string $category = 'analytics')
 * @method static Visitor grantConsent(string $visitorFingerprint, array $categories, ?Request $request = null)
 * @method static void revokeConsent(string $visitorFingerprint, array $categories)
 * @method static array getConsentStatus(string $visitorFingerprint)
 * @method static void revokeAllConsents(string $visitorFingerprint)
 *
 * @see ConsentService
 * @since   1.0.0
 */
class Consent extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected static function getFacadeAccessor(): string
	{
		return ConsentService::class;
	}
}
