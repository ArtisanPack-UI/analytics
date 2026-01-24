<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for starting a session.
 *
 * Validates incoming session start data from the JavaScript tracker.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Requests
 */
class StartSessionRequest extends FormRequest
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
			'session_id'       => 'required|uuid',
			'visitor_id'       => 'required|uuid',
			'fingerprint'      => 'nullable|string|max:64',
			'entry_page'       => 'required|string|max:2048',
			'entry_page_title' => 'nullable|string|max:500',
			'referrer'         => 'nullable|string|max:2048',
			'referrer_domain'  => 'nullable|string|max:255',
			'utm_source'       => 'nullable|string|max:255',
			'utm_medium'       => 'nullable|string|max:255',
			'utm_campaign'     => 'nullable|string|max:255',
			'utm_term'         => 'nullable|string|max:255',
			'utm_content'      => 'nullable|string|max:255',
			'screen_width'     => 'nullable|integer|min:0|max:10000',
			'screen_height'    => 'nullable|integer|min:0|max:10000',
			'viewport_width'   => 'nullable|integer|min:0|max:10000',
			'viewport_height'  => 'nullable|integer|min:0|max:10000',
			'language'         => 'nullable|string|max:10',
			'timezone'         => 'nullable|string|max:50',
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
			'visitor_id.required' => __( 'Visitor ID is required.' ),
			'visitor_id.uuid'     => __( 'Visitor ID must be a valid UUID.' ),
			'entry_page.required' => __( 'Entry page is required.' ),
			'entry_page.max'      => __( 'Entry page URL is too long.' ),
		];
	}
}
