<?php

/**
 * SegmentInsightAiRequest.
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
 * Validates requests to the segment-insight AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class SegmentInsightAiRequest extends FormRequest
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
			'segment'       => [ 'required', 'array' ],
			'segment.type'  => [ 'required', 'string', 'max:100' ],
			'segment.value' => [ 'required', 'string', 'max:200' ],
			'metrics'       => [ 'required', 'array', 'min:1' ],
			'baseline'      => [ 'required', 'array', 'min:1' ],
		];
	}
}
