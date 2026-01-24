<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for page view tracking.
 *
 * Validates incoming page view tracking data from the JavaScript tracker.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Requests
 */
class TrackPageViewRequest extends FormRequest
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
		return [
			'visitor_id'             => 'required|string|max:100',
			'session_id'             => 'required|string|max:36',
			'fingerprint'            => 'nullable|string|max:64',
			'path'                   => 'required|string|max:2048',
			'title'                  => 'nullable|string|max:500',
			'hash'                   => 'nullable|string|max:255',
			'query_string'           => 'nullable|string|max:2048',
			'referrer'               => 'nullable|string|max:2048',
			'referrer_path'          => 'nullable|string|max:2048',
			'screen_width'           => 'nullable|integer|min:0|max:10000',
			'screen_height'          => 'nullable|integer|min:0|max:10000',
			'viewport_width'         => 'nullable|integer|min:0|max:10000',
			'viewport_height'        => 'nullable|integer|min:0|max:10000',
			'language'               => 'nullable|string|max:10',
			'timezone'               => 'nullable|string|max:50',
			'load_time'              => 'nullable|integer|min:0',
			'dom_ready_time'         => 'nullable|integer|min:0',
			'first_contentful_paint' => 'nullable|integer|min:0',
			'utm_source'             => 'nullable|string|max:255',
			'utm_medium'             => 'nullable|string|max:255',
			'utm_campaign'           => 'nullable|string|max:255',
			'utm_term'               => 'nullable|string|max:255',
			'utm_content'            => 'nullable|string|max:255',
			'custom_data'            => 'nullable|array',
			'custom_data.*'          => 'nullable|string|max:1000',
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
			'visitor_id.required' => __( 'Visitor ID is required for tracking.' ),
			'session_id.required' => __( 'Session ID is required for tracking.' ),
			'path.required'       => __( 'Page path is required for tracking.' ),
			'path.max'            => __( 'Page path is too long.' ),
		];
	}
}
