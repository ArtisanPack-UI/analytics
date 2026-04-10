<?php

/**
 * Browser Breakdown API Resource.
 *
 * Transforms browser breakdown data into a consistent JSON structure
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
 * Browser Breakdown API Resource.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */
class BrowserBreakdownResource extends JsonResource
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
			'browser'    => $data['browser'] ?? '',
			'version'    => $data['version'] ?? '',
			'sessions'   => $data['sessions'] ?? 0,
			'percentage' => $data['percentage'] ?? 0.0,
		];
	}
}
