<?php

/**
 * Device Breakdown API Resource.
 *
 * Transforms device breakdown data into a consistent JSON structure
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
 * Device Breakdown API Resource.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */
class DeviceBreakdownResource extends JsonResource
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
			'device_type' => $data['device_type'] ?? '',
			'sessions'    => $data['sessions'] ?? 0,
			'percentage'  => $data['percentage'] ?? 0.0,
		];
	}
}
