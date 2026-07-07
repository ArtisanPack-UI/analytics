<?php

/**
 * AnomalyExplanationAiRequest.
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
 * Validates requests to the anomaly-explanation AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class AnomalyExplanationAiRequest extends FormRequest
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
			'anomaly'           => [ 'required', 'array' ],
			'anomaly.metric'    => [ 'required', 'string', 'max:100' ],
			'anomaly.direction' => [ 'required', 'string', 'in:up,down' ],
			'anomaly.magnitude' => [ 'required', 'numeric' ],
			'anomaly.date'      => [ 'required', 'string', 'date_format:Y-m-d' ],
			'context'           => [ 'nullable', 'array' ],
		];
	}
}
