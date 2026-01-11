<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for batch tracking.
 *
 * Validates incoming batch tracking data containing multiple events
 * and page views from the JavaScript tracker.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Requests
 */
class TrackBatchRequest extends FormRequest
{
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, mixed>
	 */
	public function rules(): array
	{
		$maxProperties    = config( 'artisanpack.analytics.events.max_properties', 25 );
		$maxPropertyValue = config( 'artisanpack.analytics.events.max_property_value_length', 500 );

		return [
			'items'             => 'required|array|min:1|max:50',
			'items.*.type'      => 'required|in:pageview,event',
			'items.*.data'      => 'required|array',
			'items.*.timestamp' => 'nullable|date',

			// Common data fields
			'items.*.data.visitor_id' => 'required|string|max:100',
			'items.*.data.session_id' => 'nullable|string|max:36',

			// Page view specific
			'items.*.data.path'  => 'required_if:items.*.type,pageview|nullable|string|max:2048',
			'items.*.data.title' => 'nullable|string|max:500',

			// Event specific
			'items.*.data.name'         => 'required_if:items.*.type,event|nullable|string|max:255',
			'items.*.data.category'     => 'nullable|string|max:100',
			'items.*.data.properties'   => "nullable|array|max:{$maxProperties}",
			'items.*.data.properties.*' => "nullable|max:{$maxPropertyValue}",
			'items.*.data.value'        => 'nullable|numeric',
		];
	}

	/**
	 * Get custom messages for validator errors.
	 *
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'items.required'                   => __( 'Batch items are required.' ),
			'items.max'                        => __( 'Batch size cannot exceed 50 items.' ),
			'items.*.type.required'            => __( 'Each batch item must have a type.' ),
			'items.*.type.in'                  => __( 'Batch item type must be pageview or event.' ),
			'items.*.data.required'            => __( 'Each batch item must have data.' ),
			'items.*.data.visitor_id.required' => __( 'Visitor ID is required for each batch item.' ),
		];
	}
}
