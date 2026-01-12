<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Facades;

use ArtisanPackUI\Analytics\Analytics as AnalyticsManager;
use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Data\VisitorData;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Analytics facade.
 *
 * @method static void trackPageView(PageViewData $data)
 * @method static void trackEvent(EventData $data)
 * @method static void event(string $name, array $properties = [], ?string $category = null, ?float $value = null, ?string $sourcePackage = null)
 * @method static Session startSession(SessionData $data)
 * @method static void endSession(string $sessionId)
 * @method static void extendSession(string $sessionId)
 * @method static Visitor resolveVisitor(VisitorData $data)
 * @method static bool canTrack()
 * @method static AnalyticsManager extend(string $name, callable $creator)
 * @method static AnalyticsProviderInterface provider(?string $name = null)
 * @method static Collection getActiveProviders()
 * @method static string getDefaultProvider()
 * @method static AnalyticsManager setDefaultProvider(string $name)
 * @method static array getProviderNames()
 *
 * @see     AnalyticsManager
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Facades
 */
class Analytics extends Facade
{
    /**
     * Get the registered name of the component.
     *
     *
     * @since 1.0.0
     */
    protected static function getFacadeAccessor(): string
    {
        return 'analytics';
    }
}
