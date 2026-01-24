<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for updating page view engagement data.
 *
 * Validates incoming page view update data from the JavaScript tracker.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Requests
 */
class TrackPageViewUpdateRequest extends FormRequest
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
			'session_id'   => 'required|uuid',
			'path'         => 'required|string|max:2048',
			'time_on_page' => 'nullable|integer|min:0|max:86400000',
			'engaged_time' => 'nullable|integer|min:0|max:86400000',
			'scroll_depth' => 'nullable|numeric|min:0|max:100',
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
			'session_id.required' => __( 'Session ID is required.' ),
			'session_id.uuid'     => __( 'Session ID must be a valid UUID.' ),
			'path.required'       => __( 'Page path is required.' ),
			'path.max'            => __( 'Page path is too long.' ),
			'time_on_page.min'    => __( 'Time on page cannot be negative.' ),
			'engaged_time.min'    => __( 'Engaged time cannot be negative.' ),
			'scroll_depth.min'    => __( 'Scroll depth cannot be negative.' ),
			'scroll_depth.max'    => __( 'Scroll depth cannot exceed 100%.' ),
		];
	}
}
