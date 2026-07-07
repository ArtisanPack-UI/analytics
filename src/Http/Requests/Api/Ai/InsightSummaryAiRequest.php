<?php

/**
 * InsightSummaryAiRequest.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests\Api\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates requests to the insight-summary AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class InsightSummaryAiRequest extends FormRequest
{
	/**
	 * {@inheritDoc}
	 */
	public function authorize(): bool
	{
		return Gate::allows( 'analytics.ai.use' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function rules(): array
	{
		return [
			'date_range'      => [ 'required', 'array' ],
			'date_range.from' => [ 'required', 'string', 'date_format:Y-m-d' ],
			'date_range.to'   => [ 'required', 'string', 'date_format:Y-m-d' ],
			'metrics'         => [ 'required', 'array', 'min:1' ],
			'compare_to'      => [ 'nullable', 'array' ],
			'compare_to.from' => [ 'required_with:compare_to', 'string', 'date_format:Y-m-d' ],
			'compare_to.to'   => [ 'required_with:compare_to', 'string', 'date_format:Y-m-d' ],
		];
	}
}
