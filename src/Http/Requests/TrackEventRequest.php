<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for event tracking.
 *
 * Validates incoming custom event tracking data from the JavaScript tracker.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Requests
 */
class TrackEventRequest extends FormRequest
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
		$allowedNames     = config( 'artisanpack.analytics.events.allowed_names', [] );

		// Build name validation rules
		$nameRules = [ 'required', 'string', 'max:255' ];
		if ( ! empty( $allowedNames ) ) {
			$nameRules[] = Rule::in( $allowedNames );
		}

		return [
			'visitor_id'                       => 'required|string|max:100',
			'session_id'                       => 'nullable|string|max:36',
			'name'                             => $nameRules,
			'category'                         => 'nullable|string|max:100',
			'action'                           => 'nullable|string|max:100',
			'label'                            => 'nullable|string|max:255',
			'properties'                       => "nullable|array|max:{$maxProperties}",
			'properties.*'                     => "nullable|max:{$maxPropertyValue}",
			'value'                            => 'nullable|numeric',
			'path'                             => 'nullable|string|max:2048',
			'fingerprint'                      => 'nullable|array',
			'fingerprint.webdriver'            => 'nullable|boolean',
			'fingerprint.has_plugins'          => 'nullable|boolean',
			'fingerprint.has_languages'        => 'nullable|boolean',
			'fingerprint.has_webgl'            => 'nullable|boolean',
			'fingerprint.has_canvas'           => 'nullable|boolean',
			'fingerprint.headless'             => 'nullable|boolean',
			'fingerprint.missing_apis'         => 'nullable|boolean',
			'fingerprint.screen_color_depth'   => 'nullable|integer|min:0|max:64',
			'fingerprint.hardware_concurrency' => 'nullable|integer|min:0|max:1024',
			'fingerprint.device_memory'        => 'nullable|numeric|min:0|max:1024',
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
			'visitor_id.required' => __( 'Visitor ID is required for event tracking.' ),
			'name.required'       => __( 'Event name is required.' ),
			'name.max'            => __( 'Event name is too long.' ),
			'name.in'             => __( 'Event name is not allowed.' ),
			'properties.max'      => __( 'Too many event properties.' ),
		];
	}

	/**
	 * Prepare the data for validation.
	 *
	 * Sanitizes the event name to remove potentially harmful characters.
	 * Validation rules handle allowed name enforcement.
	 *
	 * @return void
	 */
	protected function prepareForValidation(): void
	{
		$name = $this->input( 'name' );

		// Type guard: only process string values
		if ( ! is_string( $name ) ) {
			return;
		}

		// Sanitize the name to remove potentially harmful characters
		// preg_replace can return null on error, coalesce to empty string
		$replaced      = preg_replace( '/[^a-zA-Z0-9_.-]/', '', $name ) ?? '';
		$sanitizedName = substr( $replaced, 0, 255 );

		$this->merge( [
			'name' => $sanitizedName,
		] );

		// Older tracker scripts sent the fingerprint as a string hash. Discard
		// any non-array value so structured fingerprint validation stays
		// backwards compatible.
		if ( $this->has( 'fingerprint' ) && ! is_array( $this->input( 'fingerprint' ) ) ) {
			$this->merge( [ 'fingerprint' => null ] );
		}
	}
}
