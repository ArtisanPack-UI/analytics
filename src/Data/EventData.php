<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Data;

use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Data Transfer Object for custom event tracking.
 *
 * Encapsulates all data related to a custom event.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Data
 */
readonly class EventData
{
	/**
	 * Create a new EventData instance.
	 *
	 * @param string                    $name        The event name.
	 * @param array<string, mixed>|null $properties  Custom properties for the event.
	 * @param string|null               $sessionId   The session identifier.
	 * @param string|null               $visitorId   The visitor identifier.
	 * @param string|null               $path        The page path where the event occurred.
	 * @param string|null               $ipAddress   The visitor's IP address.
	 * @param string|null               $userAgent   The visitor's user agent.
	 * @param float|null                $value       An optional numeric value for the event.
	 * @param string|null               $category    An optional category for the event.
	 * @param int|string|null           $tenantId    The tenant identifier for multi-tenant apps.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public string $name,
		public ?array $properties = null,
		public ?string $sessionId = null,
		public ?string $visitorId = null,
		public ?string $path = null,
		public ?string $ipAddress = null,
		public ?string $userAgent = null,
		public ?float $value = null,
		public ?string $category = null,
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
		$name = $data['name'] ?? $data['event_name'] ?? '';
		if ( empty( $name ) ) {
			throw new InvalidArgumentException( 'Event name is required' );
		}

		return new static(
			name: $name,
			properties: $data['properties'] ?? null,
			sessionId: $data['session_id'] ?? null,
			visitorId: $data['visitor_id'] ?? null,
			path: $data['path'] ?? null,
			ipAddress: $request->ip(),
			userAgent: $request->userAgent(),
			value: isset( $data['value'] ) ? (float) $data['value'] : null,
			category: $data['category'] ?? null,
			tenantId: $data['tenant_id'] ?? null,
		);
	}

	/**
	 * Check if this event has a specific property.
	 *
	 * @param string $key The property key to check.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function hasProperty( string $key ): bool
	{
		return isset( $this->properties[ $key ] );
	}

	/**
	 * Get a property value.
	 *
	 * @param string $key     The property key.
	 * @param mixed  $default The default value if not found.
	 *
	 * @return mixed
	 *
	 * @since 1.0.0
	 */
	public function getProperty( string $key, mixed $default = null ): mixed
	{
		return $this->properties[ $key ] ?? $default;
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
			'name'       => $this->name,
			'properties' => $this->properties,
			'session_id' => $this->sessionId,
			'visitor_id' => $this->visitorId,
			'path'       => $this->path,
			'ip_address' => $this->ipAddress,
			'user_agent' => $this->userAgent,
			'value'      => $this->value,
			'category'   => $this->category,
			'tenant_id'  => $this->tenantId,
		], fn ( $value ) => null !== $value );
	}
}
