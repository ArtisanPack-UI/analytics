<?php

/**
 * Traffic Source API Resource.
 *
 * Transforms traffic source data into a consistent JSON structure
 * for both API responses and Inertia page props.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Traffic Source API Resource.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */
class TrafficSourceResource extends JsonResource
{
	/**
	 * Transform the resource into an array.
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	public function toArray( Request $request ): array
	{
		$data = $this->resource;

		return [
			'source'   => $data['source'] ?? '',
			'medium'   => $data['medium'] ?? '',
			'sessions' => $data['sessions'] ?? 0,
			'visitors' => $data['visitors'] ?? 0,
		];
	}
}
