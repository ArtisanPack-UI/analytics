<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Jobs;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Providers\LocalAnalyticsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Process batch tracking data job.
 *
 * Handles queued batch processing of multiple tracking items
 * for high-volume scenarios.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Jobs
 */
class ProcessBatchTracking implements ShouldQueue
{
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public int $tries = 3;

	/**
	 * The number of seconds to wait before retrying the job.
	 *
	 * @var int
	 */
	public int $backoff = 10;

	/**
	 * Create a new job instance.
	 *
	 * @param array<int, array<string, mixed>> $items     The batch items to process.
	 * @param string|null                      $ipAddress The visitor's IP address.
	 * @param string|null                      $userAgent The visitor's user agent.
	 * @param int|string|null                  $tenantId  The tenant identifier.
	 * @param int|null                         $siteId    The site identifier.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public array $items,
		public ?string $ipAddress = null,
		public ?string $userAgent = null,
		public string|int|null $tenantId = null,
		public ?int $siteId = null,
	) {
	}

	/**
	 * Execute the job.
	 *
	 * @param LocalAnalyticsProvider $provider The analytics provider.
	 *
	 * @since 1.0.0
	 */
	public function handle( LocalAnalyticsProvider $provider ): void
	{
		foreach ( $this->items as $item ) {
			try {
				$this->processItem( $item, $provider );
			} catch ( Throwable $e ) {
				Log::warning( 'Failed to process batch item', [
					'error' => $e->getMessage(),
					'type'  => $item['type'] ?? 'unknown',
				] );
			}
		}
	}

	/**
	 * Get the tags that should be assigned to the job.
	 *
	 * @return array<int, string>
	 *
	 * @since 1.0.0
	 */
	public function tags(): array
	{
		return [
			'analytics',
			'batch',
			'items:' . count( $this->items ),
		];
	}

	/**
	 * Process a single batch item.
	 *
	 * @param array<string, mixed>   $item     The item data.
	 * @param LocalAnalyticsProvider $provider The analytics provider.
	 *
	 * @since 1.0.0
	 */
	protected function processItem( array $item, LocalAnalyticsProvider $provider ): void
	{
		$type = $item['type'] ?? '';
		$data = $item['data'] ?? [];

		// Merge in common data
		$data['ip_address'] = $data['ip_address'] ?? $this->ipAddress;
		$data['user_agent'] = $data['user_agent'] ?? $this->userAgent;
		$data['tenant_id']  = $data['tenant_id'] ?? $this->tenantId;
		$data['site_id']    = $data['site_id'] ?? $this->siteId;

		switch ( $type ) {
			case 'pageview':
				$pageViewData = $this->createPageViewData( $data );
				$provider->storePageView( $pageViewData, $this->siteId );
				break;

			case 'event':
				$eventData = $this->createEventData( $data );
				$provider->storeEvent( $eventData, $this->siteId );
				break;

			default:
				// Log without PII - only include safe metadata
				Log::warning( 'Unknown batch item type', [
					'type'       => $type,
					'has_path'   => isset( $data['path'] ),
					'has_name'   => isset( $data['name'] ),
					'data_keys'  => array_keys( $data ),
				] );
				break;
		}
	}

	/**
	 * Create PageViewData from array.
	 *
	 * @param array<string, mixed> $data The raw data.
	 *
	 * @return PageViewData
	 *
	 * @since 1.0.0
	 */
	protected function createPageViewData( array $data ): PageViewData
	{
		return new PageViewData(
			path: $this->castToString( $data['path'] ?? '/' ),
			title: $this->castToStringOrNull( $data['title'] ?? null ),
			referrer: $this->castToStringOrNull( $data['referrer'] ?? null ),
			sessionId: $this->castToStringOrNull( $data['session_id'] ?? null ),
			visitorId: $this->castToStringOrNull( $data['visitor_id'] ?? null ),
			ipAddress: $this->castToStringOrNull( $data['ip_address'] ?? null ),
			userAgent: $this->castToStringOrNull( $data['user_agent'] ?? null ),
			country: $this->castToStringOrNull( $data['country'] ?? null ),
			deviceType: $this->castToStringOrNull( $data['device_type'] ?? null ),
			browser: $this->castToStringOrNull( $data['browser'] ?? null ),
			browserVersion: $this->castToStringOrNull( $data['browser_version'] ?? null ),
			os: $this->castToStringOrNull( $data['os'] ?? null ),
			osVersion: $this->castToStringOrNull( $data['os_version'] ?? null ),
			screenWidth: $this->castToStringOrNull( $data['screen_width'] ?? null ),
			screenHeight: $this->castToStringOrNull( $data['screen_height'] ?? null ),
			viewportWidth: $this->castToStringOrNull( $data['viewport_width'] ?? null ),
			viewportHeight: $this->castToStringOrNull( $data['viewport_height'] ?? null ),
			utmSource: $this->castToStringOrNull( $data['utm_source'] ?? null ),
			utmMedium: $this->castToStringOrNull( $data['utm_medium'] ?? null ),
			utmCampaign: $this->castToStringOrNull( $data['utm_campaign'] ?? null ),
			utmTerm: $this->castToStringOrNull( $data['utm_term'] ?? null ),
			utmContent: $this->castToStringOrNull( $data['utm_content'] ?? null ),
			loadTime: $this->castToFloatOrNull( $data['load_time'] ?? null ),
			customData: is_array( $data['custom_data'] ?? null ) ? $data['custom_data'] : null,
			tenantId: $data['tenant_id'] ?? null,
		);
	}

	/**
	 * Cast a value to string or return null if null/empty array.
	 *
	 * @param mixed $value The value to cast.
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	protected function castToStringOrNull( mixed $value ): ?string
	{
		if ( null === $value || is_array( $value ) ) {
			return null;
		}

		return (string) $value;
	}

	/**
	 * Cast a value to string.
	 *
	 * @param mixed $value The value to cast.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function castToString( mixed $value ): string
	{
		if ( is_array( $value ) ) {
			return '';
		}

		return (string) $value;
	}

	/**
	 * Cast a value to int or return null if null/invalid.
	 *
	 * @param mixed $value The value to cast.
	 *
	 * @return int|null
	 *
	 * @since 1.0.0
	 */
	protected function castToIntOrNull( mixed $value ): ?int
	{
		if ( null === $value || is_array( $value ) ) {
			return null;
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return (int) $value;
	}

	/**
	 * Cast a value to float or return null if null/invalid.
	 *
	 * Only casts values that are legitimately numeric to avoid
	 * converting invalid strings to 0.0.
	 *
	 * @param mixed $value The value to cast.
	 *
	 * @return float|null
	 *
	 * @since 1.0.0
	 */
	protected function castToFloatOrNull( mixed $value ): ?float
	{
		if ( null === $value || is_array( $value ) ) {
			return null;
		}

		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return (float) $value;
	}

	/**
	 * Create EventData from array.
	 *
	 * @param array<string, mixed> $data The raw data.
	 *
	 * @return EventData
	 *
	 * @since 1.0.0
	 */
	protected function createEventData( array $data ): EventData
	{
		return new EventData(
			name: $this->castToString( $data['name'] ?? '' ),
			properties: is_array( $data['properties'] ?? null ) ? $data['properties'] : null,
			sessionId: $this->castToStringOrNull( $data['session_id'] ?? null ),
			visitorId: $this->castToStringOrNull( $data['visitor_id'] ?? null ),
			path: $this->castToStringOrNull( $data['path'] ?? null ),
			ipAddress: $this->castToStringOrNull( $data['ip_address'] ?? null ),
			userAgent: $this->castToStringOrNull( $data['user_agent'] ?? null ),
			value: $this->castToFloatOrNull( $data['value'] ?? null ),
			category: $this->castToStringOrNull( $data['category'] ?? null ),
			tenantId: $data['tenant_id'] ?? null,
		);
	}
}
