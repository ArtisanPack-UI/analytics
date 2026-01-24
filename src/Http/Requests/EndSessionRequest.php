<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for ending a session.
 *
 * Validates incoming session end data from the JavaScript tracker.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Requests
 */
class EndSessionRequest extends FormRequest
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
			'session_id'   => 'required|string|max:36',
			'visitor_id'   => 'nullable|string|max:100',
			'exit_page'    => 'nullable|string|max:2048',
			'time_on_page' => 'nullable|integer|min:0',
			'scroll_depth' => 'nullable|integer|min:0|max:100',
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
			'session_id.required' => __( 'Session ID is required to end a session.' ),
		];
	}
}
