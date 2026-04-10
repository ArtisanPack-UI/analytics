<?php

/**
 * Page View Time Series API Resource.
 *
 * Transforms page view time series data into a consistent JSON structure
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
 * Page View Time Series API Resource.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */
class PageViewTimeSeriesResource extends JsonResource
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
			'date'      => $data['date'] ?? '',
			'pageviews' => $data['pageviews'] ?? 0,
			'visitors'  => $data['visitors'] ?? 0,
		];
	}
}
