<?php

/**
 * Country Breakdown API Resource.
 *
 * Transforms country breakdown data into a consistent JSON structure
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
 * Country Breakdown API Resource.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */
class CountryBreakdownResource extends JsonResource
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
			'country'      => $data['country'] ?? '',
			'country_code' => $data['country_code'] ?? '',
			'sessions'     => $data['sessions'] ?? 0,
			'percentage'   => $data['percentage'] ?? 0.0,
		];
	}
}
