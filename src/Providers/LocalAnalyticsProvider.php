<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Jobs\ProcessEvent;
use ArtisanPackUI\Analytics\Jobs\ProcessPageView;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;

/**
 * Local database analytics provider.
 *
 * This provider stores all analytics data in your local database,
 * providing complete privacy and control over your data.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Providers
 */
class LocalAnalyticsProvider implements AnalyticsProviderInterface
{
	/**
	 * The provider configuration.
	 *
	 * @var array<string, mixed>
	 */
	protected array $config;

	/**
	 * Create a new LocalAnalyticsProvider instance.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$this->config = config( 'artisanpack.analytics.local', [] );
	}

	/**
	 * Track a page view.
	 *
	 * @param PageViewData $data The page view data.
	 *
	 * @since 1.0.0
	 */
	public function trackPageView( PageViewData $data ): void
	{
		if ( ! $this->isEnabled() ) {
			return;
		}

		if ( $this->shouldQueue() ) {
			ProcessPageView::dispatch( $data )
				->onQueue( $this->getQueueName() );

			return;
		}

		$this->storePageView( $data );
	}

	/**
	 * Track a custom event.
	 *
	 * @param EventData $data The event data.
	 *
	 * @since 1.0.0
	 */
	public function trackEvent( EventData $data ): void
	{
		if ( ! $this->isEnabled() ) {
			return;
		}

		if ( $this->shouldQueue() ) {
			ProcessEvent::dispatch( $data )
				->onQueue( $this->getQueueName() );

			return;
		}

		$this->storeEvent( $data );
	}

	/**
	 * Check if this provider is enabled.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isEnabled(): bool
	{
		return $this->config['enabled'] ?? true;
	}

	/**
	 * Get the provider's unique name.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function getName(): string
	{
		return 'local';
	}

	/**
	 * Get the provider's configuration.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * Store a page view in the database.
	 *
	 * Only page-specific data is stored here. Visitor attributes (ipAddress, userAgent,
	 * browser, os, deviceType, screenWidth, screenHeight, viewportWidth, viewportHeight)
	 * and UTM parameters are intentionally excluded from the page_views table because
	 * they are stored in the related analytics_visitors and analytics_sessions tables.
	 * This normalized design avoids data duplication and allows efficient querying.
	 *
	 * @param PageViewData $data The page view data.
	 *
	 * @since 1.0.0
	 */
	public function storePageView( PageViewData $data ): void
	{
		PageView::create( [
			'session_id'  => $data->sessionId,
			'visitor_id'  => $data->visitorId,
			'path'        => $data->path,
			'title'       => $data->title,
			'referrer'    => $data->referrer,
			'load_time'   => $data->loadTime,
			'custom_data' => $data->customData,
			'tenant_id'   => $data->tenantId,
		] );
	}

	/**
	 * Store an event in the database.
	 *
	 * Only event-specific data is stored here. Visitor attributes (ipAddress, userAgent,
	 * browser, os, deviceType) are intentionally excluded from the events table because
	 * they are stored in the related analytics_visitors table. This normalized design
	 * avoids data duplication and allows efficient querying.
	 *
	 * @param EventData $data The event data.
	 *
	 * @since 1.0.0
	 */
	public function storeEvent( EventData $data ): void
	{
		Event::create( [
			'session_id' => $data->sessionId,
			'visitor_id' => $data->visitorId,
			'name'       => $data->name,
			'category'   => $data->category,
			'properties' => $data->properties,
			'value'      => $data->value,
			'path'       => $data->path,
			'tenant_id'  => $data->tenantId,
		] );
	}

	/**
	 * Check if processing should be queued.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function shouldQueue(): bool
	{
		return $this->config['queue_processing'] ?? true;
	}

	/**
	 * Get the queue name to use.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function getQueueName(): string
	{
		return $this->config['queue_name'] ?? 'analytics';
	}
}
