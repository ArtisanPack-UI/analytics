<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for anonymous (pre-consent) page view tracking.
 *
 * Deliberately narrow. The identified endpoint requires `visitor_id` and
 * accepts a fingerprint, screen dimensions and a session; none of that is
 * permitted here, and anything extra a client sends is dropped rather than
 * stored, so a mis-wired tracker cannot quietly turn anonymous hits into
 * identifiable ones.
 *
 * @since   1.5.0
 *
 * @package ArtisanPackUI\Analytics\Http\Requests
 */
class TrackAnonymousPageViewRequest extends FormRequest
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
			'path'  => 'required|string|max:2048',
			'title' => 'nullable|string|max:500',

			// The referring host only. A full referrer URL can carry search
			// terms or identifiers in its query string, so the client sends
			// the host and the server stores nothing more.
			'referrer_host' => 'nullable|string|max:255',
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
			'path.required' => __( 'A page path is required.' ),
		];
	}
}
