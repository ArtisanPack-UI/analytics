<?php

/**
 * DigestEmailAiRequest.
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
 * Validates requests to the digest-email preview AI endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.3.0
 */
class DigestEmailAiRequest extends FormRequest
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
			'items'  => [ 'required', 'array', 'min:1' ],
			'focus'  => [ 'nullable', 'string', 'max:200' ],
			'length' => [ 'nullable', 'string', 'in:brief,detailed' ],
		];
	}
}
