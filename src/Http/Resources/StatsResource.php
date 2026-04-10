<?php

/**
 * Stats API Resource.
 *
 * Transforms analytics statistics data into a consistent JSON structure
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
 * Stats API Resource.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */
class StatsResource extends JsonResource
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
			'pageviews'            => $data['pageviews'] ?? 0,
			'visitors'             => $data['visitors'] ?? 0,
			'sessions'             => $data['sessions'] ?? 0,
			'bounce_rate'          => $data['bounce_rate'] ?? 0.0,
			'avg_session_duration' => $data['avg_session_duration'] ?? 0,
			'pages_per_session'    => $data['pages_per_session'] ?? 0.0,
			'realtime_visitors'    => $data['realtime_visitors'] ?? 0,
			'comparison'           => $data['comparison'] ?? null,
		];
	}
}
